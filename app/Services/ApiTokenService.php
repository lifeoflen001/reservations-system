<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\PropertyMembership;
use App\Services\Tenancy\TenantContext;

class ApiTokenService
{
    public function issue(User $user, string $name, array $abilities): array
    {
        $plain = bin2hex(random_bytes(32));
        $context = app(TenantContext::class);
        if ($context->organizationId() === null || $context->propertyId() === null) {
            $membership = OrganizationMembership::query()
                ->where('user_id', $user->getKey())
                ->where('status', 'active')
                ->with(['propertyAccess' => fn ($query) => $query->where('status', 'active')->orderBy('property_id')])
                ->orderBy('organization_id')
                ->first();
            $propertyAccess = $membership?->propertyAccess->first();
            if ($membership && $propertyAccess) {
                $context->activate((int) $membership->organization_id, (int) $propertyAccess->property_id);
            }
        }
        $token = new ApiToken([
            'user_id' => $user->id,
            'name' => $name,
            'token_prefix' => substr($plain, 0, 12),
            'token_hash' => hash('sha256', $plain),
            'abilities' => array_values(array_unique($abilities)),
        ]);
        // Ownership is service-controlled, never mass-assigned from a
        // browser/API payload.
        $token->forceFill([
            'organization_id' => $context->requireOrganization()->getKey(),
            'property_id' => $context->requireProperty()->getKey(),
        ])->save();

        return [$token, $plain];
    }
}
