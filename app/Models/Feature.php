<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    protected $fillable = ['key', 'name', 'description', 'category', 'type', 'status', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_features')
            ->withPivot(['enabled', 'configuration'])
            ->withTimestamps();
    }

    public function isAvailable(): bool
    {
        return $this->status === 'active';
    }
}
