<?php

namespace Tests\Unit;

use App\Services\BookingOutcomeCardService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingOutcomeCardServiceTest extends TestCase
{
    public function test_it_renders_a_branded_winning_booking_result_card(): void
    {
        Storage::fake('public');

        $path = app(BookingOutcomeCardService::class)->render(
            'SportyBet',
            'L6FSHG',
            'Under 3.5 Goals • Double Chance • Over 1.5 Goals',
            true,
            5.87,
        );

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith("\xFF\xD8\xFF", Storage::disk('public')->get($path));
    }
}
