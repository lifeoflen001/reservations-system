<?php

namespace App\Models;

use App\Models\Concerns\AssignsTenantOwnership;
use Illuminate\Database\Eloquent\Model;

class IntegrationSetting extends Model
{
    use AssignsTenantOwnership;

    protected $fillable = ['key', 'provider', 'status', 'mode', 'is_enabled', 'settings', 'secrets', 'last_success_at', 'last_failure_at', 'last_error'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'settings' => 'array', 'secrets' => 'encrypted:array', 'last_success_at' => 'datetime', 'last_failure_at' => 'datetime'];
    }
}
