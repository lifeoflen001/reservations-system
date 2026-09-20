<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('rooms.view') || $user->hasPermission('rooms.manage'); }
    public function view(User $user, Room $room): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $user->hasPermission('rooms.create') || $user->hasPermission('rooms.manage'); }
    public function update(User $user, Room $room): bool { return $user->hasPermission('rooms.update') || $user->hasPermission('rooms.manage'); }
    public function delete(User $user, Room $room): bool { return $user->hasPermission('rooms.archive') || $user->hasPermission('rooms.manage'); }
    public function manageStatus(User $user, Room $room): bool { return $user->hasPermission('rooms.manage_status') || $user->hasPermission('rooms.manage'); }
}
