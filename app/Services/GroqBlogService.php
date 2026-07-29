<?php

namespace App\Services;

use App\Services\Blog\GroqRateLimitException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Writes TavsScore newsroom articles with the configured Groq model. */
class GroqBlogService
{
    public function configured(): bool
    {
        $key = config('services.groq.key');

        return filled($key) && $key !== 'your_api_key_here';
    }

    /** @return array{title: string, content: string} */
    public function writeArticle(string $systemPrompt, string $userPrompt): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('GROQ_API_KEY is not configured.');
        }

        $response = Http::withToken(config('services.groq.key'))
            ->acceptJson()
            ->asJson()
            ->timeout(90)
            ->retry(2, 1000)
            ->post(config('services.groq.url'), [
                'model' => config('services.groq.model'),
                'temperature' => 0.45,
                'max_tokens' => 2600,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if ($response->status() === 429) {
            $retryAfter = max(60, (int) ($response->header('Retry-After') ?: 60));

            throw new GroqRateLimitException($retryAfter);
        }

        if ($response->failed()) {
            throw new RuntimeException('Groq API error: status ' . $response->status());
        }

        $raw = trim((string) data_get($response->json(), 'choices.0.message.content'));
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```\s*$/', '', $raw);
        $article = json_decode($raw, true);

        if (! is_array($article) || blank($article['title'] ?? null) || blank($article['content'] ?? null)) {
            throw new RuntimeException('Groq returned an invalid article response.');
        }

        return ['title' => trim($article['title']), 'content' => trim($article['content'])];
    }
}
