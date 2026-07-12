<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    public $timestamps = false;

    protected $fillable = ['canonical_name', 'first_seen_at'];

    protected $casts = [
        'first_seen_at' => 'datetime',
    ];

    public function aliases(): HasMany
    {
        return $this->hasMany(TeamAlias::class);
    }
}
