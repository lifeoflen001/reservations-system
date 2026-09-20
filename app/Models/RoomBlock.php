<?php

namespace App\Models;

use App\Enums\RoomBlockType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomBlock extends Model
{
    protected $fillable = ['room_id', 'created_by', 'type', 'reason', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return ['type' => RoomBlockType::class, 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
