<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\FootballMatch;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

class SeoController extends Controller
{
    public function sitemap(Request $request): Response
    {
        $posts = BlogPost::published()->orderByDesc('published_at')->get();

        // Build sitemap URLs from the current public request instead of relying
        // on APP_URL. This keeps the sitemap correct even if an environment has
        // not yet been configured with the production application URL.
        $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');

        $staticPages = [
            ['path' => '/',                           'priority' => '1.0', 'freq' => 'daily'],
            ['path' => '/live',                       'priority' => '0.9', 'freq' => 'always'],
            ['path' => '/predictions',                'priority' => '0.9', 'freq' => 'daily'],
            ['path' => '/daily-football-predictions', 'priority' => '0.9', 'freq' => 'daily'],
            ['path' => '/picks',                      'priority' => '0.9', 'freq' => 'daily'],
            ['path' => '/draw-picks',                 'priority' => '0.8', 'freq' => 'daily'],
            ['path' => '/gg-picks',                   'priority' => '0.8', 'freq' => 'daily'],
            ['path' => '/over-1-5',                   'priority' => '0.8', 'freq' => 'daily'],
            ['path' => '/over-2-5',                   'priority' => '0.8', 'freq' => 'daily'],
            ['path' => '/team-3-plus',                'priority' => '0.8', 'freq' => 'daily'],
            ['path' => '/double-chance',              'priority' => '0.8', 'freq' => 'daily'],
            ['path' => '/lineup-picks',               'priority' => '0.8', 'freq' => 'daily'],
            ['path' => '/correct-score',              'priority' => '0.8', 'freq' => 'daily'],
            ['path' => '/goalscorer-picks',           'priority' => '0.8', 'freq' => 'daily'],
            ['path' => '/corners-picks',              'priority' => '0.8', 'freq' => 'daily'],
            ['path' => '/tennis',                     'priority' => '0.8', 'freq' => 'daily'],
            ['path' => '/fantasy',                    'priority' => '0.7', 'freq' => 'daily'],
            ['path' => '/stats',                      'priority' => '0.7', 'freq' => 'daily'],
            ['path' => '/standings',                  'priority' => '0.7', 'freq' => 'daily'],
            ['path' => '/top-scorers',                'priority' => '0.7', 'freq' => 'daily'],
            ['path' => '/results',                    'priority' => '0.7', 'freq' => 'daily'],
            ['path' => '/track-record',               'priority' => '0.7', 'freq' => 'monthly'],
            ['path' => '/rollover',                   'priority' => '0.7', 'freq' => 'daily'],
            ['path' => '/booking-codes',              'priority' => '0.6', 'freq' => 'daily'],
            ['path' => '/winners',                    'priority' => '0.6', 'freq' => 'weekly'],
            ['path' => '/hall-of-fame',               'priority' => '0.6', 'freq' => 'weekly'],
            ['path' => '/football-news',               'priority' => '0.8', 'freq' => 'daily'],
            ['path' => '/about',                      'priority' => '0.5', 'freq' => 'monthly'],
            ['path' => '/contact',                    'priority' => '0.5', 'freq' => 'monthly'],
            ['path' => '/privacy',                    'priority' => '0.3', 'freq' => 'yearly'],
            ['path' => '/terms',                      'priority' => '0.3', 'freq' => 'yearly'],
        ];

        $staticPages = array_map(
            fn (array $page): array => [...$page, 'url' => $baseUrl . $page['path']],
            $staticPages,
        );

        // Match-prediction pages from the last 14 days for fresh long-tail SEO
        $matchPages = FootballMatch::query()
            ->whereHas('prediction')
            ->where('match_time', '>=', now()->subDays(14))
            ->orderByDesc('match_time')
            ->limit(500)
            ->get();

        $xml = view('seo.sitemap', compact('posts', 'staticPages', 'matchPages', 'baseUrl'))->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    public function robots(): Response
    {
        $content = "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /api/\nSitemap: ".route('sitemap')."\n";

        return response($content, 200, ['Content-Type' => 'text/plain']);
    }
}
