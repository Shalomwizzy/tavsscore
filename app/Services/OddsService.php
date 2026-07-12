<?php

namespace App\Services;

use App\Models\FootballMatch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetch pre-match average bookmaker odds from API-Football's /odds endpoint
 * and convert them to implied probabilities. Used to flag AI tips that
 * disagree sharply with the market.
 *
 * Bookmaker consensus is the most calibrated football predictor that exists —
 * if our AI is 80% on Home Win but 10 bookmakers price it at 50%, the AI is
 * probably hallucinating.
 */
class OddsService
{
    /** Cache odds for an hour — they barely move pre-match */
    private const CACHE_MINUTES = 60;

    /**
     * Returns implied probabilities normalised so HW+D+AW = 100, plus the
     * over_25 / btts implied probabilities if available. Null on failure.
     */
    public function impliedProbabilities(FootballMatch $match): ?array
    {
        if (! $match->api_id) return null;

        return Cache::remember(
            'odds_implied_' . $match->api_id,
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => $this->fetchAndCompute($match->api_id)
        );
    }

    private function fetchAndCompute(int $fixtureId): ?array
    {
        // Skip silently if API-Football quota is exhausted
        if (Cache::get('api_football_quota_exhausted')) return null;

        $apiKey = config('services.football.key');
        if (blank($apiKey)) return null;

        try {
            $response = Http::withHeaders(['x-apisports-key' => $apiKey])
                ->timeout(10)
                ->get(rtrim((string) config('services.football.url'), '/') . '/odds', [
                    'fixture' => $fixtureId,
                ]);
        } catch (ConnectionException | Throwable $e) {
            Log::info('OddsService request failed', ['fixture' => $fixtureId, 'error' => $e->getMessage()]);
            return null;
        }

        if ($response->failed()) return null;

        $bookmakers = data_get($response->json(), 'response.0.bookmakers', []);
        if (! is_array($bookmakers) || count($bookmakers) === 0) return null;

        // Stash the raw bookmakers array so normalisedImpliedProbabilities()
        // can compute margin-stripped O/U and BTTS without a second API call.
        Cache::put('odds_bookmakers_' . $fixtureId, $bookmakers, now()->addMinutes(self::CACHE_MINUTES));

        // Average across all available bookmakers for each market we care about
        $hwImplied = $this->averageImplied($bookmakers, 'Match Winner', ['Home', 'home']);
        $dImplied  = $this->averageImplied($bookmakers, 'Match Winner', ['Draw', 'draw']);
        $awImplied = $this->averageImplied($bookmakers, 'Match Winner', ['Away', 'away']);

        if ($hwImplied === null || $dImplied === null || $awImplied === null) {
            return null;
        }

        // Normalise to remove bookmaker overround (margin)
        $sum  = $hwImplied + $dImplied + $awImplied;
        if ($sum <= 0) return null;
        $norm = fn (float $p): float => round($p / $sum * 100, 1);

        return [
            'home_win' => $norm($hwImplied),
            'draw'     => $norm($dImplied),
            'away_win' => $norm($awImplied),
            'over_25'  => $this->averageOverUnder($bookmakers, 2.5, true),
            'btts'     => $this->averageBtts($bookmakers, true),
            'sample_size' => count($bookmakers),
        ];
    }

    /**
     * Average implied % across all bookmakers offering the named market+pick.
     */
    private function averageImplied(array $bookmakers, string $betName, array $pickNames): ?float
    {
        $implieds = [];
        foreach ($bookmakers as $bm) {
            foreach ($bm['bets'] ?? [] as $bet) {
                if (($bet['name'] ?? '') !== $betName) continue;
                foreach ($bet['values'] ?? [] as $value) {
                    if (! in_array($value['value'] ?? '', $pickNames, true)) continue;
                    $odds = (float) ($value['odd'] ?? 0);
                    if ($odds < 1.01) continue;
                    $implieds[] = (1 / $odds) * 100;
                }
            }
        }
        return empty($implieds) ? null : array_sum($implieds) / count($implieds);
    }

    /**
     * Margin-stripped probabilities for market-benchmark logging (Phase 1.5.1).
     *
     * The default impliedProbabilities() normalises 1X2 but leaves the O/U and
     * BTTS numbers as raw 1/odds averages — those still carry bookmaker
     * overround (typically +4-6pp). For the market-closing baseline we need
     * both sides of each binary market paired and normalised to sum to 1.
     *
     * Returns [home_win, draw, away_win, over_25, btts, sample_size] with
     * every value in [0, 1] or null if odds are unavailable.
     */
    public function normalisedImpliedProbabilities(FootballMatch $match): ?array
    {
        $raw = $this->impliedProbabilities($match);
        if (! $raw) return null;

        $out = [
            'home_win'    => ($raw['home_win'] ?? 0) / 100,
            'draw'        => ($raw['draw']     ?? 0) / 100,
            'away_win'    => ($raw['away_win'] ?? 0) / 100,
            'sample_size' => $raw['sample_size'] ?? 0,
        ];

        if (! $match->api_id) return $out;

        $bookmakers = Cache::get('odds_bookmakers_' . $match->api_id);
        if (! is_array($bookmakers) || count($bookmakers) === 0) {
            // Cache miss (e.g. impliedProbabilities was cached without the
            // bookmakers array). Approximate by assuming ~5% overround.
            $out['over_25'] = isset($raw['over_25']) ? min(1.0, $raw['over_25'] / 100 / 1.05) : null;
            $out['btts']    = isset($raw['btts'])    ? min(1.0, $raw['btts']    / 100 / 1.05) : null;
            return $out;
        }

        $out['over_25'] = $this->normalisedPair(
            $this->averageOverUnder($bookmakers, 2.5, true),
            $this->averageOverUnder($bookmakers, 2.5, false),
        );
        $out['btts'] = $this->normalisedPair(
            $this->averageBtts($bookmakers, true),
            $this->averageBtts($bookmakers, false),
        );

        return $out;
    }

    /**
     * Pair two opposing implied probabilities (in 0-100 range) and normalise
     * to strip bookmaker overround. Returns 0-1 or null if either side missing.
     */
    private function normalisedPair(?float $yes, ?float $no): ?float
    {
        if ($yes === null || $no === null || ($yes + $no) <= 0) return null;
        return $yes / ($yes + $no);
    }

    private function averageOverUnder(array $bookmakers, float $line, bool $over): ?float
    {
        $implieds = [];
        foreach ($bookmakers as $bm) {
            foreach ($bm['bets'] ?? [] as $bet) {
                $name = strtolower($bet['name'] ?? '');
                if (! str_contains($name, 'goals over/under') && ! str_contains($name, 'over/under')) continue;
                foreach ($bet['values'] ?? [] as $value) {
                    $val = strtolower((string) ($value['value'] ?? ''));
                    $expected = ($over ? 'over ' : 'under ') . $line;
                    if ($val !== $expected) continue;
                    $odds = (float) ($value['odd'] ?? 0);
                    if ($odds < 1.01) continue;
                    $implieds[] = (1 / $odds) * 100;
                }
            }
        }
        return empty($implieds) ? null : (int) round(array_sum($implieds) / count($implieds));
    }

    private function averageBtts(array $bookmakers, bool $yes): ?float
    {
        $implieds = [];
        foreach ($bookmakers as $bm) {
            foreach ($bm['bets'] ?? [] as $bet) {
                $name = strtolower($bet['name'] ?? '');
                if (! str_contains($name, 'both teams') && ! str_contains($name, 'btts')) continue;
                foreach ($bet['values'] ?? [] as $value) {
                    $val = strtolower((string) ($value['value'] ?? ''));
                    $match = $yes ? 'yes' : 'no';
                    if ($val !== $match) continue;
                    $odds = (float) ($value['odd'] ?? 0);
                    if ($odds < 1.01) continue;
                    $implieds[] = (1 / $odds) * 100;
                }
            }
        }
        return empty($implieds) ? null : (int) round(array_sum($implieds) / count($implieds));
    }
}
