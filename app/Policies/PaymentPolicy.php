<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('payments.view'); }
    public function view(User $user, Payment $payment): bool { return $user->hasPermission('payments.view'); }
    public function create(User $user): bool { return $user->hasPermission('payments.create'); }
    public function update(User $user, Payment $payment): bool { return $user->hasPermission('payments.update'); }
    public function void(User $user, Payment $payment): bool { return $user->hasPermission('payments.void'); }
    public function delete(User $user, Payment $payment): bool { return $user->hasPermission('payments.delete'); }
    public function print(User $user): bool { return $user->hasPermission('payments.print'); }
    public function export(User $user): bool { return $user->hasPermission('payments.export'); }
}
