<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RolloverChallenge extends Model
{
    protected $fillable = ['started_at', 'initial_stake', 'status'];

    protected $casts = [
        'started_at'    => 'date',
        'initial_stake' => 'decimal:2',
    ];

    public function picks(): HasMany
    {
        return $this->hasMany(RolloverPick::class, 'challenge_id');
    }

    public function currentBalance(): float
    {
        $lastWon = $this->picks()->where('status', 'won')->orderByDesc('day_number')->first();
        if ($lastWon) {
            return (float) $lastWon->potential_return;
        }
        return (float) $this->initial_stake;
    }

    public function currentDay(): int
    {
        return min(10, $this->picks()->count() + 1);
    }

    public function projectedFinal(float $avgOdds = 1.20): float
    {
        $remaining = 10 - $this->picks()->where('status', 'won')->count();
        return round($this->currentBalance() * pow($avgOdds, $remaining), 2);
    }
}
