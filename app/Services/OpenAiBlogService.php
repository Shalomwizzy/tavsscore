<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Small, purpose-built OpenAI client for the automated newsroom. Keeping this
 * separate from the command makes the provider easy to change or test later.
 */
class OpenAiBlogService
{
    public function configured(): bool
    {
        $key = config('services.openai.key');

        return filled($key) && $key !== 'your_openai_api_key_here';
    }

    /** @return array{title: string, content: string} */
    public function writeArticle(string $systemPrompt, string $userPrompt): array
    {
        $response = $this->request('chat/completions', [
            'model' => config('services.openai.text_model'),
            'temperature' => 0.65,
            'max_tokens' => 1400,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        $raw = trim((string) data_get($response, 'choices.0.message.content'));
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```\s*$/', '', $raw);
        $article = json_decode($raw, true);

        if (! is_array($article) || blank($article['title'] ?? null) || blank($article['content'] ?? null)) {
            throw new RuntimeException('OpenAI returned an invalid article response.');
        }

        return ['title' => trim($article['title']), 'content' => trim($article['content'])];
    }

    /** Returns the generated image bytes, or throws when generation fails. */
    public function generateImage(string $prompt): string
    {
        $response = $this->request('images/generations', [
            'model' => config('services.openai.image_model'),
            'prompt' => $prompt,
            'size' => config('services.openai.image_size'),
            'quality' => config('services.openai.image_quality'),
            'output_format' => 'png',
            'n' => 1,
        ], 120);

        $encoded = data_get($response, 'data.0.b64_json');
        $image = is_string($encoded) ? base64_decode($encoded, true) : false;

        if ($image === false || $image === '') {
            throw new RuntimeException('OpenAI did not return image data.');
        }

        return $image;
    }

    private function request(string $path, array $payload, int $timeout = 60): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $response = Http::withToken(config('services.openai.key'))
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->retry(2, 1000)
            ->post(rtrim(config('services.openai.url'), '/') . '/' . $path, $payload);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI API error: status ' . $response->status());
        }

        return $response->json();
    }
}
