<?php

namespace App\Models;

use App\Services\EntitlementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanLimit extends Model
{
    protected $fillable = ['plan_id', 'key', 'value', 'unit'];

    protected function casts(): array
    {
        return ['value' => 'integer'];
    }

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }

    protected static function booted(): void
    {
        static::saved(fn (self $item) => app(EntitlementService::class)->forgetForPlan($item->plan_id));
        static::deleted(fn (self $item) => app(EntitlementService::class)->forgetForPlan($item->plan_id));
    }
}
