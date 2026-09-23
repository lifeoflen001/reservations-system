<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title', 'category', 'short_description', 'content', 'start_at', 'end_at', 'status',
        'is_featured', 'is_high_priority', 'is_company_wide', 'created_by', 'published_by',
        'published_at', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime', 'end_at' => 'datetime', 'published_at' => 'datetime',
            'archived_at' => 'datetime', 'is_featured' => 'boolean', 'is_high_priority' => 'boolean',
            'is_company_wide' => 'boolean',
        ];
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function publisher(): BelongsTo { return $this->belongsTo(User::class, 'published_by'); }
    public function departments(): BelongsToMany { return $this->belongsToMany(Department::class, 'announcement_department'); }
    public function recipients(): HasMany { return $this->hasMany(AnnouncementRecipient::class); }
    public function attachments(): HasMany { return $this->hasMany(AnnouncementAttachment::class); }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('status', ['scheduled', 'active'])->where(function (Builder $q): void {
            $q->whereNull('end_at')->orWhere('end_at', '>=', now());
        });
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'scheduled' => 'Scheduled', 'active' => 'Active', 'expired' => 'Expired',
            'archived' => 'Archived', default => 'Draft',
        };
    }

    public function audienceLabel(): string
    {
        if ($this->is_company_wide) return 'Company-wide';
        $count = $this->departments->count();
        return $count.' department'.($count === 1 ? '' : 's');
    }
}
