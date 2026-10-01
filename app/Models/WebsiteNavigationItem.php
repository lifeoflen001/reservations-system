<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteNavigationItem extends Model
{
    protected $fillable = ['location', 'parent_id', 'label', 'destination_type', 'destination', 'position', 'is_visible', 'status', 'draft_label', 'draft_destination', 'draft_visible'];
    protected function casts(): array { return ['is_visible' => 'boolean', 'draft_visible' => 'boolean']; }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('position'); }
}
