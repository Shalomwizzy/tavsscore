<?php

namespace App\Console\Commands;

use App\Models\BookingCode;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class ResendBookingCodes extends Command
{
    protected $signature = 'booking:resend {--date= : Lagos date (YYYY-MM-DD), defaults to today}';
    protected $description = 'Re-send active booking codes to Telegram without creating new codes.';

    public function handle(TelegramService $telegram): int
    {
        $date = $this->option('date') ?: now('Africa/Lagos')->toDateString();
        $codes = BookingCode::query()->where('status', 'published')->where('total_odds', '>=', 2)
            ->whereDate('pick_date', $date)->orderBy('id')->get();
        foreach ($codes as $code) {
            $telegram->sendBookingCode($code->platform, strtoupper($code->code), (string) ($code->note ?? ''), config('app.url'), ticketUrl: $code->link);
        }
        $this->info("Re-sent {$codes->count()} booking code(s) for {$date}.");
        return self::SUCCESS;
    }
}
