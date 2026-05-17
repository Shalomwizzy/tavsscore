<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Services\OneSignalService;
use App\Services\TelegramService;
use App\Support\LeagueCoverage;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class NotifyDailyPicks extends Command
{
    protected $signature = 'picks:notify {--type= : Which pick type to notify: daily|draw|gg|over15|over25|team3plus|doublechance|lineup|correctscore (default: all)}';

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
                    $conf   = $p->confidence ? " ({$p->confidence}%)" : '';
                    $league = LeagueCoverage::formatName($match->league, $match->league_country);
                    $league = $league ? "[{$league}] " : '';
                    return "{$league}{$match->home_team} vs {$match->away_team}: {$p->predicted_outcome}{$conf}";
                })->filter()->values();

                $oneSignal->sendMatchAlert(
                    title:   '🎯 Today\'s Daily Picks Are Live!',
                    message: $lines->implode(' | ') . ' - Tap for full analysis',
                    path:    '/picks',
                );

                $telegram->sendDailyPicks($picks->map(function ($p) {
                    $match = $p->match;
                    if (! $match) return null;
                    return ['match' => "{$match->home_team} vs {$match->away_team}", 'league' => LeagueCoverage::formatName($match->league, $match->league_country), 'tip' => $p->predicted_outcome ?? '', 'confidence' => $p->confidence ?? ''];
                })->filter()->values()->toArray(), $url);

                $this->info("Daily picks sent: {$picks->count()}");
            } else {
                $this->warn('No daily picks today — skipped.');
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
                    $conf   = $p->confidence ? " ({$p->confidence}%)" : '';
                    $league = LeagueCoverage::formatName($match->league, $match->league_country);
                    $league = $league ? "[{$league}] " : '';
                    return "{$league}{$match->home_team} vs {$match->away_team}: Draw{$conf}";
                })->filter()->values();

                $oneSignal->sendMatchAlert(title: '🤝 Today\'s Draw Picks — Triple AI Agreed!', message: $drawLines->implode(' | ') . ' - Tap for analysis', path: '/draw-picks');
                $telegram->sendDrawPicks($drawPicks->map(fn ($p) => $p->match ? ['match' => "{$p->match->home_team} vs {$p->match->away_team}", 'league' => LeagueCoverage::formatName($p->match->league, $p->match->league_country), 'confidence' => $p->confidence ?? ''] : null)->filter()->values()->toArray(), $url);
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
                    $conf   = $p->confidence ? " ({$p->confidence}%)" : '';
                    $league = LeagueCoverage::formatName($match->league, $match->league_country);
                    $league = $league ? "[{$league}] " : '';
                    return "{$league}{$match->home_team} vs {$match->away_team}: GG{$conf}";
                })->filter()->values();

                $oneSignal->sendMatchAlert(title: '⚽ Today\'s GG Picks — Both Teams to Score!', message: $ggLines->implode(' | ') . ' - Tap for analysis', path: '/gg-picks');
                $telegram->sendGGPicks($ggPicks->map(fn ($p) => $p->match ? ['match' => "{$p->match->home_team} vs {$p->match->away_team}", 'league' => LeagueCoverage::formatName($p->match->league, $p->match->league_country), 'confidence' => $p->confidence ?? ''] : null)->filter()->values()->toArray(), $url);
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
                    $league = LeagueCoverage::formatName($match->league, $match->league_country);
                    $league = $league ? "[{$league}] " : '';
                    return "{$league}{$match->home_team} vs {$match->away_team}: Over 1.5 (" . round($p->over_15_prob ?? 0) . "%)";
                })->filter()->values();

                $oneSignal->sendMatchAlert(title: '⚽ Today\'s Over 1.5 Goals Picks Are Live!', message: $o15Lines->implode(' | ') . ' — Tap for analysis', path: '/over-1-5');
                $telegram->sendOver15Picks($over15Picks->map(fn ($p) => $p->match ? ['match' => "{$p->match->home_team} vs {$p->match->away_team}", 'league' => LeagueCoverage::formatName($p->match->league, $p->match->league_country), 'prob' => round($p->over_15_prob ?? 0)] : null)->filter()->values()->toArray(), $url);
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
                    $league = LeagueCoverage::formatName($match->league, $match->league_country);
                    $league = $league ? "[{$league}] " : '';
                    return "{$league}{$match->home_team} vs {$match->away_team}: Over 2.5 (" . round($p->over_25_prob ?? 0) . "%)";
                })->filter()->values();

                $oneSignal->sendMatchAlert(title: '🔥 Today\'s Over 2.5 Goals Picks Are Live!', message: $o25Lines->implode(' | ') . ' — Tap for analysis', path: '/over-2-5');
                $telegram->sendOver25Picks($over25Picks->map(fn ($p) => $p->match ? ['match' => "{$p->match->home_team} vs {$p->match->away_team}", 'league' => LeagueCoverage::formatName($p->match->league, $p->match->league_country), 'prob' => round($p->over_25_prob ?? 0)] : null)->filter()->values()->toArray(), $url);
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
                    $label    = $p->team3plus_label ?? 'Home';
                    $teamName = $label === 'Home' ? $match->home_team : $match->away_team;
                    $prob     = round($label === 'Home' ? ($p->home_3plus_prob ?? 0) : ($p->away_3plus_prob ?? 0));
                    $league   = LeagueCoverage::formatName($match->league, $match->league_country);
                    $league   = $league ? "[{$league}] " : '';
                    return "{$league}{$match->home_team} vs {$match->away_team}: {$teamName} 3+ NO ({$prob}%)";
                })->filter()->values();

                $oneSignal->sendMatchAlert(title: '🚫 Today\'s Team 3+ NO Picks Are Live!', message: $t3Lines->implode(' | ') . ' — Tap for analysis', path: '/team-3-plus');
                $telegram->sendTeam3PlusPicks($team3Picks->map(function ($p) {
                    if (! $p->match) return null;
                    $label    = $p->team3plus_label ?? 'Home';
                    $teamName = $label === 'Home' ? $p->match->home_team : $p->match->away_team;
                    return ['match' => "{$p->match->home_team} vs {$p->match->away_team}", 'league' => LeagueCoverage::formatName($p->match->league, $p->match->league_country), 'team' => $teamName, 'prob' => round($label === 'Home' ? ($p->home_3plus_prob ?? 0) : ($p->away_3plus_prob ?? 0))];
                })->filter()->values()->toArray(), $url);
                $this->info("Team 3+ picks sent: {$team3Picks->count()}");
            } else {
                $this->warn('No Team 3+ picks today — skipped.');
            }
        }

        // ── Double Chance picks ────────────────────────────────────
        if ($type === 'all' || $type === 'doublechance') {
            $dcPicks = Prediction::query()
                ->with('match')
                ->where('is_double_chance_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('double_chance_rank')
                ->get();

            if ($dcPicks->isNotEmpty()) {
                $dcLines = $dcPicks->map(function ($p) {
                    $match = $p->match;
                    if (! $match) return null;
                    $label  = $p->double_chance_label ?? '1X';
                    $dc1x   = round((float) $p->home_win_prob + (float) $p->draw_prob, 1);
                    $dc2x   = round((float) $p->away_win_prob + (float) $p->draw_prob, 1);
                    $prob   = $label === '1X' ? $dc1x : $dc2x;
                    $league = LeagueCoverage::formatName($match->league, $match->league_country);
                    $league = $league ? "[{$league}] " : '';
                    $desc   = $label === '1X' ? 'Home Win or Draw' : 'Away Win or Draw';
                    return "{$league}{$match->home_team} vs {$match->away_team}: {$label} ({$desc}) ({$prob}%)";
                })->filter()->values();

                $oneSignal->sendMatchAlert(
                    title:   '🎯 Today\'s Double Chance Picks Are Live!',
                    message: $dcLines->implode(' | ') . ' — Tap for analysis',
                    path:    '/double-chance',
                );

                $telegram->sendDoubleChancePicks($dcPicks->map(function ($p) {
                    if (! $p->match) return null;
                    $label = $p->double_chance_label ?? '1X';
                    $dc1x  = round((float) $p->home_win_prob + (float) $p->draw_prob, 1);
                    $dc2x  = round((float) $p->away_win_prob + (float) $p->draw_prob, 1);
                    return [
                        'match'  => "{$p->match->home_team} vs {$p->match->away_team}",
                        'league' => LeagueCoverage::formatName($p->match->league, $p->match->league_country),
                        'label'  => $label,
                        'prob'   => $label === '1X' ? $dc1x : $dc2x,
                    ];
                })->filter()->values()->toArray(), $url);

                $this->info("Double Chance picks sent: {$dcPicks->count()}");
            } else {
                $this->warn('No Double Chance picks today — skipped.');
            }
        }

        // ── Lineup picks ───────────────────────────────────────────
        if ($type === 'lineup') {
            $lineupPicks = Prediction::query()
                ->with('match')
                ->where('has_lineup', true)
                ->whereNotNull('confidence')
                ->whereNotNull('predicted_outcome')
                ->where('predicted_outcome', '!=', 'Competitive Match')
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderByDesc('confidence')
                ->limit(10)
                ->get();

            if ($lineupPicks->isNotEmpty()) {
                $lines      = [];
                $telegramData = [];
                foreach ($lineupPicks as $p) {
                    $match = $p->match;
                    if (! $match) continue;
                    $league  = LeagueCoverage::formatName($match->league, $match->league_country);
                    $league  = $league ? "[{$league}] " : '';
                    $lines[] = "{$league}{$match->home_team} vs {$match->away_team}: {$p->predicted_outcome} ({$p->confidence}%)";
                    $telegramData[] = ['match' => "{$match->home_team} vs {$match->away_team}", 'league' => LeagueCoverage::formatName($match->league, $match->league_country), 'tip' => $p->predicted_outcome, 'confidence' => $p->confidence ?? ''];
                }
                if (! empty($lines)) {
                    $oneSignal->sendMatchAlert(title: '⚡ Lineups Confirmed — Picks Updated!', message: implode(' | ', $lines) . ' — Tap for analysis', path: '/lineup-picks');
                    $telegram->sendLineupPicks($telegramData, $url);
                    $this->info("Lineup picks sent: " . count($lines));
                }
            } else {
                $this->warn('No lineup picks today — skipped.');
            }
        }

        // ── Correct score picks ────────────────────────────────────
        if ($type === 'correctscore') {
            $scorePicks = Prediction::query()
                ->with('match')
                ->where('is_correct_score_pick', true)
                ->whereHas('match', fn ($q) => $q->whereBetween('match_time', [$today, $cutoff]))
                ->orderBy('correct_score_rank')
                ->get()
                ->filter(fn ($p) => ! empty($p->likely_scores));

            if ($scorePicks->isNotEmpty()) {
                $telegramData = $scorePicks->map(function ($p) {
                    $match = $p->match;
                    if (! $match) return null;
                    return ['match' => "{$match->home_team} vs {$match->away_team}", 'league' => LeagueCoverage::formatName($match->league, $match->league_country), 'scores' => array_slice(is_array($p->likely_scores) ? $p->likely_scores : [], 0, 5)];
                })->filter()->values()->toArray();

                $pushLines = $scorePicks->map(function ($p) {
                    $match = $p->match;
                    if (! $match || empty($p->likely_scores)) return null;
                    $league = LeagueCoverage::formatName($match->league, $match->league_country);
                    $league = $league ? "[{$league}] " : '';
                    $top    = $p->likely_scores[0]['score'] ?? '';
                    return "{$league}{$match->home_team} vs {$match->away_team}: {$top}";
                })->filter()->values();

                $oneSignal->sendMatchAlert(title: '🎯 Today\'s Correct Score Predictions Are Live!', message: $pushLines->implode(' | ') . ' — Tap for all scorelines', path: '/correct-score');
                $telegram->sendCorrectScores($telegramData, $url);
                $this->info("Correct score picks sent: {$scorePicks->count()}");
            } else {
                $this->warn('No correct score predictions today — skipped.');
            }
        }

        return self::SUCCESS;
    }
}
