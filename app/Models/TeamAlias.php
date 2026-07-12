<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamAlias extends Model
{
    public $timestamps = false;

    public const PROVIDER_API_FOOTBALL = 'api-football';

    protected $fillable = [
        'team_id', 'alias', 'provider', 'reviewed', 'first_seen_at',
    ];

    protected $casts = [
        'reviewed'      => 'boolean',
        'first_seen_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
