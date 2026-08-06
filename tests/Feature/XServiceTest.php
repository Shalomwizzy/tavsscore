<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\XService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_credentials_never_hits_the_api(): void
    {
        config()->set('services.x', ['api_key' => null, 'api_secret' => null, 'access_token' => null, 'access_secret' => null]);
        Http::fake();

        app(XService::class)->postBookingCode('sportybet', 'ABC123', 'First 5 Minutes Draw', 'https://tavsscore.com', 12.5);

        Http::assertNothingSent();
    }

    public function test_admin_saved_credentials_post_a_tweet(): void
    {
        foreach (['x_api_key' => 'k', 'x_api_secret' => 's', 'x_access_token' => 't', 'x_access_secret' => 'ts'] as $key => $val) {
            Setting::set($key, Crypt::encryptString($val));
        }
        Http::fake(['api.x.com/*' => Http::response(['data' => ['id' => '1']], 201)]);

        Setting::set('telegram_url', 'https://t.me/tavsscore');

        app(XService::class)->postBookingOutcome('sportybet', 'ABC123', 'First 5 Minutes Draw', true, 'https://tavsscore.com', 12.5);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'api.x.com/2/tweets')) {
                return false;
            }
            $text = json_decode($request->body(), true)['text'] ?? '';

            return str_contains((string) $request->header('Authorization')[0], 'oauth_signature')
                && str_contains($text, 'More free predictions')
                && str_contains($text, 't.me/tavsscore');
        });
    }

    public function test_only_the_days_highest_odds_code_is_tweeted(): void
    {
        config(['services.booking_worker.token' => 'test-worker-token']);
        foreach (['x_api_key' => 'k', 'x_api_secret' => 's', 'x_access_token' => 't', 'x_access_secret' => 'ts'] as $key => $val) {
            Setting::set($key, Crypt::encryptString($val));
        }
        Http::fake(['api.x.com/*' => Http::response(['data' => ['id' => '1']], 201)]);
        $today = now('Africa/Lagos')->toDateString();

        $post = fn (string $code, float $odds) => $this->withHeader('X-Worker-Token', 'test-worker-token')
            ->postJson('/api/worker/booking-codes', [
                'platform' => 'sportybet', 'code' => $code, 'slip_ref' => 'daily-acca',
                'total_odds' => $odds, 'status' => 'published', 'pick_date' => $today,
            ])->assertCreated();

        $post('LOWODDS', 5.0);
        $post('HIGHODDS', 800.0);

        // Both arrivals are record-highs at their moment, so each tweets once; a
        // later lower-odds code must not tweet.
        $post('MIDODDS', 50.0);

        $this->assertNotNull(\App\Models\BookingCode::where('code', 'HIGHODDS')->value('x_posted_at'));
        $this->assertNull(\App\Models\BookingCode::where('code', 'MIDODDS')->value('x_posted_at'));

        $tweets = Http::recorded(fn ($request) => str_contains($request->url(), 'api.x.com/2/tweets'));
        $this->assertCount(2, $tweets);
    }
}
