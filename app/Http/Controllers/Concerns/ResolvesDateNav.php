<?php

namespace App\Http\Controllers\Concerns;

use App\Models\FootballMatch;
use App\Support\LeagueCoverage;
use Carbon\Carbon;

trait ResolvesDateNav
{
    /**
     * Off-window (pre-season) empty-state context, or null.
     *
     * Returns an ['reason' => 'off_window', 'resume_date' => ...] array only when
     * viewing today AND no covered-league fixtures are scheduled today — so pages
     * can explain "top leagues are between seasons" instead of blaming the model.
     * Returns null when matches exist today (the market's own "none qualified"
     * copy is correct then) or when browsing a past date.
     */
    private function offWindowState(Carbon $requested, string $tz): ?array
    {
        if ($requested->toDateString() !== now($tz)->toDateString()) {
            return null;
        }

        $coveredToday = FootballMatch::query()
            ->where(fn ($q) => LeagueCoverage::scopeCovered($q))
            ->whereNotIn('status', ['CANC', 'PST', 'ABD'])
            ->whereBetween('match_time', [
                $requested->copy()->startOfDay(),
                $requested->copy()->endOfDay(),
            ])
            ->exists();

        if ($coveredToday) {
            return null;
        }

        $nextKickoff = FootballMatch::query()
            ->where(fn ($q) => LeagueCoverage::scopeCovered($q))
            ->where('match_time', '>', now($tz))
            ->orderBy('match_time')
            ->value('match_time');

        return [
            'reason'      => 'off_window',
            'resume_date' => $nextKickoff ? Carbon::parse($nextKickoff)->timezone($tz)->format('l, F j') : null,
        ];
    }

    private function resolveDate(?string $raw, string $tz): Carbon
    {
        $today = now($tz)->startOfDay();

        if (blank($raw)) {
            return $today;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $raw, $tz);
            if (! $parsed) return $today;
        } catch (\Throwable) {
            return $today;
        }

        $parsed = $parsed->startOfDay();

        if ($parsed->gt($today)) return $today;
        if ($parsed->lt($today->copy()->subDays(365))) return $today;

        return $parsed;
    }

    private function buildDateMeta(Carbon $requested, string $tz, string $routeName): array
    {
        $todayStr = now($tz)->toDateString();

        return [
            'iso'        => $requested->toDateString(),
            'is_today'   => $requested->toDateString() === $todayStr,
            'prev_iso'   => $requested->copy()->subDay()->toDateString(),
            'next_iso'   => $requested->toDateString() === $todayStr ? null : $requested->copy()->addDay()->toDateString(),
            'today_iso'  => $todayStr,
            'min_iso'    => now($tz)->subDays(365)->toDateString(),
            'max_iso'    => $todayStr,
            'pretty'     => $requested->format('l, F j Y'),
            'route'      => $routeName,
        ];
    }
}
