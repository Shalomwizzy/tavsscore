<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneSignalService
{
    public function sendGoalAlert(
        string $homeTeam,
        string $awayTeam,
        int    $homeScore,
        int    $awayScore,
        string $league,
        ?int   $elapsed
    ): void {
        $appId  = config('services.onesignal.app_id');
        $apiKey = config('services.onesignal.rest_api_key');

        if (! $appId || ! $apiKey) {
            Log::warning('OneSignal not configured — skipping goal alert.');
            return;
        }

        $minute = $elapsed ? " · {$elapsed}'" : '';

        $response = Http::withHeaders([
            'Authorization' => "Basic {$apiKey}",
            'Content-Type'  => 'application/json',
        ])->post('https://onesignal.com/api/v1/notifications', [
            'app_id'             => $appId,
            'included_segments'  => ['All'],
            'headings'           => ['en' => "⚽ GOAL! {$homeTeam} {$homeScore} - {$awayScore} {$awayTeam}"],
            'contents'           => ['en' => "{$league}{$minute}"],
            'url'                => config('app.url') . '/live',
            'web_push_topic'     => 'goal-alert',
        ]);

        if (! $response->successful()) {
            Log::error('OneSignal goal alert failed.', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }
    }

    public function sendPickOutcome(string $title, string $body, string $path = '/picks'): void
    {
        $this->sendMatchAlert($title, $body, $path);
    }

    public function sendMatchAlert(string $title, string $message, string $path = '/live'): void
    {
        $appId  = config('services.onesignal.app_id');
        $apiKey = config('services.onesignal.rest_api_key');

        if (! $appId || ! $apiKey) {
            return;
        }

        Http::withHeaders([
            'Authorization' => "Basic {$apiKey}",
            'Content-Type'  => 'application/json',
        ])->post('https://onesignal.com/api/v1/notifications', [
            'app_id'            => $appId,
            'included_segments' => ['All'],
            'headings'          => ['en' => $title],
            'contents'          => ['en' => $message],
            'url'               => config('app.url') . $path,
        ]);
    }
}
