<?php

namespace App\Services\Platform;

use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PlatformOrganizationService
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return Organization::query()
            ->with(['subscriptions' => fn ($query) => $query->latest('id')->with('plan')])
            ->withCount(['properties', 'users as user_count', 'memberships as owner_count' => fn ($query) => $query->where('is_owner', true)->where('status', 'active')])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('uuid', 'like', "%{$search}%")
                        ->orWhere('billing_email', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['subscription_status'] ?? null, fn ($query, string $status) => $query->where('subscription_status', $status))
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
