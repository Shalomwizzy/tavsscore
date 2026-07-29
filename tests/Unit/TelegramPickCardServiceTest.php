<?php

namespace Tests\Unit;

use App\Services\TelegramPickCardService;
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
}
