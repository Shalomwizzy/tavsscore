<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiPrediction extends Model
{
    protected $fillable = [
        'match_id', 'winner_name', 'winner_comment', 'advice',
        'percent_home', 'percent_draw', 'percent_away',
        'under_over', 'goals_home', 'goals_away', 'raw',
    ];

    protected $casts = [
        'match_id'   => 'integer',
        'goals_home' => 'float',
        'goals_away' => 'float',
        'raw'        => 'array',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }
}
