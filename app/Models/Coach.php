<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coach extends Model
{
    protected $fillable = [
        'coach_api_id', 'name', 'team_api_id', 'team_name',
        'age', 'nationality', 'photo', 'is_current',
    ];

    protected $casts = [
        'coach_api_id' => 'integer',
        'team_api_id'  => 'integer',
        'age'          => 'integer',
        'is_current'   => 'boolean',
    ];
}
