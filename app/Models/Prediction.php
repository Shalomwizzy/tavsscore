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
        'analysis_french',
        'likely_scores',
        'correct_score_notified',
        'is_daily_pick',
        'pick_rank',
        'is_draw_pick',
        'draw_rank',
        'is_gg_pick',
        'gg_rank',
        'is_over15_pick',
        'over15_rank',
        'over15_notified',
        'is_over25_pick',
        'over25_rank',
        'over25_notified',
        'is_team3plus_pick',
        'team3plus_rank',
        'team3plus_label',
        'team3plus_notified',
        'home_3plus_prob',
        'away_3plus_prob',
        'was_correct',
        'has_lineup',
        'opening_odds',
        'closing_odds',
    ];

    protected $casts = [
        'home_win_prob'           => 'decimal:2',
        'draw_prob'               => 'decimal:2',
        'away_win_prob'           => 'decimal:2',
        'over_15_prob'            => 'decimal:2',
        'over_25_prob'            => 'decimal:2',
        'over_35_prob'            => 'decimal:2',
        'btts_prob'               => 'decimal:2',
        'tips'                    => 'array',
        'confidence'              => 'integer',
        'correct_score_notified'  => 'boolean',
        'is_daily_pick'           => 'boolean',
        'pick_rank'               => 'integer',
        'is_draw_pick'            => 'boolean',
        'draw_rank'               => 'integer',
        'is_gg_pick'              => 'boolean',
        'gg_rank'                 => 'integer',
        'is_over15_pick'          => 'boolean',
        'over15_rank'             => 'integer',
        'over15_notified'         => 'boolean',
        'is_over25_pick'          => 'boolean',
        'over25_rank'             => 'integer',
        'over25_notified'         => 'boolean',
        'is_team3plus_pick'       => 'boolean',
        'team3plus_rank'          => 'integer',
        'team3plus_notified'      => 'boolean',
        'home_3plus_prob'         => 'decimal:2',
        'away_3plus_prob'         => 'decimal:2',
        'was_correct'             => 'boolean',
        'has_lineup'              => 'boolean',
        'likely_scores'           => 'array',
        'opening_odds'            => 'array',
        'closing_odds'            => 'array',
        'created_at'              => 'datetime',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }
}
