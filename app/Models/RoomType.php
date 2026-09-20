<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    protected $fillable = ['name', 'description', 'capacity', 'bed_type', 'base_rate', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'base_rate' => 'decimal:2', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function rooms(): HasMany { return $this->hasMany(Room::class); }
    public function amenities(): BelongsToMany { return $this->belongsToMany(Amenity::class); }
}
