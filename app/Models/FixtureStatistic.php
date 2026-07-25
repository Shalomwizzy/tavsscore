<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixtureStatistic extends Model
{
    protected $fillable = [
        'match_id', 'team_api_id', 'team_name',
        'shots_total', 'shots_on', 'shots_off', 'possession', 'corners',
        'offsides', 'fouls', 'yellow_cards', 'red_cards', 'saves',
        'passes_total', 'passes_accurate', 'expected_goals', 'raw',
    ];

    protected $casts = [
        'match_id'        => 'integer',
        'team_api_id'     => 'integer',
        'expected_goals'  => 'float',
        'raw'             => 'array',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }
}
