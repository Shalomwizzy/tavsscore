<?php

namespace App\Console\Commands;

use App\Models\CalibrationSnapshot;
use App\Services\AdaptiveThresholdService;
use Illuminate\Console\Command;

/**
 * Records a monthly snapshot of the AI system's current calibration state.
 * Run on the 1st of each month. These snapshots power the public Track Record
 * page — after several months they prove that the system is learning and
 * becoming more accurate and better-calibrated over time.
 *
 * Key metrics captured per snapshot:
 *   - threshold: the minimum AI confidence required for a pick to be promoted
 *   - acc_pct: 30-day accuracy at snapshot time
 *   - calibration_error_avg: average gap between AI stated confidence and actual win rate
 *                            (negative = overconfident; trend toward 0 = improving calibration)
 *   - cold_markets_count: how many markets were in a losing streak
 */
class SnapshotCalibration extends Command
{
    protected $signature   = 'calibration:snapshot {--force : Overwrite existing snapshot for this month}';
    protected $description = 'Save a monthly calibration snapshot used to prove system improvement over time.';

    public function handle(AdaptiveThresholdService $adaptive): int
    {
        $period = now()->format('Y-m');

        if (! $this->option('force') && CalibrationSnapshot::where('period_label', $period)->exists()) {
            $this->info("Snapshot for {$period} already exists. Use --force to overwrite.");
            return self::SUCCESS;
        }

        $state = $adaptive->learnedState();

        // Average calibration error across bands that have enough data
        $errAvg = collect($state['confidence_bands'])
            ->filter(fn ($b) => $b['gap'] !== null && $b['trusted'])
            ->avg('gap');

        CalibrationSnapshot::updateOrCreate(
            ['period_label' => $period],
            [
                'threshold'             => $state['threshold'],
                'acc_pct'               => $state['acc_30d'],
                'total_picks'           => $state['total_30d'],
                'calibration_error_avg' => $errAvg !== null ? round($errAvg, 2) : null,
                'cold_markets_count'    => count($state['cold_markets']),
            ]
        );

        $this->info("Snapshot saved for {$period}:");
        $this->line("  Threshold:    {$state['threshold']}%");
        $this->line("  30d accuracy: " . ($state['acc_30d'] ?? '—') . '%');
        $this->line("  Calib error:  " . ($errAvg !== null ? round($errAvg, 1) . 'pp' : '—'));
        $this->line("  Cold markets: " . count($state['cold_markets']));

        return self::SUCCESS;
    }
}
