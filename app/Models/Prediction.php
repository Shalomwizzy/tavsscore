<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prediction extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'match_id',
        'home_win_prob',
        'draw_prob',
        'away_win_prob',
        'over_15_prob',
        'over_25_prob',
        'over_35_prob',
        'btts_prob',
        'predicted_outcome',
        'tips',
        'confidence',
        'analysis',
        'analysis_pidgin',
        'analysis_swahili',
        'is_daily_pick',
        'pick_rank',
        'was_correct',
    ];

    protected $casts = [
        'home_win_prob'  => 'decimal:2',
        'draw_prob'      => 'decimal:2',
        'away_win_prob'  => 'decimal:2',
        'over_15_prob'   => 'decimal:2',
        'over_25_prob'   => 'decimal:2',
        'over_35_prob'   => 'decimal:2',
        'btts_prob'      => 'decimal:2',
        'tips'           => 'array',
        'confidence'     => 'integer',
        'is_daily_pick'  => 'boolean',
        'pick_rank'      => 'integer',
        'was_correct'    => 'boolean',
        'created_at'     => 'datetime',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }
}
