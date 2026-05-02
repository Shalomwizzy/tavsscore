<?php

namespace App\Http\Middleware;

use App\Models\RequestLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Tail-end logger that records aggregate request data for the admin analytics
 * page. We anonymise IPs by hashing them with the app key and never store
 * the raw IP — same level as the privacy policy promises.
 */
class LogRequest
{
    /** Paths we never log (assets, healthchecks, internal) */
    private const IGNORED_PREFIXES = ['/api/', '/admin', '/sanctum', '/livewire', '/storage/', '/build/'];

    private const IGNORED_EXACT = ['/sitemap.xml', '/robots.txt', '/favicon.ico'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($this->shouldLog($request)) {
                $this->record($request, $response);
            }
        } catch (Throwable) {
            // Logging must never break a real request
        }

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        if (! $request->isMethod('GET')) return false;

        $path = '/' . ltrim($request->path(), '/');
        if (in_array($path, self::IGNORED_EXACT, true)) return false;
        foreach (self::IGNORED_PREFIXES as $p) {
            if (str_starts_with($path, $p)) return false;
        }

        $accept = (string) $request->header('accept', '');
        if (str_contains($accept, 'image/') || str_contains($accept, 'font/')) return false;

        return true;
    }

    private function record(Request $request, Response $response): void
    {
        $ip   = $request->ip() ?? '';
        $hash = $ip ? hash('sha256', config('app.key') . '|' . $ip) : null;
        $ua   = (string) $request->header('User-Agent', '');

        RequestLog::create([
            'path'        => mb_substr('/' . ltrim($request->path(), '/'), 0, 500),
            'method'      => $request->method(),
            'status_code' => $response->getStatusCode(),
            'ip_hash'     => $hash ? mb_substr($hash, 0, 64) : null,
            'country'     => mb_substr((string) $request->header('CF-IPCountry', $request->header('X-Country-Code', '')), 0, 2) ?: null,
            'user_agent'  => mb_substr($ua, 0, 500),
            'referer'     => mb_substr((string) $request->header('referer', ''), 0, 500) ?: null,
            'is_bot'      => $this->looksLikeBot($ua),
            'created_at'  => now(),
        ]);
    }

    private function looksLikeBot(string $ua): bool
    {
        if ($ua === '') return true;
        $needle = strtolower($ua);
        foreach (['bot', 'crawler', 'spider', 'scrape', 'curl/', 'wget/', 'python-requests', 'go-http', 'headlesschrome'] as $kw) {
            if (str_contains($needle, $kw)) return true;
        }
        return false;
    }
}
