<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RequestLog;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(): View
    {
        $tz    = config('app.timezone');
        $now   = now($tz);
        $today = $now->copy()->startOfDay();
        $weekAgo = $now->copy()->subDays(6)->startOfDay();
        $monthAgo = $now->copy()->subDays(29)->startOfDay();

        // Summary tiles
        $summary = [
            'today_visits'      => RequestLog::query()->where('created_at', '>=', $today)->where('is_bot', false)->count(),
            'today_uniques'     => RequestLog::query()->where('created_at', '>=', $today)->where('is_bot', false)->distinct('ip_hash')->count('ip_hash'),
            'week_visits'       => RequestLog::query()->where('created_at', '>=', $weekAgo)->where('is_bot', false)->count(),
            'month_visits'      => RequestLog::query()->where('created_at', '>=', $monthAgo)->where('is_bot', false)->count(),
            'bot_today'         => RequestLog::query()->where('created_at', '>=', $today)->where('is_bot', true)->count(),
        ];

        // Top pages (last 7d, humans only)
        $topPages = RequestLog::query()
            ->selectRaw('path, COUNT(*) as hits, COUNT(DISTINCT ip_hash) as uniques')
            ->where('created_at', '>=', $weekAgo)
            ->where('is_bot', false)
            ->groupBy('path')
            ->orderByDesc('hits')
            ->limit(15)
            ->get();

        // Daily trend last 14 days
        $daily = collect(range(13, 0))->map(function (int $i) use ($now) {
            $start = $now->copy()->subDays($i)->startOfDay();
            $end   = $now->copy()->subDays($i)->endOfDay();
            $hits  = RequestLog::query()
                ->whereBetween('created_at', [$start, $end])
                ->where('is_bot', false)
                ->count();
            $uniques = RequestLog::query()
                ->whereBetween('created_at', [$start, $end])
                ->where('is_bot', false)
                ->distinct('ip_hash')
                ->count('ip_hash');
            return ['label' => $start->format('M d'), 'hits' => $hits, 'uniques' => $uniques];
        });

        // Top referrers
        $referrers = RequestLog::query()
            ->selectRaw('referer, COUNT(*) as hits')
            ->where('created_at', '>=', $weekAgo)
            ->where('is_bot', false)
            ->whereNotNull('referer')
            ->where('referer', '!=', '')
            ->groupBy('referer')
            ->orderByDesc('hits')
            ->limit(10)
            ->get();

        // Country breakdown (works only if you sit behind Cloudflare or similar)
        $countries = RequestLog::query()
            ->selectRaw('country, COUNT(*) as hits, COUNT(DISTINCT ip_hash) as uniques')
            ->where('created_at', '>=', $weekAgo)
            ->where('is_bot', false)
            ->whereNotNull('country')
            ->groupBy('country')
            ->orderByDesc('hits')
            ->limit(10)
            ->get();

        return view('admin.analytics.index', compact('summary', 'topPages', 'daily', 'referrers', 'countries'));
    }
}
