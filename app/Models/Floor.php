<?php

namespace App\Models;

use App\Models\Concerns\AssignsTenantOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Floor extends Model
{
    use AssignsTenantOwnership;
    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
}
