<?php
namespace App\Models;
use App\Models\Concerns\AssignsTenantOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class TaskTag extends Model { use AssignsTenantOwnership; protected $fillable = ['name']; public function tasks(): BelongsToMany { return $this->belongsToMany(Task::class, 'task_tag'); } public function property(): BelongsTo { return $this->belongsTo(Property::class); } }
