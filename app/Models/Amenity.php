<?php

namespace App\Models;

use App\Models\Concerns\AssignsTenantOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Amenity extends Model
{
    use AssignsTenantOwnership;
    protected $fillable = ['name'];

    public function roomTypes(): BelongsToMany
    {
        return $this->belongsToMany(RoomType::class);
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
}
