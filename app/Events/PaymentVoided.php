<?php

namespace App\Events;

use App\Models\Payment;

class PaymentVoided
{
    public function __construct(public Payment $payment) {}
}
