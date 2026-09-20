<?php

namespace App\Policies;

use App\Models\HousekeepingTask;
use App\Models\User;

class HousekeepingTaskPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('housekeeping.view') || $user->hasPermission('housekeeping.manage'); }
    public function view(User $user, HousekeepingTask $task): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $user->hasPermission('housekeeping.create') || $user->hasPermission('housekeeping.manage'); }
    public function update(User $user, HousekeepingTask $task): bool { return $user->hasPermission('housekeeping.update') || $user->hasPermission('housekeeping.manage'); }
    public function delete(User $user, HousekeepingTask $task): bool { return $user->hasPermission('housekeeping.delete') || $user->hasPermission('housekeeping.manage'); }
    public function complete(User $user, HousekeepingTask $task): bool { return $user->hasPermission('housekeeping.complete') || $user->hasPermission('housekeeping.manage'); }
}
