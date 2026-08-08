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
        // Weighted toward what grows a tips account: prediction teasers, "we
        // called it" social proof, and match-day questions (replies rank highest
        // in the algorithm). Stats/blog are lighter filler. Weighted-random each
        // call so the feed stays varied instead of a fixed rotation.
        $weights = [
            'predictionTeaser' => 5,
            'resultRecap'      => 5,
            'matchdayQuestion' => 4,
            'blogTeaser'       => 2,
            'topScorer'        => 2,
            'standingsLeader'  => 1,
        ];

        $order = [];
        foreach ($weights as $builder => $weight) {
            $order[$builder] = $weight * (mt_rand(1, 1000) / 1000);
        }
        arsort($order);

        foreach (array_keys($order) as $builder) {
            $text = $this->{$builder}();
            if ($text) {
                return $this->withCallToAction($text);
            }
        }

        return null;
    }

    /** Turn a team/player name into a bare #Hashtag for discovery. */
    private function tag(string $name): string
    {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', ucwords($name));

        return $clean !== '' ? '#'.$clean : '';
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

        $tags = trim($this->tag($p->match->home_team).' '.$this->tag($p->match->away_team));

        return "🔮 Today's AI pick\n{$p->match->home_team} vs {$p->match->away_team}\n✅ {$pick} ({$p->confidence}% confidence)\n{$tags} #FootballTips";
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

        return "✅ Called it!\n{$m->home_team} {$m->home_score}-{$m->away_score} {$m->away_team}\nAnother AI pick landed. 🎯\n#FootballTips #Winning";
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

        $tags = trim($this->tag($m->home_team).' '.$this->tag($m->away_team));

        return "🔥 {$m->home_team} vs {$m->away_team} today.\nWho's winning? Drop your score 👇\n{$tags}";
    }

    private function topScorer(): ?string
    {
        $s = PlayerStatistic::query()->where('goals', '>', 0)->orderByDesc('goals')->first();
        if (! $s) {
            return null;
        }

        return "🥇 {$s->player_name} ({$s->team_name}) — {$s->goals} goals this season. Unstoppable. 🔥\n".trim($this->tag($s->team_name).' #Football');
    }

    private function standingsLeader(): ?string
    {
        $s = Standing::query()->where('rank', 1)->where('points', '>', 0)->inRandomOrder()->first();
        if (! $s) {
            return null;
        }

        return "📊 {$s->team_name} top of the table with {$s->points} pts. Can anyone catch them?\n".trim($this->tag($s->team_name).' #Football');
    }

    private function blogTeaser(): ?string
    {
        $b = BlogPost::query()->whereNotNull('published_at')->orderByDesc('published_at')->first();
        if (! $b) {
            return null;
        }

        return "📰 {$b->title}\n#Football #FootballNews";
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
