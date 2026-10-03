<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationOnboarding extends Model
{
    protected $fillable = [
        'organization_id', 'owner_user_id', 'current_step', 'organization_completed',
        'plan_completed', 'property_completed', 'hotel_setup_completed', 'completed_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'organization_completed' => 'boolean', 'plan_completed' => 'boolean',
            'property_completed' => 'boolean', 'hotel_setup_completed' => 'boolean',
            'completed_at' => 'datetime', 'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
}
