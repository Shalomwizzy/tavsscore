<?php

namespace App\Services\Booking;

use App\Models\Prediction;
use App\Models\BookingCode;
use App\Models\RolloverChallenge;
use App\Services\DixonColes\TeamNameNormalizer;
use Illuminate\Support\Collection;

/**
 * Builds today's "betslip spec" — the structured tickets the external automation
 * worker turns into booking codes on SportyBet / 1xBet.
 *
 * One ticket per market (all Over 2.5 in one, all Over 1.5 in another, etc.),
 * each built from the SAFEST qualifying selections (highest model board
 * probability first), targeting a combined odds band of 2.00–500.00. We attach
 * an estimated odds per leg (from our board probability) so the worker can trim
 * to the live-odds band; the worker asserts the real odds it books.
 */
class BetslipSpecService
{
    private const MIN_TOTAL_ODDS = 2.0;
    private const MAX_TOTAL_ODDS = 500.0;
    private const MIN_LEGS       = 3;
    private const MAX_LEGS       = 30;   // hard cap so we never blow past MAX_TOTAL_ODDS

    // High-Risk ticket: the model's confident picks (≥50%) stacked into a big
    // long-shot accumulator. Deliberately risky — small stakes, for fun.
    private const HR_FLOOR     = 50.0;
    private const MIN_HR_ODDS  = 100.0;
    private const MAX_HR_ODDS  = 1500.0;

    /**
     * Markets that reliably exist on SportyBet (and the worker can book). The
     * Safe Builder only picks from these so it never lands on a 99% line the
     * bookmaker doesn't list (e.g. deep +4.5/+5.5 handicaps, half-time markets).
     */
    private const BOOKABLE = [
        'Home Win', 'Away Win', 'Draw',
        'Over 1.5 Goals', 'Over 2.5 Goals', 'Over 3.5 Goals',
        'Under 2.5 Goals', 'Under 3.5 Goals', 'Under 4.5 Goals',
        'Both Teams Score (GG)',
        'Home or Draw (1X)', 'Draw or Away (X2)', 'Home or Away (12)',
        'Draw No Bet - Home', 'Draw No Bet - Away',
        'Home +1.5 (Handicap)', 'Away +1.5 (Handicap)',
        'Home +2.5 (Handicap)', 'Away +2.5 (Handicap)',
    ];

    // In the mixed Safe Builder, no single market may cover more than this many
    // legs — forces genuine game+market variety instead of one repeated market.
    private const MAX_SAME_MARKET = 3;

    // A mixed code must genuinely combine supported market types. Corners stay
    // out until their live SportyBet market mapping has been verified; guessing
    // an ID would risk adding the wrong selection to a user's ticket.
    private const MIN_MIXED_MARKETS = 2;

    public function __construct(private readonly BookingOutcomeLearningService $bookingLearning) {}

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
            'over-1-5'      => ['title' => 'Over 1.5 Goals'] + $single('Over 1.5 Goals', 78),
            'over-2-5'      => ['title' => 'Over 2.5 Goals'] + $single('Over 2.5 Goals', 66),
            'gg'            => ['title' => 'Both Teams to Score'] + $single('Both Teams Score (GG)', 66),
            'double-chance' => ['title' => 'Double Chance'] + $bestOf(['Home or Draw (1X)', 'Draw or Away (X2)'], 80),
            'draw-no-bet'   => ['title' => 'Draw No Bet'] + $bestOf(['Draw No Bet - Home', 'Draw No Bet - Away'], 78),
            'under-3-5'     => ['title' => 'Under 3.5 Goals'] + $single('Under 3.5 Goals', 70),
            'under-4-5'     => ['title' => 'Under 4.5 Goals'] + $single('Under 4.5 Goals', 82),
            'handicap-safe' => ['title' => 'Goal Handicap Safety (+2.5)'] + $bestOf(['Home +2.5 (Handicap)', 'Away +2.5 (Handicap)'], 85),
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
            if (! $this->bookingLearning->permits($cfg['title'])) {
                continue;
            }
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

        // ── High Risk: the model's confident calls stacked into a big-odds acca ──
        $slips[] = $this->buildHighRisk($preds);

        $slips = $this->finalizeSlips($slips, $preds);
        $alreadyPublished = BookingCode::query()
            ->whereDate('pick_date', now($tz)->toDateString())
            ->where('status', 'published')
            ->whereNotNull('slip_ref')
            ->get(['platform', 'slip_ref'])
            ->groupBy('slip_ref')
            ->map(fn (Collection $codes) => $codes
                ->pluck('platform')
                ->map(fn (string $platform) => strtolower(trim($platform)))
                ->unique()
                ->values()
                ->all())
            ->all();

        // The external worker receives a per-platform completion marker. A
        // later manual retry then fills only a missing ticket (for example
        // High Risk) rather than replacing valid codes it already published.
        $slips = array_map(function (array $slip) use ($alreadyPublished): array {
            $slip['completed_platforms'] = $alreadyPublished[$slip['ref']] ?? [];

            return $slip;
        }, $slips);

        return [
            'generated_at'   => now($tz)->toIso8601String(),
            'pick_date'      => now($tz)->toDateString(),
            'platforms'      => ['sportybet', '1xbet'],
            'min_total_odds' => self::MIN_TOTAL_ODDS,
            'max_total_odds' => self::MAX_TOTAL_ODDS,
            'slips'          => $slips,
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
            if ($chosen === null || $chosen['prob'] < $floor || ! $this->bookingLearning->permits($chosen['market'])) {
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
        // Genuine mix: prefer an unused market for each game, then cap how many
        // legs any single market may cover so it isn't one repeated market.
        $used = [];
        $legs = [];
        foreach ($preds as $p) {
            $chosen = null;
            $options = $this->bookableOptions($p->market_board, 80.0);
            foreach ($options as $opt) {
                if (! isset($used[$opt['market']])) {
                    $chosen = $opt;
                    break;
                }
            }
            foreach ($options as $opt) {
                if ($chosen !== null) break;
                if (($used[$opt['market']] ?? 0) < self::MAX_SAME_MARKET) {
                    $chosen = $opt;
                    break;
                }
            }
            if ($chosen === null) continue;
            $used[$chosen['market']] = ($used[$chosen['market']] ?? 0) + 1;
            $legs[] = [
                'pred'   => $p,
                'market' => $chosen['market'],
                'prob'   => $chosen['prob'],
                'odds'   => $this->estOdds($chosen['prob']),
            ];
        }

        $selections = $this->stackMixedToOddsBand($legs);
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

    /**
     * High-Risk ticket: one still-model-approved (≥50%) bookable call per game,
     * deliberately favouring the lower-confidence eligible line so the ticket
     * can honestly reach its 100–1500 odds band. It is separate from the safe
     * boards and must never borrow their near-certain selections.
     */
    private function buildHighRisk(Collection $preds): ?array
    {
        $legs = [];
        foreach ($preds as $p) {
            $opts = $this->bookableOptions($p->market_board, self::HR_FLOOR);
            if ($opts === []) continue;
            // bookableOptions is highest probability first. High Risk needs the
            // lowest still-approved probability from the same verified board;
            // choosing $opts[0] made its very-safe legs too short to ever form
            // a genuine high-odds ticket, so no High Risk code was emitted.
            $risk = $opts[array_key_last($opts)];
            $legs[] = ['pred' => $p, 'market' => $risk['market'], 'prob' => $risk['prob'], 'odds' => $this->estOdds($risk['prob'])];
        }

        usort($legs, fn ($a, $b) => $b['prob'] <=> $a['prob']); // safest legs first
        $selections = [];
        $combined = 1.0;
        foreach ($legs as $leg) {
            if (count($selections) >= self::MAX_LEGS) break;
            if ($combined * $leg['odds'] > self::MAX_HR_ODDS) continue;
            $sel = $this->selectionFromMatch($leg['pred']->match, $leg['market'], $leg['prob'], $leg['odds']);
            if ($sel === null) continue;
            $selections[] = $sel;
            $combined *= $leg['odds'];
            if ($combined >= self::MIN_HR_ODDS && count($selections) >= self::MIN_LEGS) break;
        }

        if ($combined < self::MIN_HR_ODDS || count($selections) < self::MIN_LEGS) {
            return null;
        }

        return [
            'ref'            => 'high-risk',
            'title'          => 'High Risk (big odds)',
            'market'         => 'High-risk accumulator — 50%+ picks',
            'min_total_odds' => self::MIN_HR_ODDS,
            'max_total_odds' => self::MAX_HR_ODDS,
            'est_total_odds' => $this->combinedOdds($selections),
            'selections'     => $selections,
            'high_risk'      => true,
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

    /** Keep at least two market types while still building with safe legs first. */
    private function stackMixedToOddsBand(array $legs): array
    {
        usort($legs, fn ($a, $b) => $b['prob'] <=> $a['prob']);
        $seed = [];
        $markets = [];
        $matches = [];
        foreach ($legs as $leg) {
            $matchId = $leg['pred']->match_id;
            if (isset($markets[$leg['market']]) || isset($matches[$matchId])) continue;
            $seed[] = $leg;
            $markets[$leg['market']] = true;
            $matches[$matchId] = true;
            if (count($markets) >= self::MIN_MIXED_MARKETS) break;
        }
        if (count($markets) < self::MIN_MIXED_MARKETS) return [];

        foreach ($legs as $leg) {
            if (count($seed) >= self::MAX_LEGS) break;
            $matchId = $leg['pred']->match_id;
            if (isset($matches[$matchId])) continue;
            $seed[] = $leg;
            $matches[$matchId] = true;
            if (count($seed) >= self::MIN_LEGS && $this->combinedLegOdds($seed) >= self::MIN_TOTAL_ODDS) break;
        }

        return $this->stackToOddsBand($seed);
    }

    private function combinedLegOdds(array $legs): float
    {
        $odds = 1.0;
        foreach ($legs as $leg) {
            $odds *= max(1.0, (float) ($leg['odds'] ?? 1));
        }
        return $odds;
    }

    /** Top up every slip to the 2.0 minimum, recompute odds, drop those short. */
    private function finalizeSlips(array $slips, Collection $preds): array
    {
        $out = [];
        foreach ($slips as $s) {
            if (! $s) continue;
            $s['selections'] = $this->ensureMinOdds($s['selections'] ?? [], $preds);
            if (count($s['selections']) < self::MIN_LEGS) continue;
            $s['est_total_odds'] = $this->combinedOdds($s['selections']);
            $out[] = $s;
        }
        return array_values($out);
    }

    /** All bookable markets on this board clearing the floor, safest first. */
    private function bookableOptions(?array $board, float $minProb): array
    {
        if (! is_array($board)) return [];
        $out = [];
        foreach (self::BOOKABLE as $market) {
            if (! $this->bookingLearning->permits($market)) continue;
            if (! isset($board[$market])) continue;
            $prob = (float) $board[$market];
            if ($prob < $minProb) continue;
            $out[] = ['market' => $market, 'prob' => $prob];
        }
        usort($out, fn ($a, $b) => $b['prob'] <=> $a['prob']);
        return $out;
    }

    /**
     * Guarantee a ticket clears the 2.0 minimum. If its own market is too safe
     * to reach 2.0, mix in the safest bookable market from other (unused) games
     * until it does. Returns [] if even that can't get there.
     */
    private function ensureMinOdds(array $selections, Collection $preds): array
    {
        if ($selections === []) return [];

        $combined = 1.0;
        $used = [];
        foreach ($selections as $s) {
            $combined *= max(1.0, (float) ($s['est_odds'] ?? 1.0));
            $used[($s['home'] ?? '').'|'.($s['away'] ?? '')] = true;
        }
        if ($combined >= self::MIN_TOTAL_ODDS) return $selections;

        foreach ($this->topUpLegs($preds, $used) as $leg) {
            if (count($selections) >= self::MAX_LEGS) break;
            if ($combined * $leg['odds'] > self::MAX_TOTAL_ODDS) break;
            $sel = $this->selectionFromMatch($leg['match'], $leg['market'], $leg['prob'], $leg['odds']);
            if ($sel === null) continue;
            $selections[] = $sel;
            $combined *= $leg['odds'];
            if ($combined >= self::MIN_TOTAL_ODDS) break;
        }

        return $combined >= self::MIN_TOTAL_ODDS ? $selections : [];
    }

    /** Top-up candidates: safest bookable market per unused game, odds-first. */
    private function topUpLegs(Collection $preds, array $usedKeys): array
    {
        $legs = [];
        foreach ($preds as $p) {
            $key = ($p->match->home_team ?? '').'|'.($p->match->away_team ?? '');
            if (isset($usedKeys[$key])) continue;
            $opts = $this->bookableOptions($p->market_board, 80.0);
            if ($opts === []) continue;
            $opt = end($opts); // lowest prob among ≥80% = highest odds → reaches 2.0 with fewer legs
            $legs[] = ['match' => $p->match, 'market' => $opt['market'], 'prob' => $opt['prob'], 'odds' => $this->estOdds($opt['prob'])];
        }
        usort($legs, fn ($a, $b) => $b['odds'] <=> $a['odds']);
        return $legs;
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
            'match_id'   => $match->id,
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
