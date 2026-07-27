<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TennisPlayerRating extends Model
{
    protected $fillable = ['tour', 'player_name', 'surface', 'rating', 'matches_played', 'as_of_date'];
    protected $casts = ['rating' => 'float', 'as_of_date' => 'date'];
}
