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

        app(XService::class)->postBookingOutcome('sportybet', 'ABC123', 'First 5 Minutes Draw', true, 'https://tavsscore.com', 12.5);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.x.com/2/tweets')
            && str_contains((string) $request->header('Authorization')[0], 'oauth_signature'));
    }
}
