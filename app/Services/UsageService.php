<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Room;

final class UsageService
{
    public function properties(Organization $organization): int
    {
        return $organization->properties()->where('status', '!=', 'archived')->count();
    }

    public function users(Organization $organization): int
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organization->getKey())
            ->where('status', 'active')
            ->count();
    }

    public function rooms(Organization $organization): int
    {
        return Room::query()
            ->where('is_active', true)
            ->whereHas('property', fn ($query) => $query->where('organization_id', $organization->getKey()))
            ->count();
    }

    public function for(Organization $organization, string $key): int
    {
        return match ($key) {
            'properties' => $this->properties($organization),
            'users' => $this->users($organization),
            'rooms' => $this->rooms($organization),
            default => 0,
        };
    }
}
