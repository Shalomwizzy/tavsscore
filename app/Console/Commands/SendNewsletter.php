<?php

namespace App\Console\Commands;

use App\Mail\DailyPicksNewsletter;
use App\Models\NewsletterSubscriber;
use App\Models\Prediction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNewsletter extends Command
{
    protected $signature = 'newsletter:send-daily
        {--dry-run : Print what would be sent without actually sending}
        {--throttle-ms=500 : Milliseconds to sleep between sends (raise for strict-rate-limit providers like Mailtrap)}';

    protected $description = "Email today's 3 daily picks to all confirmed subscribers.";

    public function handle(): int
    {
        $tz     = 'Africa/Lagos';
        $today  = now($tz)->startOfDay();
        $cutoff = now($tz)->endOfDay();

        $picks = Prediction::query()
            ->with('match')
            ->where('is_daily_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
            ->orderBy('pick_rank')
            ->get();

        if ($picks->isEmpty()) {
            $this->warn('No daily picks for today — newsletter not sent.');
            return self::SUCCESS;
        }

        $formatted = $picks->map(fn ($p) => $this->formatPickForEmail($p));
        $dateLabel = now($tz)->format('l, F j');

        // Yesterday's resolved picks for the "How did we do?" recap
        $yStart   = now($tz)->subDay()->startOfDay();
        $yEnd     = now($tz)->subDay()->endOfDay();
        $yPicks   = Prediction::query()
            ->with('match')
            ->where('is_daily_pick', true)
            ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$yStart, $yEnd]))
            ->orderBy('pick_rank')
            ->get();

        $yesterdayRecap = $yPicks->map(fn ($p) => [
            'home'         => $p->match?->home_team ?? '?',
            'away'         => $p->match?->away_team ?? '?',
            'home_score'   => $p->match?->home_score,
            'away_score'   => $p->match?->away_score,
            'outcome'      => $p->predicted_outcome,
            'was_correct'  => $p->was_correct,
        ]);

        $subscribers = NewsletterSubscriber::query()->active()->get();

        if ($subscribers->isEmpty()) {
            $this->info('No confirmed subscribers — nothing to send.');
            return self::SUCCESS;
        }

        $sent  = 0;
        $fails = 0;

        foreach ($subscribers as $sub) {
            // Don't double-send on the same day
            if ($sub->last_sent_at && $sub->last_sent_at->setTimezone($tz)->isSameDay(now($tz))) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  [dry-run] would send to {$sub->email}");
                $sent++;
                continue;
            }

            try {
                Mail::to($sub->email)->send(new DailyPicksNewsletter($sub, $formatted, $dateLabel, $yesterdayRecap));
                $sub->update(['last_sent_at' => now()]);
                $sent++;
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                // Provider rate-limit? Wait longer and retry once.
                if (str_contains($msg, '550') || str_contains(strtolower($msg), 'rate') || str_contains(strtolower($msg), 'too many')) {
                    sleep(2);
                    try {
                        Mail::to($sub->email)->send(new DailyPicksNewsletter($sub, $formatted, $dateLabel, $yesterdayRecap));
                        $sub->update(['last_sent_at' => now()]);
                        $sent++;
                    } catch (Throwable $retry) {
                        $fails++;
                        Log::warning('Newsletter send failed (after retry)', ['email' => $sub->email, 'error' => $retry->getMessage()]);
                    }
                } else {
                    $fails++;
                    Log::warning('Newsletter send failed', ['email' => $sub->email, 'error' => $msg]);
                }
            }
            usleep(((int) $this->option('throttle-ms')) * 1000);
        }

        $this->info("Newsletter: sent {$sent}, failed {$fails}, total subscribers {$subscribers->count()}.");
        Log::info("Newsletter daily send: {$sent} sent, {$fails} failed.");

        return self::SUCCESS;
    }

    private function formatPickForEmail(Prediction $p): array
    {
        return [
            'pick_label'     => $p->predicted_outcome,
            'tips'           => is_array($p->tips) ? $p->tips : [],
            'confidence_pct' => $p->confidence,
            'analysis'       => $p->analysis,
            'reasons'        => \App\Support\PickHelpers::reasonBullets($p->analysis, 3),
            'match' => [
                'home'   => $p->match?->home_team ?? '?',
                'away'   => $p->match?->away_team ?? '?',
                'league' => \App\Support\LeagueCoverage::formatName($p->match?->league, $p->match?->league_country),
                'time'   => $p->match?->match_time?->format('H:i'),
            ],
        ];
    }
}
