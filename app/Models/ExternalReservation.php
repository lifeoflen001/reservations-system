<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalReservation extends Model
{
    protected $fillable = ['channel_connection_id', 'external_id', 'reservation_id', 'payload', 'status'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
