<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TaskAttachment extends Model { protected $fillable = ['task_id', 'uploaded_by', 'original_name', 'stored_name', 'disk', 'mime_type', 'size']; public function task(): BelongsTo { return $this->belongsTo(Task::class); } public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); } }
