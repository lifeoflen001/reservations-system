<?php

namespace App\Models;

use App\Models\Concerns\AssignsTenantOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomCategory extends Model
{
    use AssignsTenantOwnership;
    protected $fillable = ['name', 'description', 'color', 'is_active', 'sort_order'];
    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
}
