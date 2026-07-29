<?php

namespace App\Console\Commands;

use App\Services\OneSignalService;
use App\Services\PublicationQualityRevalidator;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RevalidatePublishedPicks extends Command
{
    protected $signature = 'picks:revalidate';

    protected $description = 'Withdraw stale or no-edge published picks and fill the affected boards with currently eligible replacements.';

    public function handle(PublicationQualityRevalidator $revalidator, OneSignalService $oneSignal, TelegramService $telegram): int
    {
        $result = $revalidator->revalidateToday();
        if (empty($result['withdrawn'])) {
            $this->info('Published picks still pass the quality gate.');

            return self::SUCCESS;
        }

        foreach ($result['withdrawn'] as $pick) {
            $this->warn("Withdrawn: {$pick['match']} — {$pick['market']}");
        }
        foreach ($result['replacements'] as $pick) {
            $this->info("Replacement: {$pick['match']} — {$pick['market']}");
        }

        // Do not repeat a correction message if the scheduler retries after a
        // transient failure. A later, genuinely new withdrawal has a new key.
        $key = 'published_pick_quality_update_'.now('Africa/Lagos')->toDateString().'_'.md5(json_encode($result['withdrawn']));
        if (! Cache::has($key)) {
            $oneSignal->notifyPickQualityUpdate(count($result['withdrawn']), count($result['replacements']));
            $telegram->sendPickQualityUpdate($result['withdrawn'], $result['replacements'], config('app.url'));
            Cache::put($key, true, now()->endOfDay());
        }

        return self::SUCCESS;
    }
}
