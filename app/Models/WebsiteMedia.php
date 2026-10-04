<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteMedia extends Model
{
    protected $fillable = ['category', 'disk', 'path', 'filename', 'original_filename', 'mime_type', 'width', 'height', 'file_size', 'alt_text', 'caption', 'uploaded_by', 'archived_at'];
    protected function casts(): array { return ['file_size' => 'integer', 'archived_at' => 'datetime']; }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function pages(): HasMany { return $this->hasMany(WebsitePage::class, 'og_media_id'); }
    public function url(): string
    {
        // Keep local/public media host-relative so a dev server on 8001 does
        // not request uploaded files from a stale APP_URL such as port 8000.
        return $this->disk === 'public'
            ? '/storage/'.ltrim($this->path, '/')
            : \Illuminate\Support\Facades\Storage::disk($this->disk)->url($this->path);
    }
}
