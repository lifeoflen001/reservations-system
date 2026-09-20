<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = ['payment_id', 'invoice_number', 'issue_date', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'datetime'];
    }

    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
