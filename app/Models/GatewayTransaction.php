<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GatewayTransaction extends Model
{
    protected $fillable = ['provider', 'external_transaction_id', 'reservation_id', 'payment_id', 'amount', 'currency', 'status', 'reference', 'received_at', 'confirmed_at', 'failure_reason'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'received_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }
}
