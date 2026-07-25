<?php

namespace App\Services\ApiFootball;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin, quota-aware wrapper around API-Football v3. Mirrors the back-off
 * behaviour in FootballService so batch stat fetchers stop hammering the API
 * the moment the daily limit is hit, and short-circuit for the rest of the day.
 */
class Client
{
    public const QUOTA_FLAG = 'api_football_quota_exhausted';

    /**
     * GET an endpoint and return ['response' => array, 'paging' => array].
     * Returns an empty response (not an exception) when quota is exhausted.
     */
    public function get(string $endpoint, array $query = []): array
    {
        $empty = ['response' => [], 'paging' => ['current' => 1, 'total' => 1]];

        if ($this->quotaExhausted()) {
            return $empty;
        }

        $apiKey  = config('services.football.key');
        $baseUrl = rtrim((string) config('services.football.url'), '/');

        if (blank($apiKey)) {
            throw new RuntimeException('API-Football key is not configured.');
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withHeaders(['x-apisports-key' => $apiKey])
                ->acceptJson()
                ->timeout(20)
                ->retry(2, 300)
                ->get('/'.ltrim($endpoint, '/'), $query);
        } catch (ConnectionException $e) {
            Log::error('API-Football connection failed.', [
                'endpoint' => $endpoint, 'query' => $query, 'message' => $e->getMessage(),
            ]);
            throw new RuntimeException('Unable to connect to API-Football.', previous: $e);
        }

        if ($response->failed()) {
            Log::error('API-Football request failed.', [
                'endpoint' => $endpoint, 'status' => $response->status(), 'query' => $query,
            ]);
            throw new RuntimeException('API-Football returned an unsuccessful response.');
        }

        $payload = $response->json();

        if (! empty($payload['errors'])) {
            $errors = $payload['errors'];
            $isQuota = is_array($errors) && (
                isset($errors['requests']) || isset($errors['rateLimit'])
                || (is_string($errors[0] ?? null) && stripos($errors[0], 'limit') !== false)
            );

            if ($isQuota) {
                Log::info('API-Football rate-limited — backing off.', ['errors' => $errors]);
                Cache::put(self::QUOTA_FLAG, true, now()->addHours(2));
                return $empty;
            }

            Log::warning('API-Football returned errors.', [
                'endpoint' => $endpoint, 'query' => $query, 'errors' => $errors,
            ]);
            throw new RuntimeException('API-Football returned errors for the request.');
        }

        return [
            'response' => $payload['response'] ?? [],
            'paging'   => $payload['paging'] ?? ['current' => 1, 'total' => 1],
        ];
    }

    public function quotaExhausted(): bool
    {
        return (bool) Cache::get(self::QUOTA_FLAG);
    }
}
