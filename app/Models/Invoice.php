<?php

namespace App\Models;

use App\Models\Concerns\AssignsTenantOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use AssignsTenantOwnership;
    protected $fillable = ['payment_id', 'invoice_number', 'issue_date', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'datetime'];
    }

    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
}
