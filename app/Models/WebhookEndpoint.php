<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    protected $fillable = ['name', 'url', 'signing_secret', 'events', 'is_active', 'created_by', 'updated_by', 'last_success_at', 'last_failure_at', 'last_error'];

    protected $hidden = ['signing_secret'];

    protected function casts(): array
    {
        return ['signing_secret' => 'encrypted', 'events' => 'array', 'is_active' => 'boolean', 'last_success_at' => 'datetime', 'last_failure_at' => 'datetime'];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
