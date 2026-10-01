<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactEnquiry extends Model
{
    public const STATUSES = ['new', 'read', 'replied', 'closed'];

    protected $fillable = [
        'name', 'company', 'email', 'phone', 'country', 'hotel_size', 'enquiry_type', 'message', 'status',
    ];
}
