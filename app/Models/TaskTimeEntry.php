<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TaskTimeEntry extends Model { protected $fillable = ['task_id', 'user_id', 'started_at', 'ended_at', 'duration_seconds', 'notes']; protected function casts(): array { return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'duration_seconds' => 'integer']; } public function task(): BelongsTo { return $this->belongsTo(Task::class); } public function user(): BelongsTo { return $this->belongsTo(User::class); } }
