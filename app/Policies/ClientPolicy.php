<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('clients.view') || $user->hasPermission('clients.manage'); }
    public function view(User $user, Client $client): bool { return $this->viewAny($user); }
    public function viewSensitive(User $user, Client $client): bool { return $user->hasPermission('clients.view_sensitive') || $user->hasPermission('clients.manage'); }
    public function create(User $user): bool { return $user->hasPermission('clients.create') || $user->hasPermission('clients.manage'); }
    public function update(User $user, Client $client): bool { return $user->hasPermission('clients.update') || $user->hasPermission('clients.manage'); }
    public function delete(User $user, Client $client): bool { return $user->hasPermission('clients.archive') || $user->hasPermission('clients.manage'); }
}
