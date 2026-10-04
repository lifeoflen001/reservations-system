<?php

namespace App\Services\Platform;

use App\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PlatformSubscriptionService
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return Subscription::query()
            ->with(['organization:id,name,uuid,status', 'plan:id,name,code,status'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['organization_id'] ?? null, fn ($query, int $organizationId) => $query->where('organization_id', $organizationId))
            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
