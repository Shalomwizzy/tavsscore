<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamStatistic extends Model
{
    protected $fillable = [
        'league_id', 'season', 'team_api_id', 'team_name', 'team_logo', 'form',
        'played_total', 'played_home', 'played_away',
        'wins_total', 'wins_home', 'wins_away',
        'draws_total', 'draws_home', 'draws_away',
        'loses_total', 'loses_home', 'loses_away',
        'goals_for_total', 'goals_for_home', 'goals_for_away',
        'goals_against_total', 'goals_against_home', 'goals_against_away',
        'goals_for_avg', 'goals_against_avg',
        'clean_sheets_total', 'failed_to_score_total', 'raw',
    ];

    protected $casts = [
        'league_id'         => 'integer',
        'season'            => 'integer',
        'team_api_id'       => 'integer',
        'goals_for_avg'     => 'float',
        'goals_against_avg' => 'float',
        'raw'               => 'array',
    ];
}
