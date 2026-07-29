<?php

namespace App\Services\Booking;

use App\Models\Prediction;
use App\Models\RolloverChallenge;
use App\Services\DixonColes\TeamNameNormalizer;
use App\Support\PickHelpers;
use Illuminate\Support\Collection;

/**
 * Builds today's "betslip spec" — the structured tickets the external automation
 * worker turns into booking codes on SportyBet / 1xBet.
 *
 * One ticket per market (all Over 2.5 in one, all Over 1.5 in another, etc.),
 * each built from the SAFEST qualifying selections (highest model board
 * probability first), targeting a combined odds band of 3.00–500.00. We attach
 * an estimated odds per leg (from our board probability) so the worker can trim
 * to the live-odds band; the worker asserts the real odds it books.
 */
class BetslipSpecService
{
    private const MIN_TOTAL_ODDS = 3.0;
    private const MAX_TOTAL_ODDS = 500.0;
    private const MIN_LEGS       = 3;
    private const MAX_LEGS       = 30;   // hard cap so we never blow past MAX_TOTAL_ODDS

    /**
     * Per-market tickets. Each entry: title, floor (min board prob %), and
     * pick(board) → [market, prob] | null.
     */
    private function ticketConfigs(): array
    {
        $single = fn (string $key, float $floor) => [
            'floor' => $floor,
            'pick'  => function (array $board) use ($key): ?array {
                $p = isset($board[$key]) ? (float) $board[$key] : null;
                return $p === null ? null : ['market' => $key, 'prob' => $p];
            },
        ];

        // Best-of: choose the higher-probability of two sibling markets per match.
        $bestOf = fn (array $keys, float $floor) => [
            'floor' => $floor,
            'pick'  => function (array $board) use ($keys): ?array {
                $best = null;
                foreach ($keys as $k) {
                    $p = isset($board[$k]) ? (float) $board[$k] : null;
                    if ($p !== null && ($best === null || $p > $best['prob'])) {
                        $best = ['market' => $k, 'prob' => $p];
                    }
                }
                return $best;
            },
        ];

        return [
            'over-0-5'      => ['title' => 'Over 0.5 Goals (Banker)'] + $single('Over 0.5 Goals', 90),
            'over-1-5'      => ['title' => 'Over 1.5 Goals'] + $single('Over 1.5 Goals', 78),
            'over-2-5'      => ['title' => 'Over 2.5 Goals'] + $single('Over 2.5 Goals', 66),
            'gg'            => ['title' => 'Both Teams to Score'] + $single('Both Teams Score (GG)', 66),
            'double-chance' => ['title' => 'Double Chance'] + $bestOf(['Home or Draw (1X)', 'Draw or Away (X2)'], 80),
            'draw-no-bet'   => ['title' => 'Draw No Bet'] + $bestOf(['Draw No Bet - Home', 'Draw No Bet - Away'], 78),
            'under-3-5'     => ['title' => 'Under 3.5 Goals'] + $single('Under 3.5 Goals', 70),
            'under-4-5'     => ['title' => 'Under 4.5 Goals'] + $single('Under 4.5 Goals', 82),
            'under-5-5'     => ['title' => 'Under 5.5 Goals (Banker)'] + $single('Under 5.5 Goals', 90),
            'handicap-safe' => ['title' => 'Goal Handicap Safety (+4.5)'] + $bestOf(['Home +4.5 (Handicap)', 'Away +4.5 (Handicap)'], 88),
        ];
    }

    public function today(): array
    {
        $tz    = config('app.timezone');
        $start = now($tz)->startOfDay();
        $end   = now($tz)->endOfDay();

        // Every covered upcoming fixture that has a stored board.
        $preds = Prediction::query()
            ->with('match')
            ->whereNotNull('market_board')
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [$start, $end])
                ->whereNotIn('status', ['CANC', 'PST', 'ABD', 'FT', 'AET', 'PEN']))
            ->get()
            ->filter(fn (Prediction $p) => $p->match && is_array($p->market_board) && ! empty($p->market_board))
            ->values();

        $slips = [];

        // ── Per-market tickets ──
        foreach ($this->ticketConfigs() as $ref => $cfg) {
            $ticket = $this->buildMarketTicket($preds, $ref, $cfg['title'], $cfg['pick'], $cfg['floor']);
            if ($ticket) {
                $slips[] = $ticket;
            }
        }

        // ── Safe Builder: the single safest playable market on each fixture ──
        $slips[] = $this->buildSafeBuilder($preds);

        // ── Daily editor's accumulator (the ranked headline picks) ──
        $slips[] = $this->buildDailyAcca($start, $end);

        // ── Rollover: today's full multi-leg ticket ──
        $slips[] = $this->buildRolloverTicket($tz);

        return [
            'generated_at'   => now($tz)->toIso8601String(),
            'pick_date'      => now($tz)->toDateString(),
            'platforms'      => ['sportybet', '1xbet'],
            'min_total_odds' => self::MIN_TOTAL_ODDS,
            'max_total_odds' => self::MAX_TOTAL_ODDS,
            'slips'          => array_values(array_filter(
                $slips,
                fn ($s) => $s && count($s['selections'] ?? []) >= self::MIN_LEGS
            )),
        ];
    }

    /**
     * Build one market's ticket: safest selections first, accumulate estimated
     * odds until we reach MIN_TOTAL_ODDS (never exceeding MAX_TOTAL_ODDS or the
     * leg cap). Returns null if fewer than MIN_LEGS qualify.
     */
    private function buildMarketTicket(Collection $preds, string $ref, string $title, callable $pick, float $floor): ?array
    {
        $legs = [];
        foreach ($preds as $p) {
            $chosen = $pick($p->market_board);
            if ($chosen === null || $chosen['prob'] < $floor) {
                continue;
            }
            $legs[] = [
                'pred'   => $p,
                'market' => $chosen['market'],
                'prob'   => $chosen['prob'],
                'odds'   => $this->estOdds($chosen['prob']),
            ];
        }

        $selections = $this->stackToOddsBand($legs);

        if (count($selections) < self::MIN_LEGS) {
            return null;
        }

        return [
            'ref'            => $ref,
            'title'          => $title,
            'market'         => $title,
            'min_total_odds' => self::MIN_TOTAL_ODDS,
            'max_total_odds' => self::MAX_TOTAL_ODDS,
            'est_total_odds' => $this->combinedOdds($selections),
            'selections'     => $selections,
        ];
    }

    /** Mixed ticket: the safest genuinely-playable market on each fixture. */
    private function buildSafeBuilder(Collection $preds): ?array
    {
        $legs = [];
        foreach ($preds as $p) {
            $safe = PickHelpers::safestBoardMarket($p->market_board, 80.0, PickHelpers::headlineBlock());
            if ($safe === null) continue;
            $legs[] = [
                'pred'   => $p,
                'market' => $safe['market'],
                'prob'   => $safe['prob'],
                'odds'   => $this->estOdds($safe['prob']),
            ];
        }

        $selections = $this->stackToOddsBand($legs);
        if (count($selections) < self::MIN_LEGS) {
            return null;
        }

        return [
            'ref'            => 'safe-builder',
            'title'          => 'Safe Builder (mixed)',
            'market'         => 'Mixed — safest per game',
            'min_total_odds' => self::MIN_TOTAL_ODDS,
            'max_total_odds' => self::MAX_TOTAL_ODDS,
            'est_total_odds' => $this->combinedOdds($selections),
            'selections'     => $selections,
        ];
    }

    private function buildDailyAcca($start, $end): ?array
    {
        $daily = Prediction::query()
            ->with('match')
            ->where('is_daily_pick', true)
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [$start, $end])
                ->whereNotIn('status', ['CANC', 'PST', 'ABD', 'FT', 'AET', 'PEN']))
            ->orderBy('pick_rank')
            ->get();

        $selections = $daily->map(function (Prediction $p) {
            $prob = $this->boardProb($p->market_board, $p->predicted_outcome);
            return $this->selectionFromMatch($p->match, $p->predicted_outcome, $prob, $prob ? $this->estOdds($prob) : null);
        })->filter()->values()->all();

        return [
            'ref'        => 'daily-acca',
            'title'      => 'Daily Picks Accumulator',
            'market'     => 'Editor headline picks',
            'selections' => $selections,
        ];
    }

    private function buildRolloverTicket(string $tz): ?array
    {
        $challenge = RolloverChallenge::query()->where('status', 'active')->latest('started_at')->first();
        if (! $challenge) return null;

        $legs = $challenge->picks()
            ->with('match')
            ->where('pick_date', now($tz)->toDateString())
            ->orderByDesc('implied_odds')
            ->get();

        if ($legs->isEmpty()) return null;

        $selections = $legs->map(fn ($leg) => $this->selectionFromMatch(
            $leg->match, $leg->groq_verdict, null, (float) $leg->implied_odds
        ))->filter()->values()->all();

        $day = $legs->first()->day_number;
        return [
            'ref'        => 'rollover',
            'title'      => "Rollover — Day {$day}",
            'market'     => 'Rollover safe legs',
            'selections' => $selections,
        ];
    }

    /**
     * Given candidate legs (safest first once sorted), stack them until the
     * combined estimated odds reach MIN_TOTAL_ODDS, without exceeding
     * MAX_TOTAL_ODDS or the leg cap. Returns selection arrays.
     */
    private function stackToOddsBand(array $legs): array
    {
        usort($legs, fn ($a, $b) => $b['prob'] <=> $a['prob']); // safest first

        $selections = [];
        $combined   = 1.0;
        foreach ($legs as $leg) {
            if (count($selections) >= self::MAX_LEGS) break;
            if ($combined * $leg['odds'] > self::MAX_TOTAL_ODDS) break;
            $sel = $this->selectionFromMatch($leg['pred']->match, $leg['market'], $leg['prob'], $leg['odds']);
            if ($sel === null) continue;
            $selections[] = $sel;
            $combined *= $leg['odds'];
            if ($combined >= self::MIN_TOTAL_ODDS && count($selections) >= self::MIN_LEGS) break;
        }

        return $selections;
    }

    private function combinedOdds(array $selections): float
    {
        $c = 1.0;
        foreach ($selections as $s) {
            $c *= max(1.0, (float) ($s['est_odds'] ?? 1.0));
        }
        return round($c, 2);
    }

    private function estOdds(float $probPct): float
    {
        $p = max(1.0, min(99.0, $probPct)) / 100;
        return round(1 / $p, 2);
    }

    private function boardProb(?array $board, ?string $market): ?float
    {
        if (! is_array($board) || blank($market)) return null;
        return isset($board[$market]) ? (float) $board[$market] : null;
    }

    private function selectionFromMatch($match, ?string $market, ?float $prob = null, ?float $estOdds = null): ?array
    {
        if (! $match || blank($market)) {
            return null;
        }

        return array_filter([
            'home'       => $match->home_team,
            'away'       => $match->away_team,
            'home_norm'  => TeamNameNormalizer::key($match->home_team),
            'away_norm'  => TeamNameNormalizer::key($match->away_team),
            'league'     => $match->league,
            'country'    => $match->league_country,
            'kickoff'    => $match->match_time?->toIso8601String(),
            'market'     => $market,
            'model_prob' => $prob !== null ? round($prob, 1) : null,
            'est_odds'   => $estOdds,
        ], fn ($v) => $v !== null);
    }
}
