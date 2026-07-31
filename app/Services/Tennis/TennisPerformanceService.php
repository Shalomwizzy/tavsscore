<?php

namespace App\Services\Tennis;

use App\Models\TennisPrediction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Builds a transparent, tennis-only settlement record. */
class TennisPerformanceService
{
    /**
     * @return array{by_day: Collection<string, array{predictions: Collection<int, TennisPrediction>, won: int, lost: int, resolved: int, pending: int}>, summary: array{won: int, lost: int, resolved: int, pending: int, accuracy: float|null}}
     */
    public function report(int $days = 30): array
    {
        $tz = 'Africa/Lagos';
        $until = now($tz)->startOfDay();
        $since = $until->copy()->subDays(max(1, $days - 1));

        $predictions = TennisPrediction::query()
            ->with('match')
            ->whereHas('match', fn ($query) => $query
                ->whereDate('match_date', '>=', $since->toDateString())
                ->whereDate('match_date', '<=', $until->toDateString()))
            ->orderByDesc('id')
            ->get();

        $byDay = $predictions
            ->groupBy(fn (TennisPrediction $prediction) => $prediction->match?->match_date?->toDateString() ?? 'unknown')
            ->sortKeysDesc()
            ->map(function (Collection $day): array {
                $resolved = $day->whereNotNull('was_correct');

                return [
                    'predictions' => $day,
                    'won' => $resolved->where('was_correct', true)->count(),
                    'lost' => $resolved->where('was_correct', false)->count(),
                    'resolved' => $resolved->count(),
                    'pending' => $day->whereNull('was_correct')->count(),
                ];
            });

        $resolved = $predictions->whereNotNull('was_correct');
        $won = $resolved->where('was_correct', true)->count();
        $total = $resolved->count();

        return [
            'by_day' => $byDay,
            'summary' => [
                'won' => $won,
                'lost' => $total - $won,
                'resolved' => $total,
                'pending' => $predictions->whereNull('was_correct')->count(),
                'accuracy' => $total > 0 ? round($won / $total * 100, 1) : null,
            ],
        ];
    }
}
