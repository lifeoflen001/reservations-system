<?php

namespace App\Models;

use App\Enums\HousekeepingStatus;
use App\Enums\RoomOperationalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = [
        'room_number', 'floor_id', 'room_category_id', 'room_type_id', 'operational_status',
        'housekeeping_status', 'base_rate', 'capacity', 'notes', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'operational_status' => RoomOperationalStatus::class,
            'housekeeping_status' => HousekeepingStatus::class,
            'base_rate' => 'decimal:2',
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function floor(): BelongsTo { return $this->belongsTo(Floor::class); }
    public function category(): BelongsTo { return $this->belongsTo(RoomCategory::class, 'room_category_id'); }
    public function roomType(): BelongsTo { return $this->belongsTo(RoomType::class); }
    public function reservations(): HasMany { return $this->hasMany(Reservation::class); }
    public function posOrders(): HasMany { return $this->hasMany(PosOrder::class); }
    public function posRoomCharges(): HasMany { return $this->hasMany(PosRoomCharge::class); }
    public function blocks(): HasMany { return $this->hasMany(RoomBlock::class); }
    public function housekeepingTasks(): HasMany { return $this->hasMany(HousekeepingTask::class); }
    public function maintenanceTasks(): HasMany { return $this->hasMany(MaintenanceTask::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
