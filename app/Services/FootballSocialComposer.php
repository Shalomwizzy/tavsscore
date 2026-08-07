<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\FootballMatch;
use App\Models\PlayerStatistic;
use App\Models\Prediction;
use App\Models\Setting;
use App\Models\Standing;

/**
 * Builds varied, data-driven football posts for the @tavsscore X account — never
 * random filler: every post is grounded in a real prediction, result, stat, or
 * fixture from our own data. Returns null when a given angle has no data so the
 * caller can fall through to another.
 */
class FootballSocialComposer
{
    private const FINISHED = ['FT', 'AET', 'PEN'];
    private const NOT_PLAYABLE = ['FT', 'AET', 'PEN', 'CANC', 'PST', 'ABD'];

    public function compose(): ?string
    {
        $builders = ['predictionTeaser', 'resultRecap', 'matchdayQuestion', 'topScorer', 'standingsLeader', 'blogTeaser'];
        shuffle($builders);

        foreach ($builders as $builder) {
            $text = $this->{$builder}();
            if ($text) {
                return $this->withCallToAction($text);
            }
        }

        return null;
    }

    private function predictionTeaser(): ?string
    {
        $tz = config('app.timezone');
        $p = Prediction::query()->with('match')
            ->whereNotNull('confidence')
            ->whereHas('match', fn ($q) => $q
                ->whereBetween('match_time', [now($tz), now($tz)->endOfDay()])
                ->whereNotIn('status', self::NOT_PLAYABLE))
            ->orderByDesc('confidence')
            ->first();

        if (! $p || ! $p->match) {
            return null;
        }

        $pick = $p->tips[0]['market'] ?? $p->predicted_outcome;
        if (blank($pick)) {
            return null;
        }

        return "🔮 Today's AI pick\n{$p->match->home_team} vs {$p->match->away_team}\n✅ {$pick} ({$p->confidence}% confidence)";
    }

    private function resultRecap(): ?string
    {
        $tz = config('app.timezone');
        $p = Prediction::query()->with('match')
            ->where('was_correct', true)
            ->whereHas('match', fn ($q) => $q
                ->whereIn('status', self::FINISHED)
                ->where('match_time', '>=', now($tz)->subDays(2)))
            ->orderByDesc('id')
            ->first();

        if (! $p || ! $p->match || $p->match->home_score === null) {
            return null;
        }

        $m = $p->match;

        return "✅ Called it!\n{$m->home_team} {$m->home_score}-{$m->away_score} {$m->away_team}\nAnother AI pick landed. 🎯";
    }

    private function matchdayQuestion(): ?string
    {
        $tz = config('app.timezone');
        $m = FootballMatch::query()
            ->whereBetween('match_time', [now($tz), now($tz)->endOfDay()])
            ->whereNotIn('status', self::NOT_PLAYABLE)
            ->inRandomOrder()
            ->first();

        if (! $m) {
            return null;
        }

        return "🔥 {$m->home_team} vs {$m->away_team} today.\nWho's winning? Drop your score 👇";
    }

    private function topScorer(): ?string
    {
        $s = PlayerStatistic::query()->where('goals', '>', 0)->orderByDesc('goals')->first();
        if (! $s) {
            return null;
        }

        return "🥇 {$s->player_name} ({$s->team_name}) — {$s->goals} goals this season. Unstoppable. 🔥";
    }

    private function standingsLeader(): ?string
    {
        $s = Standing::query()->where('rank', 1)->where('points', '>', 0)->inRandomOrder()->first();
        if (! $s) {
            return null;
        }

        return "📊 {$s->team_name} top of the table with {$s->points} pts. Can anyone catch them?";
    }

    private function blogTeaser(): ?string
    {
        $b = BlogPost::query()->whereNotNull('published_at')->orderByDesc('published_at')->first();
        if (! $b) {
            return null;
        }

        return "📰 {$b->title}";
    }

    /** Append the website (always) and Telegram (sometimes) so posts drive traffic. */
    private function withCallToAction(string $text): string
    {
        $site = rtrim(config('app.url'), '/');
        $text .= "\n\n⚡ Free AI predictions 👉 {$site}";

        $telegram = Setting::get('telegram_url') ?: config('services.telegram.channel_url');
        if (filled($telegram) && random_int(1, 3) === 1) {
            $text .= "\n📢 Telegram 👉 {$telegram}";
        }

        return $text;
    }
}
