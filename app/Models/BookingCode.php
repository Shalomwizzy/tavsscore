<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingCode extends Model
{
    protected $fillable = ['platform', 'code', 'note'];
}
