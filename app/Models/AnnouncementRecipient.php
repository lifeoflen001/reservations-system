<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementRecipient extends Model
{
    protected $fillable = [
        'announcement_id', 'user_id', 'audience_source', 'email_status', 'browser_status',
        'in_app_sent_at', 'email_sent_at', 'browser_sent_at', 'first_viewed_at', 'last_viewed_at', 'view_count',
    ];

    protected function casts(): array
    {
        return ['in_app_sent_at' => 'datetime', 'email_sent_at' => 'datetime', 'browser_sent_at' => 'datetime', 'first_viewed_at' => 'datetime', 'last_viewed_at' => 'datetime'];
    }

    public function announcement(): BelongsTo { return $this->belongsTo(Announcement::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
