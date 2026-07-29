<?php

namespace App\Services\Blog;

use App\Services\GeminiService;
use App\Services\GroqBlogService;
use App\Services\MistralService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Resilient article-writing chain. A provider failure must never lower the
 * editorial standard, it only selects the next configured provider.
 */
class BlogArticleWriter
{
    public function __construct(
        private readonly GroqBlogService $groq,
        private readonly GeminiService $gemini,
        private readonly MistralService $mistral,
    ) {
    }

    public function configured(): bool
    {
        return $this->groq->configured()
            || $this->gemini->isConfigured()
            || $this->mistral->isConfigured();
    }

    /** @return array{title:string, content:string} */
    public function writeArticle(string $systemPrompt, string $userPrompt): array
    {
        $providers = [
            'Groq' => [$this->groq, 'configured'],
            'Gemini' => [$this->gemini, 'isConfigured'],
            'Mistral' => [$this->mistral, 'isConfigured'],
        ];
        $lastError = null;
        $rateLimit = null;

        foreach ($providers as $name => [$provider, $configuredMethod]) {
            if (! $provider->{$configuredMethod}()) {
                continue;
            }

            try {
                $article = $provider->writeArticle($systemPrompt, $userPrompt);
                Log::info('Blog article generated', ['provider' => $name]);

                return $article;
            } catch (GroqRateLimitException $e) {
                $rateLimit = $e;
                $lastError = $e;
                Log::warning('Blog provider rate limited; trying fallback', ['provider' => $name]);
            } catch (Throwable $e) {
                $lastError = $e;
                Log::warning('Blog provider failed; trying fallback', [
                    'provider' => $name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($rateLimit) {
            throw $rateLimit;
        }

        throw new RuntimeException(
            'Every configured blog-writing provider failed to generate the article.' . ($lastError ? ' Last error: ' . $lastError->getMessage() : '')
        );
    }
}
