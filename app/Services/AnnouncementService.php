<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\User;
use App\Notifications\HotelDatabaseNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AnnouncementService
{
    public const CATEGORIES = ['Company News', 'Policy Updates', 'HR Updates', 'Benefits', 'IT Updates', 'Events', 'Training', 'Finance', 'General'];

    public function save(array $data, User $actor, ?Announcement $announcement = null): Announcement
    {
        return DB::transaction(function () use ($data, $actor, $announcement): Announcement {
            $announcement ??= new Announcement;
            $announcement->fill([
                'title' => trim((string) $data['title']),
                'category' => $data['category'],
                'short_description' => trim((string) $data['short_description']),
                'content' => $this->sanitize((string) $data['content']),
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'] ?? null,
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'is_high_priority' => (bool) ($data['is_high_priority'] ?? false),
                'is_company_wide' => (bool) ($data['is_company_wide'] ?? false),
                'created_by' => $announcement->created_by ?: $actor->id,
            ]);
            $announcement->status = $announcement->status === 'archived' ? 'draft' : ($announcement->status ?: 'draft');
            $announcement->save();
            $announcement->departments()->sync($announcement->is_company_wide ? [] : ($data['department_ids'] ?? []));

            foreach ($data['attachments'] ?? [] as $file) {
                $this->attach($announcement, $file, $actor);
            }

            return $announcement->fresh(['departments', 'attachments']);
        });
    }

    public function publish(Announcement $announcement, User $actor): Announcement
    {
        return DB::transaction(function () use ($announcement, $actor): Announcement {
            $now = now();
            $announcement->update([
                'status' => $announcement->start_at && $announcement->start_at->isFuture() ? 'scheduled' : 'active',
                'published_by' => $actor->id,
                'published_at' => $announcement->published_at ?: $now,
            ]);
            $this->snapshotAndNotify($announcement->fresh(), $actor);
            return $announcement->fresh(['departments', 'creator', 'recipients.user', 'attachments']);
        });
    }

    public function processDue(): int
    {
        $changed = 0;
        Announcement::query()->whereIn('status', ['scheduled', 'active'])->whereNotNull('end_at')->where('end_at', '<', now())->each(function (Announcement $announcement) use (&$changed): void {
            $announcement->update(['status' => 'expired']);
            $changed++;
        });
        Announcement::query()->where('status', 'scheduled')->where('start_at', '<=', now())->each(function (Announcement $announcement) use (&$changed): void {
            $announcement->update(['status' => 'active']);
            $this->snapshotAndNotify($announcement, $announcement->publisher ?: $announcement->creator ?: User::query()->firstOrFail());
            $changed++;
        });
        return $changed;
    }

    public function markViewed(Announcement $announcement, User $user): void
    {
        $recipient = $announcement->recipients()->where('user_id', $user->id)->first();
        if (! $recipient) return;
        $now = now();
        $recipient->increment('view_count');
        $recipient->forceFill(['first_viewed_at' => $recipient->first_viewed_at ?: $now, 'last_viewed_at' => $now])->save();
    }

    public function snapshotAndNotify(Announcement $announcement, User $actor): void
    {
        $users = $this->audience($announcement);
        foreach ($users as $user) {
            $recipient = AnnouncementRecipient::firstOrCreate(
                ['announcement_id' => $announcement->id, 'user_id' => $user->id],
                ['audience_source' => $announcement->is_company_wide ? 'company-wide' : 'department']
            );
            if ($recipient->in_app_sent_at) continue;
            $user->notify(new HotelDatabaseNotification([
                'title' => $announcement->title,
                'message' => $announcement->short_description,
                'severity' => $announcement->is_high_priority ? 'warning' : 'info',
                'category' => 'announcements',
                'entity_type' => Announcement::class,
                'entity_id' => $announcement->id,
                'action_url' => route('announcements.show', $announcement),
            ]));
            $recipient->update(['in_app_sent_at' => now()]);
            if ($actor->hasPermission('announcements.send_email')) {
                $this->queueEmailIfPreferred($announcement, $recipient, $user);
            }
        }
    }

    /** @return Collection<int, User> */
    public function audience(Announcement $announcement): Collection
    {
        $query = User::query()->where('is_active', true)->with('notificationPreferences');
        if (! $announcement->is_company_wide) {
            $ids = $announcement->departments()->pluck('departments.id');
            if ($ids->isEmpty()) return new Collection;
            $query->whereIn('department_id', $ids);
        }
        return $query->orderBy('id')->get();
    }

    private function queueEmailIfPreferred(Announcement $announcement, AnnouncementRecipient $recipient, User $user): void
    {
        $preferences = $user->notificationPreferences;
        if (! in_array('email', $preferences?->channels ?? [], true)) return;
        if (! app(HotelEmailService::class)->isDeliverableRecipient($user->email)) {
            $recipient->update(['email_status' => 'skipped']);
            return;
        }
        $log = app(HotelEmailService::class)->queue('announcement', $user->email, [
            'announcement_title' => $announcement->title,
            'announcement_message' => $announcement->short_description,
            'property_name' => app(PropertySettingsService::class)->name(),
            'action_url' => route('announcements.show', $announcement),
            'notification_title' => $announcement->title,
        ]);
        $log?->update(['announcement_recipient_id' => $recipient->id]);
        $wasSentSynchronously = $log?->status === 'sent';
        $recipient->update([
            'email_status' => $log ? ($wasSentSynchronously ? 'sent' : 'queued') : 'skipped',
            'email_sent_at' => $log && $wasSentSynchronously ? now() : null,
        ]);
    }

    private function attach(Announcement $announcement, UploadedFile $file, User $actor): void
    {
        $stored = $file->store('announcements/'.$announcement->id, 'local');
        $announcement->attachments()->create([
            'disk' => 'local', 'stored_name' => $stored, 'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize() ?: 0, 'uploaded_by' => $actor->id,
        ]);
    }

    private function sanitize(string $html): string
    {
        $allowed = '<p><br><strong><b><em><i><u><ol><ul><li><a><h2><h3><blockquote><span>';
        $clean = strip_tags($html, $allowed);
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/javascript\s*:/i', '', $clean) ?? $clean;
        $clean = preg_replace_callback('/href\s*=\s*(["\'])(.*?)\1/i', function (array $match): string {
            $href = trim($match[2]);
            $safe = preg_match('/^(?:https?:\/\/|\/|#)/i', $href) ? $href : '#';
            return 'href='.$match[1].e($safe).$match[1];
        }, $clean) ?? $clean;
        return trim($clean);
    }
}
