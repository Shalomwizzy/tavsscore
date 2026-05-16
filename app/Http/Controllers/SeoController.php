<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\FootballMatch;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $posts = BlogPost::published()->orderByDesc('published_at')->get();

        $staticPages = [
            ['url' => route('home.index'),         'priority' => '1.0', 'freq' => 'daily'],
            ['url' => route('live.index'),          'priority' => '0.9', 'freq' => 'always'],
            ['url' => route('predictions.index'),   'priority' => '0.9', 'freq' => 'daily'],
            ['url' => route('picks.index'),         'priority' => '0.9', 'freq' => 'daily'],
            ['url' => route('draw-picks.index'),    'priority' => '0.9', 'freq' => 'daily'],
            ['url' => route('gg-picks.index'),      'priority' => '0.9', 'freq' => 'daily'],
            ['url' => route('over15-picks.index'),  'priority' => '0.9', 'freq' => 'daily'],
            ['url' => route('over25-picks.index'),  'priority' => '0.9', 'freq' => 'daily'],
            ['url' => route('team3plus-picks.index'),'priority' => '0.9', 'freq' => 'daily'],
            ['url' => route('stats.index'),         'priority' => '0.7', 'freq' => 'daily'],
            ['url' => route('track-record.index'),  'priority' => '0.8', 'freq' => 'monthly'],
            ['url' => route('results.index'),       'priority' => '0.7', 'freq' => 'daily'],
            ['url' => route('africa.index'),        'priority' => '0.8', 'freq' => 'daily'],
            ['url' => route('blog.index'),          'priority' => '0.8', 'freq' => 'daily'],
            ['url' => route('about'),               'priority' => '0.5', 'freq' => 'monthly'],
            ['url' => route('privacy'),             'priority' => '0.3', 'freq' => 'yearly'],
            ['url' => route('terms'),               'priority' => '0.3', 'freq' => 'yearly'],
        ];

        // Match-prediction pages from the last 14 days for fresh long-tail SEO
        $matchPages = FootballMatch::query()
            ->whereHas('prediction')
            ->where('match_time', '>=', now()->subDays(14))
            ->orderByDesc('match_time')
            ->limit(500)
            ->get();

        $xml = view('seo.sitemap', compact('posts', 'staticPages', 'matchPages'))->render();

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
