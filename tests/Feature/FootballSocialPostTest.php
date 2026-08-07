<?php

namespace Tests\Feature;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Models\Setting;
use App\Services\FootballSocialComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FootballSocialPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_composes_a_prediction_teaser_from_real_data(): void
    {
        $match = FootballMatch::create([
            'api_id' => random_int(100000, 999999), 'home_team' => 'Arsenal', 'away_team' => 'Chelsea',
            'league' => 'Premier League', 'match_time' => now('Africa/Lagos')->addHours(3),
            'status' => 'NS',
        ]);
        Prediction::create([
            'match_id' => $match->id, 'predicted_outcome' => 'Home',
            'confidence' => 78, 'tips' => [['market' => 'Arsenal to win']], 'home_win_prob' => 55, 'draw_prob' => 25, 'away_win_prob' => 20, 'analysis' => 'Strong home form.',
        ]);

        $text = app(FootballSocialComposer::class)->compose();

        $this->assertNotNull($text);
        $this->assertStringContainsString('Free AI predictions', $text);
    }

    public function test_command_posts_to_x_when_configured(): void
    {
        $match = FootballMatch::create([
            'api_id' => random_int(100000, 999999), 'home_team' => 'Arsenal', 'away_team' => 'Chelsea',
            'league' => 'Premier League', 'match_time' => now('Africa/Lagos')->addHours(3),
            'status' => 'NS',
        ]);
        Prediction::create([
            'match_id' => $match->id, 'predicted_outcome' => 'Home',
            'confidence' => 78, 'tips' => [['market' => 'Arsenal to win']], 'home_win_prob' => 55, 'draw_prob' => 25, 'away_win_prob' => 20, 'analysis' => 'Strong home form.',
        ]);
        foreach (['x_api_key' => 'k', 'x_api_secret' => 's', 'x_access_token' => 't', 'x_access_secret' => 'ts'] as $key => $val) {
            Setting::set($key, Crypt::encryptString($val));
        }
        Http::fake(['api.x.com/*' => Http::response(['data' => ['id' => '1']], 201)]);

        $this->artisan('x:post-football')->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.x.com/2/tweets'));
    }

    public function test_command_no_ops_without_credentials(): void
    {
        config()->set('services.x', ['api_key' => null, 'api_secret' => null, 'access_token' => null, 'access_secret' => null]);
        Http::fake();

        $this->artisan('x:post-football')->assertSuccessful();

        Http::assertNothingSent();
    }
}
