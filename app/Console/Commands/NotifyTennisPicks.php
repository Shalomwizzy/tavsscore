<?php

namespace App\Console\Commands;

use App\Models\TennisPrediction;
use App\Services\OneSignalService;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Sends today's top tennis picks (highest confidence, scheduled) to Telegram +
 * OneSignal. Cache-guarded so it sends once per day.
 */
class NotifyTennisPicks extends Command
{
    protected $signature = 'tennis:notify {--force : Send even if already sent today}';

    protected $description = "Send today's top tennis picks to Telegram + OneSignal.";

    private const MIN_CONFIDENCE = 60;
    private const MAX_PICKS = 5;

    public function handle(TelegramService $telegram, OneSignalService $oneSignal): int
    {
        $tz   = 'Africa/Lagos';
        $date = now($tz)->toDateString();
        $key  = "tennis_picks_sent_{$date}";

        if (! $this->option('force') && Cache::has($key)) {
            $this->info('Tennis picks already sent today.');
            return self::SUCCESS;
        }

        $preds = TennisPrediction::query()
            ->with('match')
            ->whereHas('match', fn ($q) => $q->where('status', 'scheduled')->whereDate('match_date', $date))
            ->where('confidence', '>=', self::MIN_CONFIDENCE)
            ->orderByDesc('confidence')
            ->take(self::MAX_PICKS)
            ->get()
            ->filter(fn ($p) => $p->match);

        if ($preds->isEmpty()) {
            $this->info('No qualifying tennis picks for today.');
            return self::SUCCESS;
        }

        $payload = $preds->map(function ($p) {
            $best = is_array($p->features) ? ($p->features['best_market'] ?? null) : null;
            return [
                'match'      => $p->match->player_one.' vs '.$p->match->player_two,
                'winner'     => $best['label'] ?? ($p->predicted_winner.' to win'),
                'confidence' => (int) round($best['prob'] ?? $p->confidence),
                'tournament' => trim(($p->match->tournament ?? '').($p->match->tour ? " ({$p->match->tour})" : '')),
            ];
        })->values()->all();

        try { $telegram->sendTennisPicks($payload, config('app.url')); } catch (\Throwable $e) { report($e); }

        $top = $preds->first();
        try {
            $oneSignal->notifyTennisPicks($top->match->player_one.' vs '.$top->match->player_two, $top->predicted_winner, $preds->count());
        } catch (\Throwable $e) {
            report($e);
        }

        Cache::put($key, true, 86400);
        $this->info('Sent '.$preds->count().' tennis picks.');

        return self::SUCCESS;
    }
}
