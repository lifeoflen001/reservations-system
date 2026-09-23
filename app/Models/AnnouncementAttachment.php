<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementAttachment extends Model
{
    protected $fillable = ['announcement_id', 'disk', 'stored_name', 'original_name', 'mime_type', 'size', 'uploaded_by'];
    public function announcement(): BelongsTo { return $this->belongsTo(Announcement::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
