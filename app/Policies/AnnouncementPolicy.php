<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('announcements.view'); }
    public function view(User $user, Announcement $announcement): bool { return $this->viewAny($user) || $announcement->recipients()->where('user_id', $user->id)->exists(); }
    public function create(User $user): bool { return $user->hasPermission('announcements.create') || $user->hasPermission('announcements.manage'); }
    public function update(User $user, Announcement $announcement): bool { return $user->hasPermission('announcements.update') || $user->hasPermission('announcements.manage'); }
    public function publish(User $user, Announcement $announcement): bool { return $user->hasPermission('announcements.publish') || $user->hasPermission('announcements.manage'); }
    public function archive(User $user, Announcement $announcement): bool { return $user->hasPermission('announcements.archive') || $user->hasPermission('announcements.manage'); }
    public function statistics(User $user, Announcement $announcement): bool { return $user->hasPermission('announcements.statistics') || $user->hasPermission('announcements.manage'); }
}
