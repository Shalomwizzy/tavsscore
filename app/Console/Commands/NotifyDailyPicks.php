<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Services\OneSignalService;
use App\Services\TelegramService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class NotifyDailyPicks extends Command
{
    protected $signature = 'picks:notify {--type= : Which pick type to notify: daily|draw|gg|over15|over25|team3plus (default: all)}';

    protected $description = 'Send push + Telegram for today\'s picks. Use --type= to send one group at a time for staggered scheduling.';

    public function handle(OneSignalService $oneSignal, TelegramService $telegram): int
    {
        $tz     = 'Africa/Lagos';
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $cutoff = CarbonImmutable::now($tz)->endOfDay();
        $type   = $this->option('type') ?: 'all';
        $url    = config('app.url');

        // ── Daily picks + correct scores ───────────────────────────
        if ($type === 'all' || $type === 'daily') {
            $picks = Prediction::query()
                ->with('match')
                ->where('is_daily_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('pick_rank')
                ->get();

            if ($picks->isNotEmpty()) {
                $lines = $picks->map(function ($p) {
                    $match = $p->match;
                    if (! $match) return null;
                    $conf = $p->confidence ? " ({$p->confidence}%)" : '';
                    return "{$match->home_team} vs {$match->away_team}: {$p->predicted_outcome}{$conf}";
                })->filter()->values();

                $oneSignal->sendMatchAlert(
                    title:   '🎯 Today\'s Daily Picks Are Live!',
                    message: $lines->implode(' | ') . ' - Tap for full analysis',
                    path:    '/picks',
                );

                $telegram->sendDailyPicks($picks->map(function ($p) {
                    $match = $p->match;
                    if (! $match) return null;
                    return ['match' => "{$match->home_team} vs {$match->away_team}", 'league' => $match->league ?? '', 'tip' => $p->predicted_outcome ?? '', 'confidence' => $p->confidence ?? ''];
                })->filter()->values()->toArray(), $url);

                $this->info("Daily picks sent: {$picks->count()}");
            } else {
                $this->warn('No daily picks today — skipped.');
            }

            // Correct scores go with daily picks (same morning batch)
            $scorePredictions = Prediction::query()
                ->with('match')
                ->whereNotNull('likely_scores')
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderByDesc('confidence')
                ->limit(10)
                ->get()
                ->filter(fn ($p) => ! empty($p->likely_scores))
                ->map(function ($p) {
                    $match = $p->match;
                    if (! $match) return null;
                    return ['match' => "{$match->home_team} vs {$match->away_team}", 'scores' => array_slice(is_array($p->likely_scores) ? $p->likely_scores : [], 0, 4)];
                })->filter()->values()->toArray();

            if (! empty($scorePredictions)) {
                $telegram->sendCorrectScores($scorePredictions, $url);
            }
        }

        // ── Draw picks ─────────────────────────────────────────────
        if ($type === 'all' || $type === 'draw') {
            $drawPicks = Prediction::query()
                ->with('match')
                ->where('is_draw_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('draw_rank')
                ->get();

            if ($drawPicks->isNotEmpty()) {
                $drawLines = $drawPicks->map(function ($p) {
                    $match = $p->match;
                    if (! $match) return null;
                    $conf = $p->confidence ? " ({$p->confidence}%)" : '';
                    return "{$match->home_team} vs {$match->away_team}: Draw{$conf}";
                })->filter()->values();

                $oneSignal->sendMatchAlert(title: '🤝 Today\'s Draw Picks — Triple AI Agreed!', message: $drawLines->implode(' | ') . ' - Tap for analysis', path: '/draw-picks');
                $telegram->sendDrawPicks($drawPicks->map(fn ($p) => $p->match ? ['match' => "{$p->match->home_team} vs {$p->match->away_team}", 'league' => $p->match->league ?? '', 'confidence' => $p->confidence ?? ''] : null)->filter()->values()->toArray(), $url);
                $this->info("Draw picks sent: {$drawPicks->count()}");
            } else {
                $this->warn('No draw picks today — skipped.');
            }
        }

        // ── GG picks ───────────────────────────────────────────────
        if ($type === 'all' || $type === 'gg') {
            $ggPicks = Prediction::query()
                ->with('match')
                ->where('is_gg_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('gg_rank')
                ->get();

            if ($ggPicks->isNotEmpty()) {
                $ggLines = $ggPicks->map(function ($p) {
                    $match = $p->match;
                    if (! $match) return null;
                    $conf = $p->confidence ? " ({$p->confidence}%)" : '';
                    return "{$match->home_team} vs {$match->away_team}: GG{$conf}";
                })->filter()->values();

                $oneSignal->sendMatchAlert(title: '⚽ Today\'s GG Picks — Both Teams to Score!', message: $ggLines->implode(' | ') . ' - Tap for analysis', path: '/gg-picks');
                $telegram->sendGGPicks($ggPicks->map(fn ($p) => $p->match ? ['match' => "{$p->match->home_team} vs {$p->match->away_team}", 'league' => $p->match->league ?? '', 'confidence' => $p->confidence ?? ''] : null)->filter()->values()->toArray(), $url);
                $this->info("GG picks sent: {$ggPicks->count()}");
            } else {
                $this->warn('No GG picks today — skipped.');
            }
        }

        // ── Over 1.5 picks ─────────────────────────────────────────
        if ($type === 'all' || $type === 'over15') {
            $over15Picks = Prediction::query()
                ->with('match')
                ->where('is_over15_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('over15_rank')
                ->get();

            if ($over15Picks->isNotEmpty()) {
                $o15Lines = $over15Picks->map(function ($p) {
                    $match = $p->match;
                    if (! $match) return null;
                    return "{$match->home_team} vs {$match->away_team}: Over 1.5 (" . round($p->over_15_prob ?? 0) . "%)";
                })->filter()->values();

                $oneSignal->sendMatchAlert(title: '⚽ Today\'s Over 1.5 Goals Picks Are Live!', message: $o15Lines->implode(' | ') . ' — Tap for analysis', path: '/over-1-5');
                $telegram->sendOver15Picks($over15Picks->map(fn ($p) => $p->match ? ['match' => "{$p->match->home_team} vs {$p->match->away_team}", 'league' => $p->match->league ?? '', 'prob' => round($p->over_15_prob ?? 0)] : null)->filter()->values()->toArray(), $url);
                $this->info("Over 1.5 picks sent: {$over15Picks->count()}");
            } else {
                $this->warn('No Over 1.5 picks today — skipped.');
            }
        }

        // ── Over 2.5 picks ─────────────────────────────────────────
        if ($type === 'all' || $type === 'over25') {
            $over25Picks = Prediction::query()
                ->with('match')
                ->where('is_over25_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('over25_rank')
                ->get();

            if ($over25Picks->isNotEmpty()) {
                $o25Lines = $over25Picks->map(function ($p) {
                    $match = $p->match;
                    if (! $match) return null;
                    return "{$match->home_team} vs {$match->away_team}: Over 2.5 (" . round($p->over_25_prob ?? 0) . "%)";
                })->filter()->values();

                $oneSignal->sendMatchAlert(title: '🔥 Today\'s Over 2.5 Goals Picks Are Live!', message: $o25Lines->implode(' | ') . ' — Tap for analysis', path: '/over-2-5');
                $telegram->sendOver25Picks($over25Picks->map(fn ($p) => $p->match ? ['match' => "{$p->match->home_team} vs {$p->match->away_team}", 'league' => $p->match->league ?? '', 'prob' => round($p->over_25_prob ?? 0)] : null)->filter()->values()->toArray(), $url);
                $this->info("Over 2.5 picks sent: {$over25Picks->count()}");
            } else {
                $this->warn('No Over 2.5 picks today — skipped.');
            }
        }

        // ── Team 3+ picks ──────────────────────────────────────────
        if ($type === 'all' || $type === 'team3plus') {
            $team3Picks = Prediction::query()
                ->with('match')
                ->where('is_team3plus_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('team3plus_rank')
                ->get();

            if ($team3Picks->isNotEmpty()) {
                $t3Lines = $team3Picks->map(function ($p) {
                    $match = $p->match;
                    if (! $match) return null;
                    $prob  = round($p->team3plus_label === 'Home' ? $p->home_3plus_prob : $p->away_3plus_prob);
                    return "{$match->home_team} vs {$match->away_team}: {$p->team3plus_label} 3+ ({$prob}%)";
                })->filter()->values();

                $oneSignal->sendMatchAlert(title: '🎯 Today\'s Team to Score 3+ Picks Are Live!', message: $t3Lines->implode(' | ') . ' — Tap for analysis', path: '/team-3-plus');
                $telegram->sendTeam3PlusPicks($team3Picks->map(function ($p) {
                    if (! $p->match) return null;
                    return ['match' => "{$p->match->home_team} vs {$p->match->away_team}", 'league' => $p->match->league ?? '', 'prob' => round($p->team3plus_label === 'Home' ? $p->home_3plus_prob : $p->away_3plus_prob), 'label' => $p->team3plus_label ?? 'A Team'];
                })->filter()->values()->toArray(), $url);
                $this->info("Team 3+ picks sent: {$team3Picks->count()}");
            } else {
                $this->warn('No Team 3+ picks today — skipped.');
            }
        }

        return self::SUCCESS;
    }
}
