<?php

namespace App\Services;

use App\Enums\RoomOperationalStatus;
use App\Enums\TaskStatus;
use App\Exceptions\RoomUnavailableException;
use App\Models\MaintenanceTask;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlock;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RoomAvailabilityService
{
    public function isAvailable(
        Room|int $room,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        ?int $ignoreReservationId = null,
    ): bool {
        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            return false;
        }

        $room = $room instanceof Room ? $room->fresh() : Room::findOrFail($room);
        if ($room->is_active === false || in_array($room->operational_status, [
            RoomOperationalStatus::Maintenance,
            RoomOperationalStatus::Blocked,
        ], true)) {
            return false;
        }

        $reservationOverlap = Reservation::query()
            ->where('room_id', $room->getKey())
            ->blockingAvailability()
            ->when($ignoreReservationId, fn ($query) => $query->where('id', '!=', $ignoreReservationId))
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->exists();

        if ($reservationOverlap) {
            return false;
        }

        $blockOverlap = RoomBlock::query()
            ->where('room_id', $room->getKey())
            ->where('is_active', true)
            ->where('starts_at', '<', $checkOut)
            ->where('ends_at', '>', $checkIn)
            ->exists();

        if ($blockOverlap) {
            return false;
        }

        $maintenanceOverlap = MaintenanceTask::query()
            ->where('room_id', $room->getKey())
            ->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value])
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<', $checkOut)
            ->where('ends_at', '>', $checkIn)
            ->exists();
        return ! $maintenanceOverlap;
    }

    public function assertAvailable(
        Room|int $room,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        ?int $ignoreReservationId = null,
    ): void {
        if (! $this->isAvailable($room, $checkIn, $checkOut, $ignoreReservationId)) {
            throw new RoomUnavailableException('The selected room is not available for those dates.');
        }
    }

    public function findAvailableRooms(CarbonInterface $checkIn, CarbonInterface $checkOut, ?int $ignoreReservationId = null): Collection
    {
        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            return collect();
        }

        $reservationRoomIds = Reservation::query()
            ->blockingAvailability()
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->when($ignoreReservationId, fn ($query) => $query->where('id', '!=', $ignoreReservationId))
            ->pluck('room_id');

        $blockRoomIds = RoomBlock::query()
            ->where('is_active', true)
            ->where('starts_at', '<', $checkOut)
            ->where('ends_at', '>', $checkIn)
            ->pluck('room_id');

        $maintenanceRoomIds = MaintenanceTask::query()
            ->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value])
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<', $checkOut)
            ->where('ends_at', '>', $checkIn)
            ->pluck('room_id');

        return Room::query()
            ->active()
            ->whereNotIn('operational_status', [RoomOperationalStatus::Maintenance->value, RoomOperationalStatus::Blocked->value])
            ->whereNotIn('id', $reservationRoomIds->merge($blockRoomIds)->merge($maintenanceRoomIds)->unique())
            ->with(['floor', 'category', 'roomType'])
            ->orderBy('room_number')
            ->get();
    }

    public function assertCapacity(Room|int $room, int $adults, int $children): void
    {
        $room = $room instanceof Room ? $room : Room::findOrFail($room);
        if ($adults < 1 || $children < 0 || ($adults + $children) > (int) $room->capacity) {
            throw new RoomUnavailableException('The selected room cannot accommodate that number of guests.');
        }
    }
}
