<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskComment;
use App\Models\TaskSubtask;
use App\Models\TaskTag;
use App\Models\TaskTimeEntry;
use App\Models\User;
use App\Notifications\HotelDatabaseNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TaskService
{
    public function create(array $data, int $actorId): Task
    {
        return DB::transaction(function () use ($data, $actorId): Task {
            $task = Task::create($this->contextFromReservation($data) + collect($data)->except(['assignee_ids', 'tags', 'subtasks'])->merge(['task_number' => 'TASK-'.Str::uuid(), 'created_by' => $actorId])->all());
            $task->update(['task_number' => 'TSK-'.str_pad((string) $task->id, 6, '0', STR_PAD_LEFT)]);
            $this->syncRelations($task, $data, $actorId);
            $this->activity($task, $actorId, 'Task created');
            $this->notifyAssignees($task, $actorId, 'Task assigned', 'A new task has been assigned to you.');
            return $task->fresh($this->detailRelations());
        });
    }

    public function update(Task $task, array $data, int $actorId): Task
    {
        return DB::transaction(function () use ($task, $data, $actorId): Task {
            $task->update($this->contextFromReservation($data) + collect($data)->except(['assignee_ids', 'tags', 'subtasks'])->all());
            $this->syncRelations($task, $data, $actorId);
            $this->activity($task, $actorId, 'Task updated');
            if (array_key_exists('assignee_ids', $data)) $this->notifyAssignees($task, $actorId, 'Task assignment updated', 'Your task assignments have been updated for '.$task->task_number.'.');
            return $task->fresh($this->detailRelations());
        });
    }

    public function complete(Task $task, int $actorId): Task
    {
        return DB::transaction(function () use ($task, $actorId): Task {
            $this->stopActiveTimers($task, $actorId);
            $task->update(['status' => 'completed', 'completed_by' => $actorId, 'completed_at' => now(), 'actual_minutes' => $this->totalMinutes($task)]);
            $this->activity($task, $actorId, 'Task completed');
            $this->notifyAssignees($task, $actorId, 'Task completed', $task->task_number.' has been completed.');
            return $task->fresh($this->detailRelations());
        });
    }

    public function reopen(Task $task, int $actorId): Task
    {
        $task->update(['status' => 'in_progress', 'completed_by' => null, 'completed_at' => null]);
        $this->activity($task, $actorId, 'Task reopened');
        return $task->fresh($this->detailRelations());
    }

    public function archive(Task $task, int $actorId): void
    {
        $task->update(['archived_at' => now()]);
        $this->activity($task, $actorId, 'Task archived');
        $task->delete();
    }

    public function comment(Task $task, array $data, int $actorId): TaskComment
    {
        $comment = $task->comments()->create(['user_id' => $actorId, 'parent_id' => $data['parent_id'] ?? null, 'body' => trim($data['body'])]);
        $this->activity($task, $actorId, 'Comment added');
        $this->notifyAssignees($task, $actorId, 'New task comment', 'A new comment was added to '.$task->task_number.'.');
        return $comment->load('user');
    }

    public function addSubtask(Task $task, string $title, int $actorId): TaskSubtask
    {
        $subtask = $task->subtasks()->create(['title' => trim($title), 'sort_order' => (int) $task->subtasks()->max('sort_order') + 1]);
        $this->activity($task, $actorId, 'Subtask added', ['title' => $subtask->title]);
        return $subtask;
    }

    public function toggleSubtask(TaskSubtask $subtask, bool $completed, int $actorId): TaskSubtask
    {
        $subtask->update(['is_completed' => $completed, 'completed_by' => $completed ? $actorId : null, 'completed_at' => $completed ? now() : null]);
        $this->activity($subtask->task, $actorId, $completed ? 'Subtask completed' : 'Subtask reopened', ['title' => $subtask->title]);
        return $subtask->fresh();
    }

    public function startTimer(Task $task, int $userId): TaskTimeEntry
    {
        return DB::transaction(function () use ($task, $userId): TaskTimeEntry {
            $existing = TaskTimeEntry::query()->where('user_id', $userId)->whereNull('ended_at')->lockForUpdate()->first();
            if ($existing) return $existing;
            $entry = $task->timeEntries()->create(['user_id' => $userId, 'started_at' => now()]);
            $task->update(['started_at' => $task->started_at ?: $entry->started_at]);
            $this->activity($task, $userId, 'Timer started');
            return $entry;
        });
    }

    public function stopTimer(Task $task, int $userId): ?TaskTimeEntry
    {
        $entry = $task->timeEntries()->where('user_id', $userId)->whereNull('ended_at')->latest('started_at')->first();
        if (! $entry) return null;
        $end = now();
        $entry->update(['ended_at' => $end, 'duration_seconds' => max(0, $entry->started_at->diffInSeconds($end))]);
        $task->update(['actual_minutes' => $this->totalMinutes($task)]);
        $this->activity($task, $userId, 'Timer stopped');
        return $entry->fresh();
    }

    public function storeAttachment(Task $task, UploadedFile $file, int $userId): object
    {
        $storedName = $file->hashName();
        $path = $file->storeAs('tasks/'.$task->id, $storedName, 'local');
        $attachment = $task->attachments()->create(['uploaded_by' => $userId, 'original_name' => $file->getClientOriginalName(), 'stored_name' => $path, 'disk' => 'local', 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize() ?: 0]);
        $this->activity($task, $userId, 'Attachment uploaded', ['name' => $attachment->original_name]);
        return $attachment;
    }

    public function deleteAttachment(object $attachment, int $userId): void
    {
        Storage::disk($attachment->disk)->delete($attachment->stored_name);
        $this->activity($attachment->task, $userId, 'Attachment removed', ['name' => $attachment->original_name]);
        $attachment->delete();
    }

    public function totalMinutes(Task $task): int
    {
        return (int) floor((float) $task->timeEntries()->whereNotNull('duration_seconds')->sum('duration_seconds') / 60);
    }

    public function detailRelations(): array { return ['department', 'creator', 'completer', 'room', 'reservation.client', 'reservation.room', 'client', 'assignees.department', 'tags', 'subtasks', 'comments.user', 'attachments.uploader', 'timeEntries.user', 'activity.user']; }

    private function contextFromReservation(array $data): array
    {
        if (! empty($data['reservation_id'])) {
            $reservation = \App\Models\Reservation::with('client')->find($data['reservation_id']);
            if ($reservation) return ['room_id' => $data['room_id'] ?? $reservation->room_id, 'client_id' => $data['client_id'] ?? $reservation->client_id];
        }
        return [];
    }

    private function syncRelations(Task $task, array $data, int $actorId): void
    {
        if (array_key_exists('assignee_ids', $data)) $task->assignees()->syncWithPivotValues(array_values(array_unique($data['assignee_ids'] ?? [])), ['assigned_by' => $actorId, 'assigned_at' => now()]);
        if (array_key_exists('tags', $data)) {
            $tagIds = collect($data['tags'] ?? [])->map(fn ($name) => trim((string) $name))->filter()->unique()->map(fn ($name) => TaskTag::firstOrCreate(['name' => $name])->id)->all();
            $task->tags()->sync($tagIds);
        }
        if (array_key_exists('subtasks', $data)) {
            $task->subtasks()->delete();
            foreach (array_values(array_filter($data['subtasks'] ?? [])) as $index => $title) $task->subtasks()->create(['title' => trim($title), 'sort_order' => $index]);
        }
    }

    private function activity(Task $task, int $userId, string $event, array $metadata = []): void { TaskActivity::create(['task_id' => $task->id, 'user_id' => $userId, 'event' => $event, 'metadata' => $metadata ?: null]); }
    private function stopActiveTimers(Task $task, int $userId): void { foreach ($task->activeTimeEntry()->get() as $entry) $entry->update(['ended_at' => now(), 'duration_seconds' => max(0, $entry->started_at->diffInSeconds(now()))]); }
    private function notifyAssignees(Task $task, int $actorId, string $title, string $message): void { $task->loadMissing('assignees'); foreach ($task->assignees->where('id', '!=', $actorId) as $user) if ($user->hasPermission('notifications.view')) $user->notify(new HotelDatabaseNotification(['title' => $title, 'message' => $message, 'severity' => 'info', 'category' => 'operational', 'entity_type' => Task::class, 'entity_id' => $task->id, 'action_url' => route('tasks.show', $task)])); }
}
