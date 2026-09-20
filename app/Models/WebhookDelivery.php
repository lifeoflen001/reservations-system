<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = ['webhook_endpoint_id', 'event', 'entity_type', 'entity_id', 'payload', 'signature', 'status', 'http_status', 'attempts', 'duration_ms', 'delivered_at', 'failed_at', 'error_summary'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'delivered_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
