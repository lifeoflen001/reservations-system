<?php

namespace App\Policies;

use App\Models\PosOrder;
use App\Models\User;

class PosOrderPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('pos.view_orders') || $user->hasPermission('pos.access'); }
    public function view(User $user, PosOrder $order): bool { return $user->hasPermission('pos.view_all_orders') || $user->hasPermission('pos.manage') || $order->cashier_id === $user->id; }
    public function create(User $user): bool { return $user->hasPermission('pos.sell'); }
    public function chargeRoom(User $user): bool { return $user->hasPermission('pos.charge_room'); }
    public function discount(User $user): bool { return $user->hasPermission('pos.discount'); }
    public function void(User $user, PosOrder $order): bool { return $user->hasPermission('pos.void'); }
    public function refund(User $user, PosOrder $order): bool { return $user->hasPermission('pos.refund'); }
}
