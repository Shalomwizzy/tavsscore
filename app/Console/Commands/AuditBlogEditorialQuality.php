<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Services\Blog\EditorialQualityGate;
use App\Services\Blog\GroqRateLimitException;
use App\Services\Blog\BlogArticleWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AuditBlogEditorialQuality extends Command
{
    protected $signature = 'blog:audit-editorial
        {--fix : Rewrite only articles that fail the editorial quality gate}
        {--limit=100 : Maximum number of published articles to inspect}
        {--delay=60 : Seconds to wait between AI rewrites}';

    protected $description = 'Audit published blog articles and safely improve eligible low-quality AI drafts.';

    public function handle(BlogArticleWriter $writer, EditorialQualityGate $quality): int
    {
        $fix = (bool) $this->option('fix');

        if ($fix && ! $writer->configured()) {
            $this->error('No blog-writing AI is configured. Add GROQ_API_KEY, GEMINI_API_KEY or MISTRAL_API_KEY to .env.');
            return self::FAILURE;
        }

        $posts = BlogPost::published()
            ->orderByDesc('published_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($posts->isEmpty()) {
            $this->info('No published articles found.');
            return self::SUCCESS;
        }

        $flagged = 0;
        $fixed = 0;
        $unchanged = 0;
        $rewritesAttempted = 0;
        $delay = max(0, (int) $this->option('delay'));

        foreach ($posts as $post) {
            $content = $quality->sanitise($post->content);
            $issues = $quality->issues($post->title, $content, $post->id);

            if ($issues === []) {
                $this->line("✓ #{$post->id} already meets the editorial standard: {$post->title}");
                continue;
            }

            $flagged++;
            $this->warn("! #{$post->id} needs attention: " . implode(' ', $issues));

            if (! $fix) {
                continue;
            }

            try {
                if ($rewritesAttempted > 0 && $delay > 0) {
                    $this->line("  ↳ waiting {$delay}s before the next AI rewrite to respect Groq limits…");
                    sleep($delay);
                }
                $rewritesAttempted++;
                $article = $this->rewriteUntilApproved($writer, $quality, $post);
                $post->update([
                    // Keep the existing slug so links already crawled by Google
                    // continue to work even when the headline is improved.
                    'title'           => $article['title'],
                    'content'         => $article['content'],
                    'excerpt'         => $this->excerpt($article['content']),
                    'is_ai_generated' => true,
                ]);
                $fixed++;
                $this->info("  ↳ improved safely: {$post->title}");
            } catch (GroqRateLimitException $e) {
                $this->warn("  ↳ Groq rate limit reached. Stopping safely; wait at least {$e->retryAfterSeconds} seconds before running the command again.");

                return self::FAILURE;
            } catch (Throwable $e) {
                $unchanged++;
                Log::warning('Blog editorial audit could not improve article', [
                    'blog_id' => $post->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  ↳ left unchanged: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Audited {$posts->count()} article(s): {$flagged} flagged, {$fixed} improved, {$unchanged} left unchanged.");

        return self::SUCCESS;
    }

    /** @return array{title:string, content:string} */
    private function rewriteUntilApproved(BlogArticleWriter $writer, EditorialQualityGate $quality, BlogPost $post): array
    {
        $revisionNote = '';

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $article = $writer->writeArticle(
                $quality->systemPrompt(),
                "Improve the published TavsScore article below. It is the only factual briefing available, so preserve confirmed facts, remove unsupported claims, and do not add new facts, quotes, statistics or sources. Write an original reader-first article of at least 750 useful words, with at least three H2 headings and five substantive paragraphs.\n\nCURRENT TITLE: {$post->title}\n\nCURRENT ARTICLE HTML:\n{$post->content}{$revisionNote}",
            );
            $content = $quality->sanitise($article['content']);
            $issues = $quality->issues($article['title'], $content, $post->id);

            if ($issues === []) {
                return ['title' => trim($article['title']), 'content' => $content];
            }

            $revisionNote = "\n\nThe previous rewrite failed this editorial review: " . implode(' ', $issues) . " Rewrite it completely and correct every issue.";
        }

        throw new \RuntimeException('Two rewrites failed the editorial quality gate.');
    }

    private function excerpt(string $html): string
    {
        return Str::limit(preg_replace('/\s+/', ' ', trim(strip_tags($html))) ?? '', 155, '…');
    }
}
