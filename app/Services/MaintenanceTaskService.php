<?php

namespace App\Services;

use App\Enums\HousekeepingStatus;
use App\Enums\TaskStatus;
use App\Models\MaintenanceTask;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use LogicException;

class MaintenanceTaskService
{
    public function __construct(private readonly RoomStateService $state) {}

    public function create(array $data, ?int $actorId = null): MaintenanceTask
    {
        return DB::transaction(function () use ($data, $actorId) {
            $room = Room::query()->lockForUpdate()->findOrFail($data['room_id']);
            $task = MaintenanceTask::create([...$data, 'created_by' => $actorId, 'status' => $data['status'] ?? TaskStatus::Pending->value]);
            if ($this->hasFutureReservationConflict($task)) throw new LogicException('This maintenance window overlaps a future reservation.');
            $this->state->sync($room);
            return $task;
        });
    }

    public function update(MaintenanceTask $task, array $data, ?int $actorId = null): MaintenanceTask
    {
        if ($task->status === TaskStatus::Completed) throw new LogicException('Completed maintenance history cannot be edited.');
        if (($data['status'] ?? null) === TaskStatus::Completed->value || ($data['status'] ?? null) === TaskStatus::Cancelled->value) throw new LogicException('Use the task action to complete or cancel a maintenance task.');
        return DB::transaction(function () use ($task, $data, $actorId) {
            $task = MaintenanceTask::query()->lockForUpdate()->findOrFail($task->id);
            $roomIds = collect([$task->room_id, $data['room_id'] ?? $task->room_id])->filter()->unique()->sort()->values();
            $rooms = Room::query()->whereIn('id', $roomIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $task->update([...$data, 'updated_by' => $actorId]);
            if ($this->hasFutureReservationConflict($task)) throw new LogicException('This maintenance window overlaps a future reservation.');
            $newRoomId = (int) ($task->room_id ?? 0);
            foreach ($rooms as $room) {
                // A moved task may have left its previous room in the derived
                // maintenance state. Reconcile that old room once, while the
                // normal path still preserves manually assigned states.
                $this->state->sync($room, $room->id !== $newRoomId);
            }
            return $task->refresh();
        });
    }

    public function complete(MaintenanceTask|int $task, ?int $actorId = null): MaintenanceTask
    {
        return DB::transaction(function () use ($task, $actorId) {
            $task = MaintenanceTask::query()->whereKey($task instanceof MaintenanceTask ? $task->id : $task)->lockForUpdate()->firstOrFail();
            if ($task->status === TaskStatus::Completed) return $task;
            if ($task->status === TaskStatus::Cancelled) throw new LogicException('Cancelled maintenance tasks cannot be completed.');
            $room = Room::query()->lockForUpdate()->findOrFail($task->room_id);
            $task->update(['status' => TaskStatus::Completed->value, 'completed_at' => now(), 'completed_by' => $actorId]);
            if ($room->housekeeping_status !== HousekeepingStatus::Dirty) $room->update(['housekeeping_status' => HousekeepingStatus::Clean->value]);
            $this->state->sync($room, true);
            return $task->refresh();
        });
    }

    public function cancel(MaintenanceTask|int $task, ?int $actorId = null): MaintenanceTask
    {
        return DB::transaction(function () use ($task, $actorId) {
            $task = MaintenanceTask::query()->whereKey($task instanceof MaintenanceTask ? $task->id : $task)->lockForUpdate()->firstOrFail();
            if ($task->status === TaskStatus::Completed) throw new LogicException('Completed maintenance history cannot be cancelled.');
            $room = $task->room_id ? Room::query()->lockForUpdate()->findOrFail($task->room_id) : null;
            $task->update(['status' => TaskStatus::Cancelled->value, 'updated_by' => $actorId]);
            if ($room) $this->state->sync($room, true);

            return $task->refresh();
        });
    }

    public function hasFutureReservationConflict(MaintenanceTask $task): bool
    {
        return Reservation::query()->where('room_id', $task->room_id)->blockingAvailability()->where('check_in', '>', now())->when($task->starts_at && $task->ends_at, fn ($q) => $q->where('check_in', '<', $task->ends_at)->where('check_out', '>', $task->starts_at))->exists();
    }
}
