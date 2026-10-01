<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsitePricingPlan extends Model
{
    protected $fillable = ['name', 'short_description', 'price_display', 'billing_label', 'cta_label', 'cta_url', 'is_highlighted', 'position', 'is_active', 'status', 'published_at', 'updated_by'];
    protected function casts(): array { return ['is_highlighted' => 'boolean', 'is_active' => 'boolean', 'published_at' => 'datetime']; }
    public function features(): HasMany { return $this->hasMany(WebsitePricingFeature::class, 'plan_id')->orderBy('position'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
