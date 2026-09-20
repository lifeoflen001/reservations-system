<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('roles.view') || $user->hasPermission('roles.manage'); }
    public function view(User $user, Role $role): bool { return $this->viewAny($user); }
    public function update(User $user, Role $role): bool { return $user->hasPermission('roles.manage'); }
}
