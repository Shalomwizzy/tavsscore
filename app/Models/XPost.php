<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XPost extends Model
{
    protected $fillable = ['kind', 'text', 'tweet_id', 'status', 'error'];

    public const KINDS = [
        'booking_code'    => '🎟️ Booking code',
        'booking_outcome' => '✅ Booking result',
        'growth'          => '📣 Football post',
        'manual'          => '✍️ Manual',
    ];

    public function label(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
