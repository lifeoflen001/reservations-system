<?php

namespace App\Policies;

use App\Models\PosShift;
use App\Models\User;

class PosShiftPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('pos.shifts.view_all') || $user->hasPermission('pos.shifts.open'); }
    public function view(User $user, PosShift $shift): bool { return $user->hasPermission('pos.shifts.view_all') || $shift->cashier_id === $user->id; }
    public function open(User $user): bool { return $user->hasPermission('pos.shifts.open'); }
    public function close(User $user, PosShift $shift): bool { return $user->hasPermission('pos.shifts.close') && ($shift->cashier_id === $user->id || $user->hasPermission('pos.shifts.view_all')); }
}
