<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Posts booking codes + their win/lose outcome to X (Twitter), mirroring the
 * TelegramService booking flow. Uses OAuth 1.0a user-context signing so a single
 * bot account posts with non-expiring tokens. No-ops when credentials are absent,
 * so the pipeline never breaks if X is not configured.
 */
class XService
{
    private const TWEET_URL = 'https://api.x.com/2/tweets';
    private const UPLOAD_URL = 'https://upload.x.com/1.1/media/upload.json';

    public function isConfigured(): bool
    {
        $c = $this->creds();

        return filled($c['api_key'])
            && filled($c['api_secret'])
            && filled($c['access_token'])
            && filled($c['access_secret']);
    }

    /**
     * Credentials, preferring the admin-managed (encrypted) Settings over env.
     * Admins add the X account at /admin/settings; env stays a deploy fallback.
     */
    private function creds(): array
    {
        return [
            'api_key'       => $this->setting('x_api_key') ?: config('services.x.api_key'),
            'api_secret'    => $this->setting('x_api_secret') ?: config('services.x.api_secret'),
            'access_token'  => $this->setting('x_access_token') ?: config('services.x.access_token'),
            'access_secret' => $this->setting('x_access_secret') ?: config('services.x.access_secret'),
        ];
    }

    private function setting(string $key): ?string
    {
        $raw = Setting::get($key);
        if (blank($raw)) {
            return null;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return $raw; // tolerate a value that was stored unencrypted
        }
    }

    public function postBookingCode(string $platform, string $code, string $note, string $siteUrl, ?float $totalOdds = null, ?string $ticketImagePath = null): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $odds = $totalOdds ? "\n📈 Odds: ".number_format($totalOdds, 2) : '';
        $picks = $note ? "\n📋 {$note}" : '';
        $text = '🎟️ '.strtoupper($platform)." BOOKING CODE\n"
            ."🔥 {$code} 🔥"
            .$odds
            .$picks
            ."\n\n⚠️ Verify odds before placing. 18+\n"
            .'👉 '.rtrim($siteUrl, '/')."/booking-codes\n"
            .'#'.strtolower($platform).' #bookingcode #freebettingtips';

        $this->tweet($text, $ticketImagePath);
    }

    public function postBookingOutcome(string $platform, string $code, string $note, bool $won, string $siteUrl, ?float $totalOdds = null, ?string $ticketImagePath = null): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $head = $won ? '✅ BOOKING CODE WON' : '❌ BOOKING CODE LOST';
        $odds = $totalOdds ? "\n📈 Odds: ".number_format($totalOdds, 2) : '';
        $picks = $note ? "\n📋 {$note}" : '';
        $text = $head."\n"
            ."🔥 {$code} 🔥"
            .$odds
            .$picks
            ."\n\n👉 ".rtrim($siteUrl, '/')."/booking-codes\n"
            .'#'.strtolower($platform).($won ? ' #winning #freebettingtips' : ' #freebettingtips');

        $this->tweet($text, $ticketImagePath);
    }

    /** Post a tweet, attaching the ticket image when one is available (best effort). */
    private function tweet(string $text, ?string $imagePath = null): void
    {
        try {
            $payload = ['text' => $text];

            $mediaId = $imagePath ? $this->uploadMedia($imagePath) : null;
            if ($mediaId) {
                $payload['media'] = ['media_ids' => [$mediaId]];
            }

            $auth = $this->authHeader('POST', self::TWEET_URL, []);
            $res = Http::withHeaders(['Authorization' => $auth])
                ->withBody(json_encode($payload), 'application/json')
                ->post(self::TWEET_URL);

            if ($res->failed()) {
                report(new \RuntimeException('X tweet failed: '.$res->status().' '.$res->body()));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Upload an image via the v1.1 media endpoint; returns media_id_string or null. */
    private function uploadMedia(string $path): ?string
    {
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        try {
            $binary = Storage::disk('public')->get($path);
            // Multipart bodies are excluded from the OAuth signature base string.
            $auth = $this->authHeader('POST', self::UPLOAD_URL, []);
            $res = Http::withHeaders(['Authorization' => $auth])
                ->attach('media', $binary, 'ticket.jpg')
                ->post(self::UPLOAD_URL);

            return $res->successful() ? ($res->json('media_id_string') ?: null) : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Build an OAuth 1.0a Authorization header for a request. */
    private function authHeader(string $method, string $url, array $queryParams): string
    {
        $c = $this->creds();

        $oauth = [
            'oauth_consumer_key' => $c['api_key'],
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_token' => $c['access_token'],
            'oauth_version' => '1.0',
        ];

        // Signature base includes oauth params + any query params (never the body).
        $signParams = array_merge($oauth, $queryParams);
        ksort($signParams);
        $paramString = http_build_query($signParams, '', '&', PHP_QUERY_RFC3986);

        $base = strtoupper($method).'&'.rawurlencode($url).'&'.rawurlencode($paramString);
        $key = rawurlencode($c['api_secret']).'&'.rawurlencode($c['access_secret']);
        $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $base, $key, true));

        ksort($oauth);
        $parts = [];
        foreach ($oauth as $k => $v) {
            $parts[] = rawurlencode($k).'="'.rawurlencode($v).'"';
        }

        return 'OAuth '.implode(', ', $parts);
    }
}
