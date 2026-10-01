<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactEnquiry extends Model
{
    public const STATUSES = ['new', 'read', 'replied', 'closed'];

    protected $fillable = [
        'name', 'company', 'email', 'phone', 'country', 'hotel_size', 'enquiry_type', 'message', 'internal_notes', 'status',
        'assigned_to', 'read_at', 'replied_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return ['hotel_size' => 'integer', 'read_at' => 'datetime', 'replied_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function assignee(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
}
