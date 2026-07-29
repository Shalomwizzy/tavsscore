<?php

namespace App\Services\Blog;

use App\Models\BlogPost;
use Illuminate\Support\Str;

/**
 * Enforces a people-first editorial baseline before an AI article is published.
 *
 * This deliberately does not try to "game" a search engine. It prevents thin,
 * repetitive and unsafe drafts from being published at scale, while leaving the
 * final decision to Google and to the TavsScore editor.
 */
class EditorialQualityGate
{
    private const ALLOWED_TAGS = '<p><h2><h3><ul><ol><li><strong><em><blockquote>';

    private const BANNED_PHRASES = [
        'it is worth noting',
        'furthermore',
        'in conclusion',
        'delve into',
        'delve',
        'it remains to be seen',
        'tapestry',
        'game changer',
        'in the ever-evolving',
        'without further ado',
    ];

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
You are TavsScore's responsible football editor. Your job is to help readers understand a football story, not to manufacture pages for search engines.

Editorial rules:
- Use only the supplied TavsScore data briefing as factual evidence. If a fact, quote, injury, transfer, statistic, lineup or date is not in the briefing, leave it out.
- Never claim first-hand reporting, attendance, interviews, access to sources, or that you watched a match when the briefing does not establish that.
- Write an original, useful analysis in your own words. Never copy, closely paraphrase, spin, scrape, or imitate another publisher.
- Give the reader a clear angle, explain why the facts matter, and distinguish a data-based opinion/prediction from a confirmed fact.
- Avoid keyword stuffing, clickbait, fake certainty, betting guarantees, sensational claims and generic filler.
- Use descriptive headings, short readable paragraphs, and specific details from the briefing. Do not repeat the same point merely to make the article longer.
- Do not use links, citations you cannot verify, images, scripts, tables, markdown, or HTML outside p, h2, h3, ul, ol, li, strong, em and blockquote.
- Never use em dashes. Do not use the phrases: "it is worth noting", "furthermore", "in conclusion", "delve", "it remains to be seen", or "tapestry".
- Return only valid JSON with exactly two keys: "title" and "content". No markdown or code fences.
PROMPT;
    }

    public function sanitise(string $html): string
    {
        $html = preg_replace('/<(script|style|iframe|object|embed)\b[^>]*>.*?<\/\1\s*>/is', '', $html) ?? $html;
        $html = preg_replace('/<\/?(?:script|style|iframe|object|embed)[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<img[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<a\b[^>]*>(.*?)<\/a>/is', '$1', $html) ?? $html;

        return trim(strip_tags($html, self::ALLOWED_TAGS));
    }

    /** @return list<string> */
    public function issues(string $title, string $html, ?int $ignorePostId = null): array
    {
        $issues = [];
        $plain = preg_replace('/\s+/', ' ', trim(strip_tags($html))) ?? '';
        $words = str_word_count($plain);
        $titleLength = mb_strlen(trim($title));

        if ($titleLength < 35 || $titleLength > 85) {
            $issues[] = 'Title must be a clear, descriptive 35-85 characters.';
        }

        if ($words < 750) {
            $issues[] = 'Article must contain at least 750 useful words.';
        }

        if (preg_match_all('/<h2\b[^>]*>/i', $html) < 3) {
            $issues[] = 'Article needs at least three descriptive H2 sections.';
        }

        if (preg_match_all('/<p\b[^>]*>/i', $html) < 5) {
            $issues[] = 'Article needs at least five substantive paragraphs.';
        }

        foreach (self::BANNED_PHRASES as $phrase) {
            if (Str::contains(Str::lower($plain), $phrase)) {
                $issues[] = 'Article contains banned generic AI phrasing.';
                break;
            }
        }

        if (preg_match('/<\/?(?:script|style|iframe|object|embed|img|a)\b/i', $html)) {
            $issues[] = 'Article contains unsupported HTML.';
        }

        if ($this->hasNearDuplicateTitle($title, $ignorePostId)) {
            $issues[] = 'Title is too similar to an existing published article.';
        }

        return array_values(array_unique($issues));
    }

    private function hasNearDuplicateTitle(string $title, ?int $ignorePostId): bool
    {
        $candidate = $this->titleTokens($title);

        if (count($candidate) < 3) {
            return false;
        }

        $query = BlogPost::published()->latest('published_at')->limit(100);
        if ($ignorePostId) {
            $query->whereKeyNot($ignorePostId);
        }

        foreach ($query->pluck('title') as $existingTitle) {
            $existing = $this->titleTokens($existingTitle);
            $shared = count(array_intersect($candidate, $existing));
            $total = count(array_unique(array_merge($candidate, $existing)));

            if ($total > 0 && ($shared / $total) >= 0.8) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function titleTokens(string $title): array
    {
        $normalised = Str::lower(preg_replace('/[^a-z0-9]+/i', ' ', $title) ?? '');

        return array_values(array_unique(array_filter(
            explode(' ', $normalised),
            fn (string $word): bool => mb_strlen($word) > 2 && ! in_array($word, ['the', 'and', 'for', 'with', 'from', 'that'], true),
        )));
    }
}
