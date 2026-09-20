<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool { return $user->hasPermission('invoices.view'); }
    public function print(User $user, Invoice $invoice): bool { return $user->hasPermission('invoices.print'); }
    public function download(User $user, Invoice $invoice): bool { return $user->hasPermission('invoices.download'); }
}
