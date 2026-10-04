<?php

namespace App\Models;

use App\Models\Concerns\AssignsTenantOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalReservation extends Model
{
    use AssignsTenantOwnership;
    protected $fillable = ['channel_connection_id', 'external_id', 'reservation_id', 'payload', 'status'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
}
