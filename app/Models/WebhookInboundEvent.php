<?php

namespace App\Models;

use App\Models\Concerns\AssignsTenantOwnership;
use Illuminate\Database\Eloquent\Model;

class WebhookInboundEvent extends Model
{
    use AssignsTenantOwnership;

    protected $fillable = ['provider', 'event_id', 'event_type', 'status', 'processed_at'];

    protected function propertyOwnershipRequired(): bool
    {
        return false;
    }
}
