<?php
namespace App\Policies;
use App\Models\Task;
use App\Models\User;
class TaskPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('tasks.view') || $user->hasPermission('tasks.manage'); }
    public function view(User $user, Task $task): bool { return $this->viewAny($user) && $this->withinScope($user, $task); }
    public function create(User $user): bool { return $user->hasPermission('tasks.create') || $user->hasPermission('tasks.manage'); }
    public function update(User $user, Task $task): bool { return $this->withinScope($user, $task) && ($user->hasPermission('tasks.update') || $user->hasPermission('tasks.manage') || $task->created_by === $user->id); }
    public function assign(User $user, Task $task): bool { return $this->withinScope($user, $task) && ($user->hasPermission('tasks.assign') || $user->hasPermission('tasks.manage')); }
    public function complete(User $user, Task $task): bool { return $this->withinScope($user, $task) && ($user->hasPermission('tasks.complete') || $user->hasPermission('tasks.manage') || $task->assignees->contains('id', $user->id)); }
    public function reopen(User $user, Task $task): bool { return $this->withinScope($user, $task) && ($user->hasPermission('tasks.reopen') || $user->hasPermission('tasks.manage')); }
    public function comment(User $user, Task $task): bool { return $this->withinScope($user, $task) && ($user->hasPermission('tasks.comment') || $user->hasPermission('tasks.manage') || $this->view($user, $task)); }
    public function uploadAttachment(User $user, Task $task): bool { return $this->withinScope($user, $task) && ($user->hasPermission('tasks.upload') || $user->hasPermission('tasks.manage')); }
    public function trackTime(User $user, Task $task): bool { return $this->withinScope($user, $task) && ($user->hasPermission('tasks.track_time') || $user->hasPermission('tasks.manage') || $task->assignees->contains('id', $user->id)); }
    public function archive(User $user, Task $task): bool { return $this->withinScope($user, $task) && ($user->hasPermission('tasks.archive') || $user->hasPermission('tasks.manage')); }

    private function withinScope(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.view_all_departments')
            || $task->created_by === $user->id
            || $task->assignees->contains('id', $user->id)
            || $task->department_id === null
            || $task->department_id === $user->department_id;
    }
}
