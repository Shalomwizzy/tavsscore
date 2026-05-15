<?php

namespace App\Services;

use App\Models\FootballMatch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineupService
{
    // Lineups are published 60-75 min before kickoff by most clubs.
    // We check from 3 hours before to 15 minutes after kickoff.
    private const FETCH_WINDOW_MINUTES_BEFORE = 180;
    private const FETCH_WINDOW_MINUTES_AFTER  = 15;

    public function getLineup(FootballMatch $match): string
    {
        if (! $this->isWithinWindow($match)) {
            return '';
        }

        $cacheKey = "lineup_{$match->api_id}";

        return Cache::remember($cacheKey, now()->addMinutes(60), function () use ($match) {
            return $this->fetch((int) $match->api_id);
        });
    }

    private function isWithinWindow(FootballMatch $match): bool
    {
        if (! $match->match_time || ! $match->api_id) {
            return false;
        }

        $minutesUntilKickoff = now()->diffInMinutes($match->match_time, false);

        return $minutesUntilKickoff <= self::FETCH_WINDOW_MINUTES_BEFORE
            && $minutesUntilKickoff >= -self::FETCH_WINDOW_MINUTES_AFTER;
    }

    private function fetch(int $fixtureId): string
    {
        if (Cache::get('api_football_quota_exhausted')) {
            return '';
        }

        $apiKey  = config('services.football.key');
        $baseUrl = rtrim((string) config('services.football.url'), '/');

        if (blank($apiKey)) {
            return '';
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withHeaders(['x-apisports-key' => $apiKey])
                ->acceptJson()
                ->timeout(10)
                ->get('/fixtures/lineups', ['fixture' => $fixtureId]);

            if ($response->failed()) {
                return '';
            }

            $teams = $response->json('response');

            if (empty($teams)) {
                return '';
            }

            return $this->format($teams);

        } catch (\Throwable $e) {
            Log::debug("LineupService: fetch failed for fixture {$fixtureId}", ['err' => $e->getMessage()]);
            return '';
        }
    }

    private function format(array $teams): string
    {
        $parts = [];

        foreach ($teams as $team) {
            $name      = $team['team']['name']      ?? 'Unknown';
            $formation = $team['formation']         ?? 'N/A';
            $coach     = $team['coach']['name']      ?? 'Unknown';

            $starters = collect($team['startXI'] ?? [])
                ->map(fn ($p) => ($p['player']['number'] ?? '?') . '. ' . ($p['player']['name'] ?? '?') . ' (' . ($p['player']['pos'] ?? '?') . ')')
                ->implode(', ');

            $parts[] = "{$name} [{$formation}] Coach: {$coach}\nStarters: {$starters}";
        }

        return implode("\n\n", $parts);
    }
}
