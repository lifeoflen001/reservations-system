<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = ['task_number', 'title', 'description', 'category', 'status', 'priority', 'department_id', 'created_by', 'completed_by', 'room_id', 'reservation_id', 'client_id', 'housekeeping_task_id', 'maintenance_task_id', 'due_at', 'started_at', 'completed_at', 'archived_at', 'estimated_minutes', 'actual_minutes'];

    protected function casts(): array
    {
        return ['status' => TaskStatus::class, 'priority' => TaskPriority::class, 'due_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): Builder { return $query->whereNull('archived_at'); }
    public function scopeOverdue(Builder $query): Builder { return $query->whereNotNull('due_at')->where('due_at', '<', now())->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value]); }
    public function scopeOpen(Builder $query): Builder { return $query->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value]); }

    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function completer(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function housekeepingTask(): BelongsTo { return $this->belongsTo(HousekeepingTask::class); }
    public function maintenanceTask(): BelongsTo { return $this->belongsTo(MaintenanceTask::class); }
    public function assignees(): BelongsToMany { return $this->belongsToMany(User::class, 'task_assignees')->withPivot(['assigned_by', 'assigned_at']); }
    public function tags(): BelongsToMany { return $this->belongsToMany(TaskTag::class, 'task_tag'); }
    public function subtasks(): HasMany { return $this->hasMany(TaskSubtask::class)->orderBy('sort_order')->orderBy('id'); }
    public function comments(): HasMany { return $this->hasMany(TaskComment::class)->with('user')->latest(); }
    public function attachments(): HasMany { return $this->hasMany(TaskAttachment::class)->latest(); }
    public function timeEntries(): HasMany { return $this->hasMany(TaskTimeEntry::class)->with('user')->latest('started_at'); }
    public function activity(): HasMany { return $this->hasMany(TaskActivity::class)->with('user')->latest(); }
    public function activeTimeEntry(): HasMany { return $this->hasMany(TaskTimeEntry::class)->whereNull('ended_at'); }
}
