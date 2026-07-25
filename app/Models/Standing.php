<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Standing extends Model
{
    protected $fillable = [
        'league_id', 'season', 'team_api_id', 'team_name', 'team_logo',
        'rank', 'group_label', 'points', 'goals_diff', 'form', 'status_desc',
        'played', 'win', 'draw', 'lose', 'goals_for', 'goals_against',
    ];

    protected $casts = [
        'league_id'     => 'integer',
        'season'        => 'integer',
        'team_api_id'   => 'integer',
        'rank'          => 'integer',
        'points'        => 'integer',
        'goals_diff'    => 'integer',
        'played'        => 'integer',
        'win'           => 'integer',
        'draw'          => 'integer',
        'lose'          => 'integer',
        'goals_for'     => 'integer',
        'goals_against' => 'integer',
    ];
}
