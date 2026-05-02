<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    protected $fillable = [
        'email', 'confirm_token', 'confirmed_at',
        'unsubscribe_token', 'unsubscribed_at',
        'last_sent_at', 'source', 'ip_hash',
    ];

    protected $casts = [
        'confirmed_at'     => 'datetime',
        'unsubscribed_at'  => 'datetime',
        'last_sent_at'     => 'datetime',
    ];

    public static function freshTokens(): array
    {
        return [
            'confirm_token'     => Str::random(48),
            'unsubscribe_token' => Str::random(48),
        ];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNotNull('confirmed_at')->whereNull('unsubscribed_at');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->whereNull('confirmed_at')->whereNull('unsubscribed_at');
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null && $this->unsubscribed_at === null;
    }
}
