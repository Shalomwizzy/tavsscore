<?php

namespace App\Services;

use App\Models\Prediction;
use App\Models\PredictionLog;
use App\Support\SpecialtyPickCatalog;
use Carbon\CarbonImmutable;

/**
 * Removes a previously published pick when late data makes it fail the same
 * gate used at initial selection, then fills the affected board from the
 * remaining eligible candidates. This is intentionally a correction flow,
 * not a second round of prediction generation or a new API consumer.
 */
class PublicationQualityRevalidator
{
    public function __construct(
        private readonly PublicationQualityService $quality,
        private readonly PredictionService $predictions,
    ) {}

    /**
     * @return array{withdrawn:array<int,array{match:string,market:string}>,replacements:array<int,array{match:string,market:string}>}
     */
    public function revalidateToday(): array
    {
        $today = CarbonImmutable::now('Africa/Lagos');
        $flags = $this->publishedFlags();
        $rows = Prediction::query()
            ->with('match')
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today->startOfDay(), $today->endOfDay()]))
            ->where(fn ($q) => collect($flags)->each(fn (array $row) => $q->orWhere($row['flag'], true)))
            ->get();

        $withdrawn = [];
        $affected = [];
        foreach ($rows as $prediction) {
            foreach ($flags as $entry) {
                if (! $prediction->{$entry['flag']}) {
                    continue;
                }

                $context = ($entry['context'])($prediction);
                if ($this->quality->evaluate($prediction, $context['market'], $context['probability'], $context['outcome'])['allowed']) {
                    continue;
                }

                $prediction->update([$entry['flag'] => false, $entry['rank'] => null]);
                $withdrawn[] = [
                    'match' => trim(($prediction->match?->home_team ?? 'Home').' vs '.($prediction->match?->away_team ?? 'Away')),
                    'market' => $context['outcome'] ?? $entry['label'],
                ];
                $affected[$entry['type']] = true;
            }
        }

        if (empty($withdrawn)) {
            return ['withdrawn' => [], 'replacements' => []];
        }

        $replacements = [];
        foreach (array_keys($affected) as $type) {
            $selected = match ($type) {
                'daily' => $this->predictions->selectDailyPicks(),
                'draw' => $this->predictions->selectDrawPicks(),
                'gg' => $this->predictions->selectGGPicks(),
                'over15' => $this->predictions->selectOver15Picks(),
                'over25' => $this->predictions->selectOver25Picks(),
                default => $this->predictions->selectSpecialtyMarketPicks($type),
            };

            foreach ($selected as $prediction) {
                $entry = collect($flags)->firstWhere('type', $type);
                $context = $entry ? ($entry['context'])($prediction) : $this->quality->contextForHeadline($prediction);
                $replacements[] = [
                    'match' => trim(($prediction->match?->home_team ?? 'Home').' vs '.($prediction->match?->away_team ?? 'Away')),
                    'market' => $context['outcome'] ?? $entry['label'],
                ];
            }
        }

        return ['withdrawn' => $withdrawn, 'replacements' => array_values(array_unique($replacements, SORT_REGULAR))];
    }

    /** @return array<int,array{type:string,flag:string,rank:string,label:string,context:callable(Prediction):array{market:string,probability:float,outcome:?string}}> */
    private function publishedFlags(): array
    {
        $simple = fn (string $market, string $field, string $outcome): callable => fn (Prediction $p): array => [
            'market' => $market,
            'probability' => (float) ($p->{$field} ?? 0) / 100,
            'outcome' => $outcome,
        ];

        $items = [
            ['type' => 'daily', 'flag' => 'is_daily_pick', 'rank' => 'pick_rank', 'label' => 'Daily pick', 'context' => fn (Prediction $p): array => $this->quality->contextForHeadline($p)],
            ['type' => 'draw', 'flag' => 'is_draw_pick', 'rank' => 'draw_rank', 'label' => 'Draw', 'context' => $simple(PredictionLog::MARKET_DRAW, 'draw_prob', 'Draw')],
            ['type' => 'gg', 'flag' => 'is_gg_pick', 'rank' => 'gg_rank', 'label' => 'Both Teams Score', 'context' => $simple(PredictionLog::MARKET_GG, 'btts_prob', 'Both Teams Score')],
            ['type' => 'over15', 'flag' => 'is_over15_pick', 'rank' => 'over15_rank', 'label' => 'Over 1.5 Goals', 'context' => $simple(PredictionLog::MARKET_OVER15, 'over_15_prob', 'Over 1.5 Goals')],
            ['type' => 'over25', 'flag' => 'is_over25_pick', 'rank' => 'over25_rank', 'label' => 'Over 2.5 Goals', 'context' => $simple(PredictionLog::MARKET_OVER25, 'over_25_prob', 'Over 2.5 Goals')],
        ];

        foreach (SpecialtyPickCatalog::types() as $type) {
            $config = SpecialtyPickCatalog::get($type);
            $items[] = [
                'type' => $type,
                'flag' => $config['flag'],
                'rank' => $config['rank'],
                'label' => $config['title'],
                'context' => function (Prediction $p) use ($type, $config): array {
                    $label = $config['market'] ?? $p->{$config['label_field']};
                    $board = is_array($p->market_board) ? $p->market_board : [];

                    return [
                        'market' => $this->quality->specialtyMarketFor($type),
                        'probability' => (float) ($board[$label] ?? 0) / 100,
                        'outcome' => $label,
                    ];
                },
            ];
        }

        return $items;
    }
}
