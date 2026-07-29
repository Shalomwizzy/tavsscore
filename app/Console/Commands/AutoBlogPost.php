<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\Coach;
use App\Models\FootballMatch;
use App\Models\MatchInjury;
use App\Models\PlayerStatistic;
use App\Models\Standing;
use App\Models\Transfer;
use App\Services\GroqBlogService;
use App\Services\Blog\EditorialQualityGate;
use App\Support\LeagueCoverage;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutoBlogPost extends Command
{
    protected $signature = 'blog:auto-post {--force : Regenerate even if a post exists today}';

    protected $description = 'Auto-generate a football news article using AI and publish it.';

    private const TOP_LEAGUE_IDS = [2, 3, 39, 61, 78, 135, 140, 848];

    public function handle(): int
    {
        $groq = app(GroqBlogService::class);

        if (! $groq->configured()) {
            $this->error('GROQ_API_KEY not configured in .env');
            return self::FAILURE;
        }

        $today = CarbonImmutable::now(config('app.timezone'));

        if (!$this->option('force')) {
            $existing = BlogPost::whereDate('created_at', $today->toDateString())
                ->where('is_ai_generated', true)->exists();

            if ($existing) {
                $this->info('AI post already generated today. Use --force to override.');
                return self::SUCCESS;
            }
        }

        $dateStr = $today->format('l, F j Y');

        // Prefer top-league matches today; broaden to any covered league; else
        // fall back to a news roundup so the blog never fails to post.
        $matches = FootballMatch::whereDate('match_time', $today->toDateString())
            ->whereIn('league_id', self::TOP_LEAGUE_IDS)
            ->orderBy('match_time')->limit(10)->get();

        if ($matches->isEmpty()) {
            $matches = FootballMatch::whereDate('match_time', $today->toDateString())
                ->where(fn ($q) => LeagueCoverage::scopeCovered($q))
                ->orderBy('match_time')->limit(10)->get();
        }

        if ($matches->isEmpty()) {
            // Roundup mode — no fixtures today, write from the football news we hold.
            $newsContext = $this->buildGeneralNewsContext();
            if (blank($newsContext)) {
                $this->warn('No matches and no football news available today — skipping auto-post.');
                return self::SUCCESS;
            }
            $matchList  = 'football news, transfers and league form';
            $userPrompt = "Write a football NEWS ROUNDUP article for {$dateStr}. There are no major fixtures today, so cover the latest transfers, standings/form, top scorers and recent results below.{$newsContext}\n\nJSON format required:\n{\"title\": \"<compelling headline, max 70 chars, SEO-optimised>\", \"content\": \"<full HTML article>\"}\n\nContent requirements:\n- Minimum 600 words.\n- Open with a punchy, opinionated hook.\n- Organise into <h2> themed sections (transfers, title race / form, top scorers, results talking points) using the REAL facts below.\n- Base everything on the facts provided; rephrase in your own words, do not invent facts.\n- Use <p> <h2> <h3> <ul> <li> <strong> tags only.\n- Real human journalist voice: varied sentences, genuine opinions, take sides.\n- No AI filler ('it is worth noting', 'furthermore', 'in conclusion', 'delve'). Do NOT use em dashes.";
        } else {
            $matchList   = $matches->map(fn ($m) => "{$m->home_team} vs {$m->away_team} ({$m->league})")->implode(', ');
            $newsContext = $this->buildNewsContext($matches);
            if (blank($newsContext)) {
                $this->warn('No verified supporting data is available for today\'s fixtures — skipping publication rather than creating a generic article.');
                return self::SUCCESS;
            }
            $userPrompt  = "Write a football match preview article for {$dateStr}.\n\nMatches: {$matchList}{$newsContext}\n\nJSON format required:\n{\"title\": \"<compelling headline, max 70 chars, SEO-optimised>\", \"content\": \"<full HTML article>\"}\n\nContent requirements:\n- Minimum 600 words. Do NOT submit fewer than 600 words under any circumstances.\n- Open with a punchy, opinionated introduction, not a generic scene-setter\n- Cover each match with its own <h2> heading using the actual team names\n- For each match: form analysis, key player matchups, tactical insight, a clear prediction with a reason\n- Use <p> <h2> <h3> <ul> <li> <strong> tags only\n- End with a short confident wrap-up, not a hedge\n- Write like a real human journalist: varied sentence lengths, natural flow, genuine opinions. Take sides.\n- No AI filler: no 'it is worth noting', 'furthermore', 'in conclusion', 'delve', 'it remains to be seen', 'tapestry'\n- Assume the reader knows football well. No basic explanations.\n- Specific details: recent form runs, head-to-head records, key player conditions, tactical setups\n- Do NOT use em dashes (—) anywhere. Use commas, colons, or full stops.";
        }

        try {
            $this->info("Generating AI article for {$dateStr}…");

            $quality = app(EditorialQualityGate::class);
            $json = $this->writeApprovedArticle($groq, $quality, $userPrompt);
            $content = $quality->sanitise($json['content']);

            $category = $this->pickCategory($matchList);
            $excerpt  = $this->buildExcerpt($content);
            $slug     = BlogPost::generateSlug($json['title']);
            // Images are uploaded manually in Admin after publication. Never
            // publish a generated SVG or a generic substitute.
            $image = null;

            $post = BlogPost::create([
                'title'           => $json['title'],
                'slug'            => $slug,
                'excerpt'         => $excerpt,
                'content'         => $content,
                'featured_image'  => $image,
                'category'        => $category,
                'author'          => 'TavsScore AI',
                'is_published'    => true,
                'is_ai_generated' => true,
                'published_at'    => now(),
            ]);

            $this->info("Published: \"{$post->title}\" (ID {$post->id})");

            return self::SUCCESS;

        } catch (Throwable $e) {
            Log::error('Auto blog post failed', ['error' => $e->getMessage()]);
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Aggregate ALL the football news we hold into a single briefing for the AI
     * writer: recent transfers, injuries/suspensions, standings movers, top
     * scorers, recent results and coach news for the teams and leagues in play.
     */
    private function buildNewsContext($matches): string
    {
        $teamNames = $matches->flatMap(fn ($m) => [$m->home_team, $m->away_team])->filter()->unique()->values();
        $leagueIds = $matches->pluck('league_id')->filter()->unique()->values();
        $season    = (int) (Standing::query()->whereIn('league_id', $leagueIds)->max('season') ?: now('Africa/Lagos')->year);

        $sections = [];

        // ── Recent transfers (last 21 days) ──
        $transfers = Transfer::query()
            ->where(fn ($q) => $q->whereIn('team_in_name', $teamNames)->orWhereIn('team_out_name', $teamNames))
            ->where('transfer_date', '>=', now()->subDays(21)->toDateString())
            ->orderByDesc('transfer_date')->limit(15)->get();
        if ($transfers->isNotEmpty()) {
            $lines = ['TRANSFERS:'];
            foreach ($transfers as $t) {
                $lines[] = "- {$t->player_name}: ".($t->team_out_name ?? '?')." to ".($t->team_in_name ?? '?')
                    .($t->type ? " ({$t->type})" : '');
            }
            $sections[] = implode("\n", $lines);
        }

        // ── Injuries / suspensions for today's fixtures ──
        $injuries = MatchInjury::query()->whereIn('match_id', $matches->pluck('id'))->get();
        if ($injuries->isNotEmpty()) {
            $lines = ['INJURIES & SUSPENSIONS:'];
            foreach ($injuries->groupBy('team_name') as $team => $rows) {
                $players = $rows->map(fn ($i) => $i->player_name.($i->reason ? " ({$i->reason})" : ''))->implode(', ');
                $lines[] = "- {$team}: {$players}";
            }
            $sections[] = implode("\n", $lines);
        }

        // ── Standings movers (leaders + strugglers per league) ──
        foreach ($leagueIds as $lid) {
            $rows = Standing::query()->where('league_id', $lid)->where('season', $season)
                ->orderBy('rank')->get();
            if ($rows->isEmpty()) continue;
            $leagueName = LeagueCoverage::formatName(
                optional($matches->firstWhere('league_id', $lid))->league,
                optional($matches->firstWhere('league_id', $lid))->league_country,
            );
            $top    = $rows->take(3)->map(fn ($s) => "{$s->rank}. {$s->team_name} ({$s->points}pts)")->implode(', ');
            $sections[] = "STANDINGS - {$leagueName}: {$top}";
        }

        // ── Top scorers in the leagues in play ──
        $scorers = PlayerStatistic::query()->whereIn('league_id', $leagueIds)->where('season', $season)
            ->where('goals', '>', 0)->orderByDesc('goals')->limit(6)->get();
        if ($scorers->isNotEmpty()) {
            $lines = ['TOP SCORERS:'];
            foreach ($scorers as $p) {
                $lines[] = "- {$p->player_name} ({$p->team_name}): {$p->goals} goals";
            }
            $sections[] = implode("\n", $lines);
        }

        // ── Recent results (last 3 days) for context ──
        $results = FootballMatch::query()
            ->whereIn('status', ['FT', 'AET', 'PEN'])
            ->where(fn ($q) => $q->whereIn('home_team', $teamNames)->orWhereIn('away_team', $teamNames))
            ->whereBetween('match_time', [now()->subDays(4), now()])
            ->orderByDesc('match_time')->limit(8)->get();
        if ($results->isNotEmpty()) {
            $lines = ['RECENT RESULTS:'];
            foreach ($results as $r) {
                $lines[] = "- {$r->home_team} {$r->home_score}-{$r->away_score} {$r->away_team}";
            }
            $sections[] = implode("\n", $lines);
        }

        // ── Coach news ──
        $coaches = Coach::query()->whereIn('team_name', $teamNames)->where('is_current', true)->get();
        if ($coaches->isNotEmpty()) {
            $lines = ['MANAGERS:'];
            foreach ($coaches as $c) {
                $lines[] = "- {$c->team_name}: {$c->name}".($c->nationality ? " ({$c->nationality})" : '');
            }
            $sections[] = implode("\n", $lines);
        }

        if (empty($sections)) {
            return '';
        }

        return "\n\nREAL FOOTBALL NEWS & FACTS (sourced from live data — transfers, injuries, form, scorers, results, managers).\n"
            ."Base the article on these REAL facts. Rephrase them in your own words for original, plagiarism-free copy. "
            ."Do NOT copy any wording verbatim, and do NOT invent facts, stats, transfers or injuries that are not listed here:\n\n"
            .implode("\n\n", $sections);
    }

    /**
     * News roundup context when there are no fixtures today: recent transfers,
     * standings leaders, top scorers and recent results across covered leagues.
     */
    private function buildGeneralNewsContext(): string
    {
        $tz        = config('app.timezone');
        $leagueIds = LeagueCoverage::coveredLeagueIds();
        $season    = (int) (Standing::query()->max('season') ?: now($tz)->year);
        $sections  = [];

        $transfers = Transfer::query()
            ->whereNotNull('team_in_name')
            ->where('transfer_date', '>=', now()->subDays(21)->toDateString())
            ->orderByDesc('transfer_date')->limit(15)->get();
        if ($transfers->isNotEmpty()) {
            $lines = ['TRANSFERS:'];
            foreach ($transfers as $t) {
                $lines[] = "- {$t->player_name}: ".($t->team_out_name ?? '?')." to ".($t->team_in_name ?? '?').($t->type ? " ({$t->type})" : '');
            }
            $sections[] = implode("\n", $lines);
        }

        $leaders = Standing::query()->where('season', $season)->where('rank', 1)->limit(8)->get();
        if ($leaders->isNotEmpty()) {
            $lines = ['LEAGUE LEADERS:'];
            foreach ($leaders as $s) {
                $lines[] = "- {$s->team_name}: {$s->points} pts, {$s->win}W-{$s->draw}D-{$s->lose}L";
            }
            $sections[] = implode("\n", $lines);
        }

        $scorers = PlayerStatistic::query()->where('season', $season)
            ->where('goals', '>', 0)->orderByDesc('goals')->limit(8)->get();
        if ($scorers->isNotEmpty()) {
            $lines = ['TOP SCORERS:'];
            foreach ($scorers as $p) {
                $lines[] = "- {$p->player_name} ({$p->team_name}): {$p->goals} goals";
            }
            $sections[] = implode("\n", $lines);
        }

        $results = FootballMatch::query()
            ->where(fn ($q) => LeagueCoverage::scopeCovered($q))
            ->whereIn('status', ['FT', 'AET', 'PEN'])
            ->whereBetween('match_time', [now()->subDays(3), now()])
            ->orderByDesc('match_time')->limit(10)->get();
        if ($results->isNotEmpty()) {
            $lines = ['RECENT RESULTS:'];
            foreach ($results as $r) {
                $lines[] = "- {$r->home_team} {$r->home_score}-{$r->away_score} {$r->away_team}";
            }
            $sections[] = implode("\n", $lines);
        }

        if (empty($sections)) {
            return '';
        }

        return "\n\nREAL FOOTBALL NEWS & FACTS (from live data). Base the article on these; rephrase in your own words, do not invent facts:\n\n"
            .implode("\n\n", $sections);
    }

    private function pickCategory(string $matchList): string
    {
        $text = strtolower($matchList);

        if (str_contains($text, 'champions league') || str_contains($text, 'ucl')) return 'Champions League';
        if (str_contains($text, 'premier league')) return 'Premier League';
        if (str_contains($text, 'la liga'))         return 'La Liga';
        if (str_contains($text, 'serie a'))         return 'Serie A';
        if (str_contains($text, 'bundesliga'))      return 'Bundesliga';
        if (str_contains($text, 'ligue 1'))         return 'Ligue 1';

        return 'Match Previews';
    }

    /** @return array{title:string, content:string} */
    private function writeApprovedArticle(GroqBlogService $groq, EditorialQualityGate $quality, string $userPrompt): array
    {
        $revisionNote = '';

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $article = $groq->writeArticle(
                $quality->systemPrompt(),
                $userPrompt . $revisionNote . "\n\nRequired output quality: write at least 750 useful words, use at least three H2 headings and five substantive paragraphs. Every claim must come from the supplied briefing.",
            );
            $content = $quality->sanitise($article['content']);
            $issues = $quality->issues($article['title'], $content);

            if ($issues === []) {
                return ['title' => $article['title'], 'content' => $content];
            }

            $revisionNote = "\n\nYour previous draft failed this editorial review: " . implode(' ', $issues) . " Rewrite it completely and correct every issue.";
        }

        throw new \RuntimeException('AI article did not meet TavsScore editorial quality requirements. Nothing was published.');
    }

    private function buildExcerpt(string $html): string
    {
        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', trim($text));

        if (mb_strlen($text) <= 155) {
            return $text;
        }

        // Truncate at last word boundary before 155 chars
        $excerpt = mb_substr($text, 0, 152);
        $lastSpace = mb_strrpos($excerpt, ' ');
        return ($lastSpace !== false ? mb_substr($excerpt, 0, $lastSpace) : $excerpt) . '…';
    }
}
