<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCodeLeg extends Model
{
    protected $fillable = [
        'booking_code_id', 'match_id', 'source_key', 'home_team', 'away_team',
        'market', 'model_probability', 'estimated_odds', 'status',
        'home_score', 'away_score', 'settled_at',
    ];

    protected $casts = [
        'model_probability' => 'float',
        'estimated_odds' => 'float',
        'settled_at' => 'datetime',
    ];

    public function bookingCode(): BelongsTo
    {
        return $this->belongsTo(BookingCode::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }
}
