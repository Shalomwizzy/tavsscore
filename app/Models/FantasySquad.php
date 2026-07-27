<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasySquad extends Model
{
    protected $fillable = [
        'league_id', 'season', 'gameweek', 'formation', 'budget_used',
        'total_points', 'captain', 'vice_captain',
        'starting_xi', 'bench', 'transfers_in', 'built_at',
    ];

    protected $casts = [
        'league_id'    => 'integer',
        'season'       => 'integer',
        'budget_used'  => 'float',
        'total_points' => 'integer',
        'starting_xi'  => 'array',
        'bench'        => 'array',
        'transfers_in' => 'array',
        'built_at'     => 'datetime',
    ];
}
