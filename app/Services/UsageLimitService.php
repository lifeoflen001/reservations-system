<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Validation\ValidationException;

final class UsageLimitService
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function canCreateProperty(Organization $organization): bool
    {
        return $this->entitlements->canConsume($organization, 'properties');
    }

    public function canCreateUser(Organization $organization): bool
    {
        return $this->entitlements->canConsume($organization, 'users');
    }

    public function canCreateRoom(Organization $organization): bool
    {
        return $this->entitlements->canConsume($organization, 'rooms');
    }

    public function assertCanConsume(Organization $organization, string $key, int $increment = 1): void
    {
        if ($this->entitlements->canConsume($organization, $key, $increment)) return;

        $limit = $this->entitlements->limit($organization, $key);
        $label = match ($key) { 'properties' => 'properties', 'users' => 'users', 'rooms' => 'rooms', default => $key };
        throw ValidationException::withMessages(['limit' => "Your current plan allows up to {$limit} {$label}."]);
    }
}
