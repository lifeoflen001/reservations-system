<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HousekeepingTask extends Model
{
    protected $fillable = [
        'room_id', 'assignee_id', 'created_by', 'updated_by', 'task_type', 'priority', 'due_at', 'notes', 'status', 'completed_at', 'completed_by',
    ];

    protected function casts(): array
    {
        return ['priority' => TaskPriority::class, 'due_at' => 'datetime', 'status' => TaskStatus::class, 'completed_at' => 'datetime'];
    }

    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assignee_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function completer(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
}
