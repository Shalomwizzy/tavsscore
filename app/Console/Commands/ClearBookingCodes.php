<?php

namespace App\Console\Commands;

use App\Models\BookingCode;
use Illuminate\Console\Command;

/**
 * Wipes booking codes for a fresh start. With no options it deletes ALL of
 * them; --failed removes failed placeholder rows; --before keeps only recent days.
 */
class ClearBookingCodes extends Command
{
    protected $signature = 'booking:clear {--failed : only delete failed codes} {--before= : delete codes on/before YYYY-MM-DD} {--force : delete without an interactive confirmation}';

    protected $description = 'Delete booking codes (all, failed-only, or before a date).';

    public function handle(): int
    {
        $query = BookingCode::query();

        if ($this->option('failed')) {
            $query->where(function ($failed) {
                $failed->where('status', 'failed')
                    ->orWhere('code', 'like', 'FAILED-%');
            });
        }
        if ($before = $this->option('before')) {
            $query->where('pick_date', '<=', $before);
        }

        $count = (clone $query)->count();

        if (! $this->option('force') && ! $this->option('failed') && ! $this->option('before')
            && ! $this->confirm("Delete ALL {$count} booking codes? This cannot be undone.", false)) {
            $this->warn('Aborted.');
            return self::SUCCESS;
        }

        $query->delete();
        $this->info("Deleted {$count} booking code(s).");

        return self::SUCCESS;
    }
}
