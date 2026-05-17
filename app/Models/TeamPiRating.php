<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamPiRating extends Model
{
    protected $fillable = [
        'team', 'pi_home', 'pi_away', 'matches_rated', 'last_match_at',
    ];

    protected $casts = [
        'pi_home'       => 'float',
        'pi_away'       => 'float',
        'matches_rated' => 'integer',
        'last_match_at' => 'datetime',
    ];
}
