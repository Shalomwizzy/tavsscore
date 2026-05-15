<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WinnerSubmission extends Model
{
    protected $fillable = [
        'username',
        'email',
        'screenshot_path',
        'pick_description',
        'winning_amount',
        'currency',
        'platform',
        'match_details',
        'is_approved',
    ];

    protected $casts = [
        'is_approved'    => 'boolean',
        'winning_amount' => 'decimal:2',
    ];

    public function getScreenshotUrlAttribute(): string
    {
        return '/' . ltrim($this->getPaths()[0], '/');
    }

    public function getScreenshotUrlsAttribute(): array
    {
        return array_map(fn ($p) => '/' . ltrim($p, '/'), $this->getPaths());
    }

    private function getPaths(): array
    {
        $raw = $this->screenshot_path ?? '';
        if (str_starts_with(ltrim($raw), '[')) {
            return json_decode($raw, true) ?? [$raw];
        }
        return [$raw];
    }

    public function scopeApproved(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where('is_approved', true);
    }
}
