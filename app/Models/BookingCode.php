<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingCode extends Model
{
    protected $fillable = [
        'platform', 'code', 'link', 'ticket_image_path', 'slip_ref', 'fixtures',
        'total_odds', 'source', 'status', 'note', 'pick_date', 'expires_at', 'settled_at',
        'x_posted_at',
    ];

    protected $casts = [
        'fixtures'    => 'array',
        'total_odds'  => 'float',
        'pick_date'   => 'date',
        'expires_at'  => 'datetime',
        'settled_at'  => 'datetime',
        'x_posted_at' => 'datetime',
    ];

    public function legs(): HasMany
    {
        return $this->hasMany(BookingCodeLeg::class)->orderBy('id');
    }
}
