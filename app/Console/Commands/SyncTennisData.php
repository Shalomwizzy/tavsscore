<?php

namespace App\Console\Commands;

use App\Services\Tennis\TennisDataImporter;
use App\Services\Tennis\TennisRatingService;
use Illuminate\Console\Command;

class SyncTennisData extends Command
{
    protected $signature = 'tennis:sync {--from= : First year for a historical backfill} {--to= : Final year for a historical backfill} {--tour=both : atp, wta, or both} {--ratings : Rebuild Elo ratings after importing}';
    protected $description = 'Import permitted ATP/WTA Sackmann-style CSV data; daily runs sync current and previous year.';

    public function handle(TennisDataImporter $importer, TennisRatingService $ratings): int
    {
        $from = (int) ($this->option('from') ?: now()->year - 1);
        $to = (int) ($this->option('to') ?: now()->year);
        if ($from < 1968 || $to < $from || $to > now()->year + 1) {
            $this->error('Use a valid year range from 1968 through next year.');
            return self::FAILURE;
        }
        $tours = match (strtolower((string) $this->option('tour'))) {
            'atp' => ['ATP'], 'wta' => ['WTA'], 'both' => ['ATP', 'WTA'], default => [],
        };
        if ($tours === []) { $this->error('Tour must be atp, wta, or both.'); return self::FAILURE; }

        $total = 0;
        try {
            foreach (range($from, $to) as $year) foreach ($tours as $tour) {
                $count = $importer->import($tour, $year);
                $total += $count;
                $this->line("{$tour} {$year}: {$count} rows processed");
            }
            if ($this->option('ratings')) {
                $this->line('Rebuilding tennis Elo ratings…');
                $ratings->rebuild();
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->info("Tennis sync complete: {$total} rows processed.");
        return self::SUCCESS;
    }
}
