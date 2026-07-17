<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Models\PredictionLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * One-shot production health check. Answers:
 *   - Is the scheduler running at all?
 *   - Are fixtures being ingested?
 *   - Are predictions being generated?
 *   - Are outcomes being settled?
 *   - Are blog posts being generated?
 *   - Is DC active?
 *
 * Run from SSH:
 *   php artisan system:health-check
 *
 * Or on Hostinger with the required PHP 8.4 binary:
 *   /opt/alt/php84/usr/bin/php artisan system:health-check
 */
class SystemHealthCheck extends Command
{
    protected $signature   = 'system:health-check';
    protected $description = 'End-to-end health check for the prediction pipeline. Diagnoses stalled cron / DB / API issues.';

    public function handle(): int
    {
        $tz    = config('app.timezone', 'Africa/Lagos');
        $now   = now($tz);
        $today = $now->copy()->startOfDay();
        $problems = 0;
        $warnings = 0;

        $this->line('');
        $this->line("<fg=cyan>═══ TavsScore health check · {$now->format('Y-m-d H:i:s')} {$tz} ═══</>");
        $this->line('');

        // ── 1. Scheduler activity ──────────────────────────────────────────
        $this->line('<fg=yellow>[1] Scheduler activity</>');
        $log = storage_path('logs/schedule.log');
        if (! File::exists($log)) {
            $this->error('   ✗ storage/logs/schedule.log MISSING — cron has never written. Setup broken.');
            $problems++;
        } else {
            $size = File::size($log);
            $mtime = Carbon::createFromTimestamp(File::lastModified($log), $tz);
            $ageMin = $now->diffInMinutes($mtime);
            if ($size === 0) {
                $this->error('   ✗ schedule.log exists but is EMPTY — cron isn\'t writing to it. Wrong path/binary?');
                $problems++;
            } elseif ($ageMin > 5) {
                $this->error("   ✗ schedule.log last written {$ageMin} min ago — scheduler stalled (expect < 5 min).");
                $problems++;
            } else {
                $this->info("   ✓ schedule.log last written {$ageMin} min ago (size: " . number_format($size) . " bytes)");
            }
        }
        $lLog = storage_path('logs/laravel.log');
        if (File::exists($lLog)) {
            $lSize = File::size($lLog);
            if ($lSize > 100_000_000) {
                $this->warn(sprintf('   ⚠ laravel.log is %.1f MB — consider log rotation.', $lSize / 1_048_576));
                $warnings++;
            }
        }
        $this->line('');

        // ── 2. Fixture ingestion ───────────────────────────────────────────
        $this->line('<fg=yellow>[2] Fixture ingestion (fetch:matches)</>');
        $recentIngest = FootballMatch::where('updated_at', '>=', $now->copy()->subMinutes(30))->count();
        $recentTotal  = FootballMatch::whereDate('match_time', $today->toDateString())->count();
        if ($recentIngest === 0 && $recentTotal === 0) {
            $this->error("   ✗ Zero fixtures for today ({$today->toDateString()}) and zero updates in last 30 min.");
            $this->error('     → fetch:matches likely not running. Check scheduler.');
            $problems++;
        } elseif ($recentIngest === 0) {
            $this->warn("   ⚠ {$recentTotal} fixtures for today but nothing updated in last 30 min.");
            $this->warn('     → fetch:matches may have stopped. If matches are live this is a problem.');
            $warnings++;
        } else {
            $this->info("   ✓ {$recentTotal} fixtures for today, {$recentIngest} updated in last 30 min.");
        }

        $quota = \Illuminate\Support\Facades\Cache::get('api_football_quota_exhausted');
        if ($quota) {
            $this->warn('   ⚠ API-Football quota flag is SET — fetches will be no-ops until it expires (2h from set).');
            $warnings++;
        } else {
            $this->info('   ✓ API-Football quota flag is CLEAR.');
        }
        $this->line('');

        // ── 3. Predictions ─────────────────────────────────────────────────
        $this->line('<fg=yellow>[3] Prediction generation (predict:matches)</>');
        $preds24h = Prediction::where('created_at', '>=', $now->copy()->subHours(24))->count();
        $predsToday = Prediction::whereDate('created_at', $today->toDateString())->count();
        if ($preds24h === 0) {
            $this->error('   ✗ Zero predictions in the last 24 hours.');
            $this->error('     → predict:matches not running, OR no fixtures to predict, OR Groq/Gemini/Mistral all down.');
            $problems++;
        } else {
            $this->info("   ✓ {$preds24h} predictions in last 24h · {$predsToday} today");
        }

        $latestPred = Prediction::orderByDesc('created_at')->first();
        if ($latestPred) {
            $ageMin = $now->diffInMinutes($latestPred->created_at);
            $this->line("   Latest prediction: {$ageMin} min ago (id #{$latestPred->id})");
        }
        $this->line('');

        // ── 4. Pick selection ──────────────────────────────────────────────
        $this->line('<fg=yellow>[4] Pick selection (picks:select)</>');
        $picksToday = Prediction::where('is_daily_pick', true)
            ->whereDate('created_at', $today->toDateString())
            ->count();
        $sentFlag = \Illuminate\Support\Facades\Cache::get('picks_sent_daily_' . $today->toDateString());
        if ($picksToday === 0) {
            $hour = $now->hour;
            if ($hour < 3) {
                $this->info("   · No picks yet — picks:select --force runs at 03:00 (currently {$hour}:00).");
            } else {
                $this->error("   ✗ Zero daily picks today after 03:00 — picks:select likely didn't run or found nothing.");
                $problems++;
            }
        } else {
            $this->info("   ✓ {$picksToday} daily pick(s) selected today · notified: " . ($sentFlag ? 'YES' : 'NO'));
        }
        $this->line('');

        // ── 5. Outcome settlement ──────────────────────────────────────────
        $this->line('<fg=yellow>[5] Outcome resolution (predictions:check-outcomes)</>');
        $pending = Prediction::query()
            ->whereNull('was_correct')
            ->whereHas('match', fn ($q) => $q
                ->whereIn('status', ['FT', 'AET', 'PEN'])
                ->where('match_time', '>=', $now->copy()->subDays(3))
                ->where('match_time', '<=', $now->copy()->subHour())
            )
            ->count();
        if ($pending > 20) {
            $this->error("   ✗ {$pending} predictions still UNRESOLVED for finished matches >1h old.");
            $this->error('     → predictions:check-outcomes is stalled. Results won\'t update on picks pages.');
            $problems++;
        } elseif ($pending > 0) {
            $this->warn("   ⚠ {$pending} predictions still unresolved (some settlement lag is normal).");
            $warnings++;
        } else {
            $this->info('   ✓ No stale unresolved predictions.');
        }

        // Prediction has UPDATED_AT=null so we can't measure "settled at time X".
        // Approximate by counting predictions for recent finished matches that
        // now have a was_correct value.
        $recentSettled = Prediction::query()
            ->whereNotNull('was_correct')
            ->whereHas('match', fn ($q) => $q
                ->whereIn('status', ['FT', 'AET', 'PEN'])
                ->where('match_time', '>=', $now->copy()->subDays(2))
            )
            ->count();
        $this->line("   Predictions settled for matches in last 2 days: {$recentSettled}");
        $this->line('');

        // ── 6. Blog auto-post ──────────────────────────────────────────────
        $this->line('<fg=yellow>[6] Blog auto-post (blog:auto-post at 08:30)</>');
        $blogsToday = BlogPost::whereDate('created_at', $today->toDateString())
            ->where('is_ai_generated', true)
            ->count();
        $blogs7d = BlogPost::where('created_at', '>=', $now->copy()->subDays(7))
            ->where('is_ai_generated', true)
            ->count();
        if ($blogs7d === 0) {
            $this->error("   ✗ Zero AI-generated blog posts in last 7 days — blog:auto-post is not running.");
            $problems++;
        } elseif ($blogsToday === 0 && $now->hour >= 9) {
            $this->warn("   ⚠ No AI blog post today (schedule: 08:30 Lagos, currently {$now->hour}:00).");
            $warnings++;
        } else {
            $this->info("   ✓ {$blogs7d} AI-generated posts in last 7 days · {$blogsToday} today");
        }
        $latestBlog = BlogPost::orderByDesc('created_at')->first();
        if ($latestBlog) {
            $ageHrs = round($now->diffInHours($latestBlog->created_at), 1);
            $this->line("   Latest blog post: {$ageHrs}h ago (#{$latestBlog->id})");
        }
        $this->line('');

        // ── 7. Dixon-Coles status ──────────────────────────────────────────
        $this->line('<fg=yellow>[7] Dixon-Coles engine</>');
        if (! config('prediction.dc_enabled')) {
            $this->warn('   ⚠ DC_ENABLED=false — DC is OFF. Every prediction uses the pre-DC hybrid.');
            $warnings++;
        } else {
            $fitCount = DB::table('dc_league_params')->count();
            if ($fitCount === 0) {
                $this->error('   ✗ DC is enabled but no leagues are fitted. Run: php artisan dc:fit');
                $problems++;
            } else {
                $lastFit = DB::table('dc_league_params')->max('fit_at');
                $lastFitAge = Carbon::parse($lastFit)->diffInDays($now);
                if ($lastFitAge > 10) {
                    $this->warn("   ⚠ DC last fit {$lastFitAge} days ago (weekly cron should keep this ≤ 7). Refit stalled?");
                    $warnings++;
                } else {
                    $this->info("   ✓ DC ENABLED · {$fitCount} leagues fitted · last fit {$lastFitAge} days ago");
                }
                $dcHybridLogs = PredictionLog::where('model_version', 'dc-hybrid-v1')
                    ->where('created_at', '>=', $now->copy()->subDays(7))
                    ->count();
                if ($dcHybridLogs === 0 && $preds24h > 0) {
                    $this->warn('   ⚠ Predictions are being made but zero dc-hybrid-v1 rows in prediction_logs.');
                    $this->warn('     → Observer may not be firing. Check AppServiceProvider registration.');
                    $warnings++;
                } else {
                    $this->line("   Live dc-hybrid-v1 logs (7d): {$dcHybridLogs}");
                }
            }
        }
        $this->line('');

        // ── Summary ────────────────────────────────────────────────────────
        $this->line('<fg=cyan>─── Summary ───</>');
        if ($problems === 0 && $warnings === 0) {
            $this->info('   ✅ Everything is healthy.');
            return self::SUCCESS;
        }
        if ($problems === 0) {
            $this->warn("   ⚠ {$warnings} warning(s), no blocking problems.");
            return self::SUCCESS;
        }
        $this->error("   ❌ {$problems} blocking problem(s), {$warnings} warning(s).");
        $this->line('');
        $this->line('<fg=red>Most likely fix if multiple items are failing:</>');
        $this->line('  Hostinger cron must use the full PHP 8.4 path. Command should be:');
        $this->line('');
        $this->line('    cd /home/USERNAME/domains/tavsscore.com/public_html \\');
        $this->line('      && /opt/alt/php84/usr/bin/php artisan schedule:run \\');
        $this->line('      >> storage/logs/schedule.log 2>&1');
        $this->line('');
        $this->line('  Bare "php" is 8.3 on Hostinger and errors out on Composer platform check.');
        $this->line('  Verify by running:  /opt/alt/php84/usr/bin/php artisan schedule:list');

        return self::FAILURE;
    }
}
