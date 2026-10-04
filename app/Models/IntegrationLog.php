<?php

namespace App\Models;

use App\Models\Concerns\AssignsTenantOwnership;
use Illuminate\Database\Eloquent\Model;

class IntegrationLog extends Model
{
    use AssignsTenantOwnership;

    protected $fillable = ['integration', 'action', 'entity_type', 'entity_id', 'direction', 'status', 'attempts', 'started_at', 'completed_at', 'error_summary', 'metadata'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime', 'metadata' => 'array'];
    }

    protected function propertyOwnershipRequired(): bool
    {
        return false;
    }
}
