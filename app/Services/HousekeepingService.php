<?php

namespace App\Services;

use App\Enums\HousekeepingStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\HousekeepingTask;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use LogicException;

class HousekeepingService
{
    public function __construct(private readonly RoomStateService $state) {}

    public function create(array $data, ?int $actorId = null): HousekeepingTask
    {
        return DB::transaction(function () use ($data, $actorId) {
            $room = Room::query()->lockForUpdate()->findOrFail($data['room_id']);
            $task = HousekeepingTask::create([...$data, 'created_by' => $actorId, 'status' => $data['status'] ?? TaskStatus::Pending->value]);

            // Keep the room state and the task queue committed together. The
            // state service decides whether an active task changes the room.
            $this->state->sync($room);

            return $task->refresh();
        });
    }

    public function update(HousekeepingTask $task, array $data, ?int $actorId = null): HousekeepingTask
    {
        if ($task->status === TaskStatus::Completed) throw new LogicException('Completed housekeeping history cannot be edited.');
        if (($data['status'] ?? null) === TaskStatus::Completed->value || ($data['status'] ?? null) === TaskStatus::Cancelled->value) throw new LogicException('Use the task action to complete or cancel a housekeeping task.');
        return DB::transaction(function () use ($task, $data, $actorId) {
            $task = HousekeepingTask::query()->lockForUpdate()->findOrFail($task->id);
            $roomIds = collect([$task->room_id, $data['room_id'] ?? $task->room_id])->filter()->unique()->sort()->values();
            $rooms = Room::query()->whereIn('id', $roomIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $room = $rooms->get($task->room_id);
            if (! $room) throw new LogicException('The housekeeping task is linked to a missing room.');
            $task->update([...$data, 'updated_by' => $actorId]);
            foreach ($rooms as $linkedRoom) $this->state->sync($linkedRoom);

            return $task->refresh();
        });
    }

    public function ensureTurnoverTask(Room $room, ?int $actorId, string $reservationCode): HousekeepingTask
    {
        $task = $room->housekeepingTasks()->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value])->where('task_type', 'cleaning')->first();
        return $task ?: HousekeepingTask::create(['room_id' => $room->id, 'created_by' => $actorId, 'task_type' => 'cleaning', 'priority' => TaskPriority::High->value, 'due_at' => now(), 'notes' => 'Turnover after reservation '.$reservationCode, 'status' => TaskStatus::Pending->value]);
    }

    public function complete(HousekeepingTask|int $task, ?int $actorId = null): HousekeepingTask
    {
        return DB::transaction(function () use ($task, $actorId) {
            $task = HousekeepingTask::query()->whereKey($task instanceof HousekeepingTask ? $task->id : $task)->lockForUpdate()->firstOrFail();
            if ($task->status === TaskStatus::Completed) return $task;
            if ($task->status === TaskStatus::Cancelled) throw new LogicException('Cancelled housekeeping tasks cannot be completed.');
            $room = Room::query()->lockForUpdate()->findOrFail($task->room_id);
            $task->update(['status' => TaskStatus::Completed->value, 'completed_at' => now(), 'completed_by' => $actorId]);
            $room->update(['housekeeping_status' => HousekeepingStatus::Clean->value]);
            $this->state->sync($room);
            return $task->refresh();
        });
    }

    public function cancel(HousekeepingTask|int $task, ?int $actorId = null): HousekeepingTask
    {
        return DB::transaction(function () use ($task, $actorId) {
            $task = HousekeepingTask::query()->whereKey($task instanceof HousekeepingTask ? $task->id : $task)->lockForUpdate()->firstOrFail();
            if ($task->status === TaskStatus::Completed) throw new LogicException('Completed housekeeping history cannot be cancelled.');
            $room = Room::query()->lockForUpdate()->findOrFail($task->room_id);
            $task->update(['status' => TaskStatus::Cancelled->value, 'updated_by' => $actorId]);
            $this->state->sync($room);

            return $task->refresh();
        });
    }
}
