<?php

namespace App\Events;

use App\Models\Payment;

class PaymentReceived
{
    public function __construct(public Payment $payment) {}
}
