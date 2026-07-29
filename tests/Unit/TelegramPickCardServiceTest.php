<?php

namespace Tests\Unit;

use App\Services\TelegramPickCardService;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TelegramPickCardServiceTest extends TestCase
{
    public function test_it_renders_a_complete_jpeg_pick_card_from_real_pick_data(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is required for Telegram pick-card rendering.');
        }

        Storage::fake('public');
        $path = app(TelegramPickCardService::class)->render('Over 2.5 Goals', [
            ['match' => 'Manchester United vs Chelsea', 'league' => 'Premier League', 'tip' => 'Over 2.5 Goals', 'prob' => 82.4],
            ['match' => 'Arsenal vs Liverpool', 'league' => 'Premier League', 'tip' => 'Over 2.5 Goals', 'prob' => 78],
        ]);

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith("\xFF\xD8\xFF", Storage::disk('public')->get($path));
    }

    public function test_it_falls_back_to_a_native_gd_font_when_no_ttf_font_is_available(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is required for Telegram pick-card rendering.');
        }

        Storage::fake('public');
        config()->set('services.telegram.card_native_only', true);

        $path = app(TelegramPickCardService::class)->render('Tennis Picks', [[
            'match' => 'Player One vs Player Two',
            'tip' => 'Player One to win',
            'confidence' => 72,
            'league' => 'Test Open',
        ]]);

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith("\xFF\xD8\xFF", Storage::disk('public')->get($path));
    }

    public function test_prediction_photo_caption_keeps_the_readable_pick_list(): void
    {
        $method = new \ReflectionMethod(TelegramService::class, 'predictionCardCaption');
        $caption = $method->invoke(app(TelegramService::class), 'Over 2.5 Goals', [[
            'match' => 'Manchester United vs Chelsea',
            'league' => 'Premier League',
            'tip' => 'Over 2.5 Goals',
            'prob' => 82,
        ]]);

        $this->assertStringContainsString('Manchester United vs Chelsea', $caption);
        $this->assertStringContainsString('Over 2.5 Goals', $caption);
        $this->assertStringContainsString('82%', $caption);
        $this->assertStringContainsString('Play responsibly', $caption);
        $this->assertLessThanOrEqual(1024, mb_strlen($caption));
    }
}
