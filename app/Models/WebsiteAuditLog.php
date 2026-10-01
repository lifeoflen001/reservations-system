<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteAuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'target_type', 'target_id', 'metadata'];
    protected function casts(): array { return ['metadata' => 'array']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
