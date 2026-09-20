<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailDeliveryLog extends Model
{
    protected $fillable = ['recipient', 'subject', 'template', 'reservation_id', 'client_id', 'status', 'provider', 'attempts', 'sent_at', 'failed_at', 'error_summary'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'failed_at' => 'datetime'];
    }
}
