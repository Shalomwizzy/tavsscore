<?php

namespace Tests\Unit;

use App\Services\Blog\BlogArticleWriter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlogArticleWriterTest extends TestCase
{
    public function test_it_accepts_any_configured_article_provider(): void
    {
        config([
            'services.groq.key' => null,
            'services.gemini.key' => 'gemini-test-key',
            'services.mistral.key' => null,
        ]);

        $this->assertTrue(app(BlogArticleWriter::class)->configured());
    }

    public function test_it_requires_at_least_one_article_provider(): void
    {
        config([
            'services.groq.key' => null,
            'services.gemini.key' => null,
            'services.mistral.key' => null,
        ]);

        $this->assertFalse(app(BlogArticleWriter::class)->configured());
    }

    public function test_it_falls_back_to_gemini_when_groq_is_rate_limited(): void
    {
        config([
            'services.groq.key' => 'groq-test-key',
            'services.gemini.key' => 'gemini-test-key',
            'services.mistral.key' => null,
        ]);
        Http::fake([
            'https://api.groq.com/*' => Http::response(['error' => ['message' => 'rate limited']], 429),
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '{"title":"Gemini football analysis","content":"<p>Original analysis.</p>"}']]],
                ]],
            ]),
        ]);

        $article = app(BlogArticleWriter::class)->writeArticle('System rules', 'Article briefing');

        $this->assertSame('Gemini football analysis', $article['title']);
        $this->assertSame('<p>Original analysis.</p>', $article['content']);
    }

    public function test_it_falls_back_to_mistral_when_gemini_fails(): void
    {
        config([
            'services.groq.key' => null,
            'services.gemini.key' => 'gemini-test-key',
            'services.mistral.key' => 'mistral-test-key',
        ]);
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([], 500),
            'https://api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => '{"title":"Mistral football analysis","content":"<p>Original analysis.</p>"}'],
                ]],
            ]),
        ]);

        $article = app(BlogArticleWriter::class)->writeArticle('System rules', 'Article briefing');

        $this->assertSame('Mistral football analysis', $article['title']);
        $this->assertSame('<p>Original analysis.</p>', $article['content']);
    }

    public function test_it_falls_back_when_a_provider_returns_malformed_article_json(): void
    {
        config([
            'services.groq.key' => null,
            'services.gemini.key' => 'gemini-test-key',
            'services.mistral.key' => 'mistral-test-key',
        ]);
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '{"title":["invalid"],"content":"<p>Bad shape.</p>"}']]],
                ]],
            ]),
            'https://api.mistral.ai/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => '{"title":"Recovered article","content":"<p>Original analysis.</p>"}'],
                ]],
            ]),
        ]);

        $article = app(BlogArticleWriter::class)->writeArticle('System rules', 'Article briefing');

        $this->assertSame('Recovered article', $article['title']);
    }
}
