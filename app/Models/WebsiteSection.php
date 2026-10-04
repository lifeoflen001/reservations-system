<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteSection extends Model
{
    protected $fillable = ['page_id', 'section_key', 'section_type', 'position', 'is_visible', 'draft_content', 'published_content', 'draft_updated_by', 'published_by', 'published_at'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'draft_content' => 'array', 'published_content' => 'array', 'published_at' => 'datetime'];
    }

    public function page(): BelongsTo { return $this->belongsTo(WebsitePage::class, 'page_id'); }
    public function hasDraftChanges(): bool { return $this->draft_content !== $this->published_content; }
}
