<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookInboundEvent extends Model
{
    protected $fillable = ['provider', 'event_id', 'event_type', 'status', 'processed_at'];
}
