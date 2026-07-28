<?php

namespace App\Services;

use App\Models\Prediction;
use App\Support\PickHelpers;
use Carbon\CarbonImmutable;

/** Builds the date-based table of every generated football prediction. */
class DailyFootballPredictionTableService
{
    private const FINISHED_STATUSES = ['FT', 'AET', 'PEN'];

    /** @return array{date: CarbonImmutable, meta: array<string, mixed>, predictions: \Illuminate\Support\Collection, summary: array<string, int>} */
    public function forDate(?string $requestedDate): array
    {
        $timezone = config('app.timezone');
        $date = $this->resolveDate($requestedDate, $timezone);
        $end = $date->endOfDay();

        $predictions = Prediction::query()
            ->with('match')
            ->whereNotNull('predicted_outcome')
            ->whereHas('match', fn ($query) => $query->whereBetween('match_time', [$date, $end]))
            ->get()
            ->sortBy(fn (Prediction $prediction) => $prediction->match?->match_time?->getTimestamp() ?? PHP_INT_MAX)
            ->values();

        $predictions->each(fn (Prediction $prediction) => $this->settleIfFinished($prediction));

        $summary = [
            'total' => $predictions->count(),
            'won' => $predictions->where('was_correct', true)->count(),
            'lost' => $predictions->where('was_correct', false)->count(),
            'pending' => $predictions->whereNull('was_correct')->count(),
        ];

        $today = CarbonImmutable::now($timezone)->startOfDay();

        return [
            'date' => $date,
            'meta' => [
                'iso' => $date->toDateString(),
                'pretty' => $date->format('l, F j, Y'),
                'is_today' => $date->isSameDay($today),
                'is_yesterday' => $date->isSameDay($today->subDay()),
                'today_iso' => $today->toDateString(),
                'yesterday_iso' => $today->subDay()->toDateString(),
                'previous_iso' => $date->subDay()->toDateString(),
                'next_iso' => $date->lt($today) ? $date->addDay()->toDateString() : null,
            ],
            'predictions' => $predictions,
            'summary' => $summary,
        ];
    }

    private function resolveDate(?string $requestedDate, string $timezone): CarbonImmutable
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();

        if (blank($requestedDate)) {
            return $today;
        }

        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $requestedDate, $timezone)->startOfDay();
        } catch (\Throwable) {
            return $today;
        }

        return $date->gt($today) || $date->lt($today->subDays(365)) ? $today : $date;
    }

    private function settleIfFinished(Prediction $prediction): void
    {
        $match = $prediction->match;

        if ($prediction->was_correct !== null
            || ! $match
            || ! in_array($match->status, self::FINISHED_STATUSES, true)
            || $match->home_score === null
            || $match->away_score === null
            || $prediction->predicted_outcome === 'Competitive Match') {
            return;
        }

        $result = PickHelpers::resolveOutcome($prediction);

        if ($result !== null) {
            $prediction->update(['was_correct' => $result]);
            $prediction->was_correct = $result;
        }
    }
}
