<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelConnection extends Model
{
    protected $fillable = ['provider', 'name', 'property_external_id', 'status', 'is_active', 'credentials', 'last_sync_at', 'last_error', 'created_by', 'updated_by'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'is_active' => 'boolean', 'last_sync_at' => 'datetime'];
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(ChannelMapping::class);
    }
}
