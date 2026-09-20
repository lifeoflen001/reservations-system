<?php

namespace App\Policies;

use App\Models\User;

class StaffPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('staff.view') || $user->hasPermission('staff.manage'); }
    public function view(User $user, User $staff): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $user->hasPermission('staff.create') || $user->hasPermission('staff.manage'); }
    public function update(User $user, User $staff): bool { return $user->hasPermission('staff.update') || $user->hasPermission('staff.manage'); }
    public function disable(User $user, User $staff): bool { return $user->hasPermission('staff.disable') || $user->hasPermission('staff.manage'); }
    public function resetPassword(User $user, User $staff): bool { return $user->hasPermission('staff.reset_password') || $user->hasPermission('staff.manage'); }
}
