<?php

namespace App\Policies;

use App\Models\MaintenanceTask;
use App\Models\User;

class MaintenanceTaskPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('maintenance.view') || $user->hasPermission('maintenance.manage'); }
    public function view(User $user, MaintenanceTask $task): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $user->hasPermission('maintenance.create') || $user->hasPermission('maintenance.manage'); }
    public function update(User $user, MaintenanceTask $task): bool { return $user->hasPermission('maintenance.update') || $user->hasPermission('maintenance.manage'); }
    public function delete(User $user, MaintenanceTask $task): bool { return $user->hasPermission('maintenance.delete') || $user->hasPermission('maintenance.manage'); }
    public function complete(User $user, MaintenanceTask $task): bool { return $user->hasPermission('maintenance.complete') || $user->hasPermission('maintenance.manage'); }
}
