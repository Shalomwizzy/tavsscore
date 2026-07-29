<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\ShalomAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/** Trains Shalom's own versioned score model using every eligible final result. */
class ShalomTrain extends Command
{
    protected $signature = 'shalom:train {--half-life=240 : Days after which a result has half its training weight}';
    protected $description = 'Train the private Shalom AI model from verified football results.';

    public function handle(): int
    {
        $leagues = FootballMatch::query()
            ->selectRaw('league_id, COUNT(*) as n')
            ->whereNotNull('league_id')
            ->whereIn('status', ['FT', 'AET', 'PEN'])
            ->whereNotNull('home_score')->whereNotNull('away_score')
            ->where('held_for_review', false)
            ->groupBy('league_id')->havingRaw('COUNT(*) >= 100')
            ->pluck('league_id')->map(fn ($id) => (int) $id)->all();

        if ($leagues === []) {
            $this->warn('Shalom AI needs at least one league with 100 verified final scores before training.');
            return self::SUCCESS;
        }

        $this->info('Training Shalom AI on '.count($leagues).' eligible league(s)…');
        $exit = Artisan::call('dc:fit', [
            '--league' => implode(',', $leagues),
            '--model-version' => ShalomAIService::VERSION,
            '--half-life' => (float) $this->option('half-life'),
            '--min-matches' => 10,
        ]);
        $this->line(Artisan::output());
        return $exit === 0 ? self::SUCCESS : self::FAILURE;
    }
}
