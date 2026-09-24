<?php

namespace App\Policies;

use App\Models\PosProduct;
use App\Models\User;

class PosProductPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('pos.products.view') || $user->hasPermission('pos.manage'); }
    public function view(User $user, PosProduct $product): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $user->hasPermission('pos.products.manage') || $user->hasPermission('pos.manage'); }
    public function update(User $user, PosProduct $product): bool { return $this->create($user); }
    public function delete(User $user, PosProduct $product): bool { return $this->create($user); }
}
