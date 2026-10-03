<?php

namespace App\Services\Platform;

use App\Models\Property;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PlatformPropertyService
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return Property::query()
            ->with('organization:id,name,uuid,status')
            ->withCount(['memberships as access_count' => fn ($query) => $query->where('status', 'active')])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('property_code', 'like', "%{$search}%")
                        ->orWhere('uuid', 'like', "%{$search}%");
                });
            })
            ->when($filters['organization_id'] ?? null, fn ($query, int $organizationId) => $query->where('organization_id', $organizationId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
