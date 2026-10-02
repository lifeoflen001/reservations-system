<?php

namespace App\Models;

use App\Models\Concerns\AssignsTenantOwnership;
use Illuminate\Database\Eloquent\Model;

class EmailDeliveryLog extends Model
{
    use AssignsTenantOwnership;

    protected $fillable = ['recipient', 'subject', 'template', 'reservation_id', 'client_id', 'announcement_recipient_id', 'status', 'provider', 'attempts', 'sent_at', 'failed_at', 'error_summary'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    protected function propertyOwnershipRequired(): bool
    {
        return false;
    }
}
