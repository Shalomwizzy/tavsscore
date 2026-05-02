<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Services\FootballService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MatchController extends Controller
{
    /** Live data is considered stale once it's older than this many seconds. */
    private const STALE_AFTER_SECONDS = 60;

    /** Don't kick off another fetch within this window even if data is older. */
    private const FETCH_LOCK_SECONDS = 55;

    public function __construct(private readonly FootballService $footballService)
    {
    }

    public function live(): JsonResponse
    {
        // If the cron isn't running (or the user has just opened the site after
        // a long pause), the DB rows can be minutes old. Trigger a fresh fetch
        // ourselves on first request, but rate-limit it so a stampede of
        // concurrent visitors doesn't hammer the API.
        $this->ensureFreshLiveData();

        return response()->json([
            'data' => $this->footballService->liveMatchesFromDatabase(),
        ])->header('Cache-Control', 'public, max-age=20');
    }

    public function today(): JsonResponse
    {
        return response()->json([
            'data' => $this->footballService->todayMatchesFromDatabase(),
        ])->header('Cache-Control', 'public, max-age=120');
    }

    public function finished(): JsonResponse
    {
        return response()->json([
            'data' => $this->footballService->finishedMatchesFromDatabase(),
        ])->header('Cache-Control', 'public, max-age=300');
    }

    private function ensureFreshLiveData(): void
    {
        // Don't bother if we know we're rate-limited
        if (Cache::get('api_football_quota_exhausted')) {
            return;
        }

        $lastUpdated = FootballMatch::query()
            ->whereIn('status', ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'LIVE'])
            ->max('updated_at');

        $isStale = ! $lastUpdated || now()->diffInSeconds($lastUpdated) > self::STALE_AFTER_SECONDS;
        if (! $isStale) {
            return;
        }

        // Cache lock so only one request actually fires fetch:matches per window.
        $lock = Cache::lock('fetch_matches_running', self::FETCH_LOCK_SECONDS);
        if (! $lock->get()) {
            return;
        }

        try {
            Artisan::call('fetch:matches');
        } catch (Throwable $e) {
            Log::warning('On-demand fetch:matches failed', ['error' => $e->getMessage()]);
        }
    }
}
