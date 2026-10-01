<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsitePage extends Model
{
    public const STATUSES = ['draft', 'published'];

    protected $fillable = [
        'key', 'name', 'route_name', 'status', 'seo_title', 'seo_description', 'og_title', 'og_description',
        'og_media_id', 'robots_index', 'robots_follow', 'published_at', 'published_by', 'draft_updated_by', 'version',
    ];

    protected function casts(): array
    {
        return ['robots_index' => 'boolean', 'robots_follow' => 'boolean', 'published_at' => 'datetime'];
    }

    public function sections(): HasMany { return $this->hasMany(WebsiteSection::class, 'page_id')->orderBy('position'); }
    public function revisions(): HasMany { return $this->hasMany(WebsiteRevision::class, 'page_id')->latest(); }
    public function ogMedia(): BelongsTo { return $this->belongsTo(WebsiteMedia::class, 'og_media_id'); }
    public function publisher(): BelongsTo { return $this->belongsTo(User::class, 'published_by'); }
    public function draftEditor(): BelongsTo { return $this->belongsTo(User::class, 'draft_updated_by'); }

    public function hasDraftChanges(): bool
    {
        return $this->status === 'draft' || $this->sections->contains(fn (WebsiteSection $section) => $section->hasDraftChanges());
    }
}
