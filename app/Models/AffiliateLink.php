<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AffiliateLink extends Model
{
    protected $fillable = [
        'platform',
        'slug',
        'register_url',
        'website_url',
        'promo_text',
        'color',
        'logo_emoji',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
