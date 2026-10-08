<?php

namespace App\Models;

use App\Models\Concerns\AssignsTenantOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatewayTransaction extends Model
{
    use AssignsTenantOwnership;
    protected $fillable = ['property_id', 'provider', 'external_transaction_id', 'reservation_id', 'payment_id', 'amount', 'currency', 'status', 'reference', 'received_at', 'confirmed_at', 'failure_reason'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'received_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    protected function propertyOwnershipRequired(): bool
    {
        return false;
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
}
