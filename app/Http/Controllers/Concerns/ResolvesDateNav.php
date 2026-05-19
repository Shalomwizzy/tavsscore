<?php

namespace App\Http\Controllers\Concerns;

use Carbon\Carbon;

trait ResolvesDateNav
{
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
