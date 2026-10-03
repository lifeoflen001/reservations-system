<?php

namespace App\Services;

use App\Enums\RoomOperationalStatus;
use App\Models\Room;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class RoomService
{
    public function __construct(private readonly RoomStateService $state, private readonly UsageLimitService $limits, private readonly TenantContext $context) {}

    public function create(array $data, ?int $actorId = null): Room
    {
        return DB::transaction(function () use ($data, $actorId): Room {
            $this->limits->assertCanConsume($this->context->requireOrganization(), 'rooms');
            return Room::create([...$data, 'created_by' => $actorId, 'updated_by' => $actorId]);
        });
    }

    public function update(Room $room, array $data, ?int $actorId = null): Room
    {
        return DB::transaction(function () use ($room, $data, $actorId) {
            $room = Room::query()->lockForUpdate()->findOrFail($room->id);
            $status = RoomOperationalStatus::from($data['operational_status']);
            $this->state->assertManualStatusChange($room, $status);
            $room->update([...$data, 'updated_by' => $actorId]);
            return $room->refresh();
        });
    }

    public function archive(Room $room, ?int $actorId = null): Room
    {
        return DB::transaction(function () use ($room, $actorId) {
            $room = Room::query()->lockForUpdate()->findOrFail($room->id);
            $room->update(['is_active' => false, 'operational_status' => RoomOperationalStatus::Blocked, 'updated_by' => $actorId]);
            return $room->refresh();
        });
    }

    public function setStatus(Room $room, RoomOperationalStatus $status, ?int $actorId = null): Room
    {
        return DB::transaction(function () use ($room, $status, $actorId) {
            $room = Room::query()->lockForUpdate()->findOrFail($room->id);
            $this->state->assertManualStatusChange($room, $status);
            $room->update(['operational_status' => $status, 'updated_by' => $actorId]);
            return $room->refresh();
        });
    }
}
