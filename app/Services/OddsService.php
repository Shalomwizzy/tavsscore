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
