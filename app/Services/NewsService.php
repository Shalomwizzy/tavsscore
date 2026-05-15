<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NewsService
{
    private const RSS_SOURCES = [
        'bbc'     => 'https://feeds.bbci.co.uk/sport/football/rss.xml',
        'sky'     => 'https://www.skysports.com/rss/12040',
        'espn'    => 'https://www.espn.com/espn/rss/soccer/news',
    ];

    /**
     * Full context for a team: general news + injury/suspension news + RSS feeds.
     * This is the main public method — used before every Groq prediction.
     */
    public function getFullContext(string $team): string
    {
        $cacheKey = 'news_full_' . md5($team);

        return Cache::remember($cacheKey, now()->addHours(2), function () use ($team) {
            $headlines = array_merge(
                $this->fetchGoogleNews("\"{$team}\" football", needle: $team, limit: 5),
                $this->fetchGoogleNews("\"{$team}\" injury OR suspended OR \"ruled out\" OR doubtful OR \"misses\"", needle: $team, limit: 5),
                $this->rssFeeds($team),
            );

            return $this->dedup($headlines, 10);
        });
    }

    /**
     * Match-specific preview: combined fixture preview, tactical team news,
     * injury/suspension updates, and pundit predictions for {home} vs {away}.
     * Cached 3 hours per fixture pair.
     */
    public function getMatchPreview(string $homeTeam, string $awayTeam, string $league = ''): string
    {
        $cacheKey = 'match_preview_' . md5("{$homeTeam}_{$awayTeam}");

        return Cache::remember($cacheKey, now()->addHours(3), function () use ($homeTeam, $awayTeam, $league) {
            $leagueHint = $league ? " {$league}" : '';
            $queries = [
                "\"{$homeTeam}\" \"{$awayTeam}\" preview{$leagueHint}",
                "\"{$homeTeam}\" \"{$awayTeam}\" prediction team news lineups",
                "\"{$homeTeam}\" \"{$awayTeam}\" injury suspension confirmed",
                "\"{$homeTeam}\" \"{$awayTeam}\" referee odds analysis",
            ];

            $headlines = [];
            foreach ($queries as $query) {
                $headlines = array_merge($headlines, $this->fetchGoogleNews($query, needle: null, limit: 4));
            }

            return $this->dedup($headlines, 12);
        });
    }

    // ── Private helpers ─────────────────────────────────────────────

    /**
     * Fetch Google News RSS for any raw query string.
     * $needle: if provided, only items whose title contains this string are kept.
     */
    private function fetchGoogleNews(string $query, ?string $needle, int $limit): array
    {
        $url = 'https://news.google.com/rss/search?' . http_build_query([
            'q'    => $query,
            'hl'   => 'en-US',
            'gl'   => 'US',
            'ceid' => 'US:en',
        ]);

        return $this->parseRss($url, $needle, limit: $limit);
    }

    /** Deduplicate headlines and return as a single newline-joined string. */
    private function dedup(array $headlines, int $max): string
    {
        $seen   = [];
        $unique = [];
        foreach ($headlines as $h) {
            $key = strtolower(substr($h, 0, 60));
            if (! in_array($key, $seen, true)) {
                $seen[]   = $key;
                $unique[] = $h;
            }
        }
        return implode("\n", array_slice($unique, 0, $max));
    }

    /**
     * Pull from BBC Sport, Sky Sports, ESPN RSS and filter items that mention
     * the team by name.
     */
    private function rssFeeds(string $team): array
    {
        $headlines = [];

        foreach (self::RSS_SOURCES as $source => $url) {
            $cacheKey = "rss_feed_{$source}";

            $items = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($url) {
                return $this->parseRss($url, needle: null, limit: 50);
            });

            $teamLower = strtolower($team);

            foreach ($items as $headline) {
                if (str_contains(strtolower($headline), $teamLower)) {
                    $headlines[] = $headline;
                }
                if (count($headlines) >= 4) break;
            }
        }

        return $headlines;
    }

    /**
     * Fetch an RSS feed URL and return clean headline strings.
     * If $needle is provided, only returns items whose title contains it.
     */
    private function parseRss(string $url, ?string $needle, int $limit): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; TavsScore/1.0; +https://tavsscore.com)',
            ])->timeout(5)->get($url);

            if ($response->failed() || blank($response->body())) {
                return [];
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response->body());

            if (! $xml || ! isset($xml->channel->item)) {
                return [];
            }

            $results = [];

            foreach ($xml->channel->item as $item) {
                $title = html_entity_decode(
                    strip_tags((string) $item->title),
                    ENT_QUOTES | ENT_HTML5
                );

                // Remove " - Source Name" suffix Google appends
                $title = trim(preg_replace('/ - [^-]{3,40}$/', '', $title));

                if (blank($title)) continue;

                if ($needle && ! str_contains(strtolower($title), strtolower($needle))) {
                    continue;
                }

                $results[] = '• ' . $title;

                if (count($results) >= $limit) break;
            }

            return $results;

        } catch (\Throwable $e) {
            Log::debug('NewsService RSS parse failed', ['url' => $url, 'err' => $e->getMessage()]);
            return [];
        }
    }
}
