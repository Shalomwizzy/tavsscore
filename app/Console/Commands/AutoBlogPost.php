<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\FootballMatch;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutoBlogPost extends Command
{
    protected $signature = 'blog:auto-post {--force : Regenerate even if a post exists today}';

    protected $description = 'Auto-generate a football news article using AI and publish it.';

    private const TOP_LEAGUE_IDS = [2, 3, 39, 61, 78, 135, 140, 848];

    // Picsum Photos — stable seeded URLs, no auth required, always serve correctly.
    // Format: https://picsum.photos/seed/{seed}/1200/630
    private const IMAGES = [
        'champions' => 'https://picsum.photos/seed/football-champions/1200/630',
        'trophy'    => 'https://picsum.photos/seed/football-trophy/1200/630',
        'stadium'   => 'https://picsum.photos/seed/football-stadium/1200/630',
        'crowd'     => 'https://picsum.photos/seed/football-crowd/1200/630',
        'match'     => 'https://picsum.photos/seed/football-match/1200/630',
        'pitch'     => 'https://picsum.photos/seed/football-pitch/1200/630',
        'goal'      => 'https://picsum.photos/seed/football-goal/1200/630',
        'transfer'  => 'https://picsum.photos/seed/football-transfer/1200/630',
        'training'  => 'https://picsum.photos/seed/football-training/1200/630',
        'analysis'  => 'https://picsum.photos/seed/football-analysis/1200/630',
    ];

    public function handle(): int
    {
        $apiKey = config('services.groq.key');

        if (blank($apiKey) || $apiKey === 'your_api_key_here') {
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

        $matches = FootballMatch::whereDate('match_time', $today->toDateString())
            ->whereIn('league_id', self::TOP_LEAGUE_IDS)
            ->orderBy('match_time')->limit(10)->get();

        if ($matches->isEmpty()) {
            $this->warn('No top-league matches today - skipping auto-post.');
            return self::SUCCESS;
        }

        $matchList = $matches->map(fn ($m) => "{$m->home_team} vs {$m->away_team} ({$m->league})")->implode(', ');
        $dateStr   = $today->format('l, F j Y');

        try {
            $this->info("Generating AI article for {$dateStr}…");

            $response = Http::withToken($apiKey)
                ->acceptJson()->asJson()->timeout(45)
                ->retry(2, 1000, fn ($_, $r) => !($r && $r->status() === 429))
                ->post(config('services.groq.url'), [
                    'model'           => config('services.groq.model', 'llama-3.3-70b-versatile'),
                    'temperature'     => 0.65,
                    'max_tokens'      => 1200,
                    'response_format' => ['type' => 'json_object'],
                    'messages'        => [
                        [
                            'role'    => 'system',
                            'content' => 'You are a senior football journalist writing for TavsScore. Write exactly like a real human sports writer: opinionated, direct, sometimes blunt, with natural rhythm and varied sentence length. Mix short punchy sentences with longer analytical ones. Use everyday football language that fans actually use. Show genuine personality, take sides, make bold calls. Avoid all AI-sounding patterns: no listing things in threes, no "it is worth noting", no "furthermore", no "in conclusion", no "delve", no "it remains to be seen", no generic filler phrases. Write like you watched the matches yourself and have a real opinion. Return ONLY valid JSON with exactly two keys: "title" and "content". No markdown, no code fences. NEVER use em dashes (—). Use commas, colons, or full stops instead.',
                        ],
                        [
                            'role'    => 'user',
                            'content' => "Write a football match preview article for {$dateStr}.\n\nMatches: {$matchList}\n\nJSON format required:\n{\"title\": \"<compelling headline, max 70 chars, SEO-optimised>\", \"content\": \"<full HTML article>\"}\n\nContent requirements:\n- Minimum 600 words. Do NOT submit fewer than 600 words under any circumstances.\n- Open with a punchy, opinionated introduction, not a generic scene-setter\n- Cover each match with its own <h2> heading using the actual team names\n- For each match: form analysis, key player matchups, tactical insight, a clear prediction with a reason\n- Use <p> <h2> <h3> <ul> <li> <strong> tags only\n- End with a short confident wrap-up, not a hedge\n- Write like a real human journalist: varied sentence lengths, natural flow, genuine opinions. Take sides.\n- No AI filler: no 'it is worth noting', 'furthermore', 'in conclusion', 'delve', 'it remains to be seen', 'tapestry'\n- Assume the reader knows football well. No basic explanations.\n- Specific details: recent form runs, head-to-head records, key player conditions, tactical setups\n- Do NOT use em dashes (—) anywhere. Use commas, colons, or full stops.",
                        ],
                    ],
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('Groq API error: status ' . $response->status());
            }

            $raw  = trim((string) data_get($response->json(), 'choices.0.message.content'));
            $raw  = preg_replace('/^```(?:json)?\s*/i', '', $raw);
            $raw  = preg_replace('/\s*```\s*$/', '', $raw);
            $json = json_decode($raw, true);

            if (!$json || empty($json['title']) || empty($json['content'])) {
                throw new \RuntimeException('Invalid JSON from Groq: ' . substr($raw, 0, 200));
            }

            // Strip any <img> tags and <a> tags the AI sneaks in — they reference
            // fake URLs that will 404. We provide the hero image separately.
            $content = preg_replace('/<img[^>]*>/i', '', $json['content']);
            $content = preg_replace('/<a\b[^>]*>(.*?)<\/a>/is', '$1', $content);

            $image    = $this->pickImage($json['title'], $matchList);
            $category = $this->pickCategory($matchList);
            $excerpt  = $this->buildExcerpt($content);

            $post = BlogPost::create([
                'title'           => $json['title'],
                'slug'            => BlogPost::generateSlug($json['title']),
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

    private function pickImage(string $title, string $matchList): string
    {
        $text = strtolower($title . ' ' . $matchList);

        if (str_contains($text, 'champion') || str_contains($text, 'ucl') || str_contains($text, 'europa')) {
            return self::IMAGES['champions'];
        }
        if (str_contains($text, 'transfer') || str_contains($text, 'sign')) {
            return self::IMAGES['transfer'];
        }
        if (str_contains($text, 'tactic') || str_contains($text, 'analys') || str_contains($text, 'data')) {
            return self::IMAGES['analysis'];
        }
        if (str_contains($text, 'train')) {
            return self::IMAGES['training'];
        }
        if (str_contains($text, 'trophy') || str_contains($text, 'title') || str_contains($text, 'win')) {
            return self::IMAGES['trophy'];
        }

        // Rotate through match images based on day of week
        $rotation = ['match', 'stadium', 'crowd', 'pitch', 'goal'];
        return self::IMAGES[$rotation[date('N') % count($rotation)]];
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
