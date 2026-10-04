<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteRevision extends Model
{
    protected $fillable = ['page_id', 'version', 'snapshot', 'created_by'];
    protected function casts(): array { return ['snapshot' => 'array']; }
    public function page(): BelongsTo { return $this->belongsTo(WebsitePage::class, 'page_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
