<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationSequence extends Model
{
    protected $fillable = ['next_number'];
    protected $casts = ['next_number' => 'integer'];
}
