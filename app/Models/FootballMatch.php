<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class FootballMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'api_id',
        'league_id',
        'league',
        'league_country',
        'home_team',
        'home_team_logo',
        'away_team',
        'away_team_logo',
        'home_score',
        'away_score',
        'home_score_ht',
        'away_score_ht',
        'status',
        'elapsed',
        'match_time',
        'integrity_flags',
        'held_for_review',
    ];

    protected $casts = [
        'api_id'          => 'integer',
        'league_id'       => 'integer',
        'home_score'      => 'integer',
        'away_score'      => 'integer',
        'home_score_ht'   => 'integer',
        'away_score_ht'   => 'integer',
        'elapsed'         => 'integer',
        'match_time'      => 'datetime',
        'integrity_flags' => 'array',
        'held_for_review' => 'boolean',
    ];

    public function prediction(): HasOne
    {
        return $this->hasOne(Prediction::class, 'match_id');
    }

    /**
     * SEO-friendly slug like "manchester-united-vs-arsenal-12345".
     * The trailing api_id keeps it unique and lets us look up O(1).
     */
    public function getSlugAttribute(): string
    {
        return Str::slug($this->home_team . ' vs ' . $this->away_team) . '-' . $this->api_id;
    }
}
