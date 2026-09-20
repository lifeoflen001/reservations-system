<?php

namespace App\Services;

use App\Enums\HousekeepingStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomOperationalStatus;
use App\Enums\TaskStatus;
use App\Models\MaintenanceTask;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlock;
use LogicException;

class RoomStateService
{
    public function sync(Room|int $room, bool $forceManualState = false): Room
    {
        $room = $room instanceof Room ? $room->fresh() : Room::query()->findOrFail($room);
        if (! $room->is_active) return $room;
        if (! $forceManualState && in_array($room->operational_status, [RoomOperationalStatus::Maintenance, RoomOperationalStatus::Blocked], true)) return $room;
        $now = now();
        if (RoomBlock::query()->where('room_id', $room->id)->where('is_active', true)->where('starts_at', '<', $now)->where('ends_at', '>', $now)->exists()) return tap($room)->update(['operational_status' => RoomOperationalStatus::Blocked]);
        if (MaintenanceTask::query()->where('room_id', $room->id)->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value])->where(function ($q) use ($now) {
            $q->whereNull('starts_at')->orWhere(function ($q) use ($now) { $q->where('starts_at', '<=', $now)->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now)); });
        })->exists()) return tap($room)->update(['operational_status' => RoomOperationalStatus::Maintenance]);
        $reservation = Reservation::query()->where('room_id', $room->id)->blockingAvailability()->where('check_out', '>', $now)->orderBy('check_in')->first();
        if ($reservation?->status === ReservationStatus::CheckedIn && $reservation->check_in->lessThanOrEqualTo($now)) $status = RoomOperationalStatus::Occupied;
        elseif ($reservation) $status = RoomOperationalStatus::Reserved;
        elseif ($room->housekeeping_status === HousekeepingStatus::Dirty) $status = RoomOperationalStatus::MustClean;
        else $status = RoomOperationalStatus::Available;
        $room->update(['operational_status' => $status]);
        return $room->refresh();
    }

    public function assertManualStatusChange(Room $room, RoomOperationalStatus $status): void
    {
        if ($status !== RoomOperationalStatus::Available) return;
        if (! $room->is_active) throw new LogicException('Inactive rooms cannot be made available.');
        if ($room->housekeeping_status === HousekeepingStatus::Dirty) throw new LogicException('A dirty room must be cleaned before it can be made available.');
        if (MaintenanceTask::query()->where('room_id', $room->id)->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value])->exists()) throw new LogicException('This room has active maintenance.');
        if (RoomBlock::query()->where('room_id', $room->id)->where('is_active', true)->where('ends_at', '>', now())->exists()) throw new LogicException('This room is blocked.');
        if (Reservation::query()->where('room_id', $room->id)->blockingAvailability()->where('check_out', '>', now())->exists()) throw new LogicException('This room has an active or future reservation.');
    }
}
