<?php

namespace App\Http\Controllers;

use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantContextSwitchRedirector;
use App\Services\Tenancy\MembershipAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantContextController extends Controller
{
    public function property(Request $request, TenantContext $context, TenantContextSwitchRedirector $redirector, MembershipAccessService $access): RedirectResponse
    {
        abort_unless(config('hotel.tenancy.multi_property_ui'), 404);
        $data = $request->validate(['property_id' => ['required', 'integer', 'exists:properties,id'], 'return_to' => ['nullable', 'string', 'max:2048']]);

        $fromOrganizationId = $context->organizationId();
        $fromPropertyId = $context->propertyId();
        try {
            $context->setProperty(\App\Models\Property::query()->findOrFail($data['property_id']));
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            return $redirector->response($request)->with('error', 'This property is no longer available to your account.');
        }

        $context->clearTenantSensitiveSessionState();
        if ($fromPropertyId !== $context->propertyId()) {
            $access->audit($context->requireOrganization(), $request->user(), 'tenant.property_switched', null, $context->currentProperty(), [
                'from_organization_id' => $fromOrganizationId,
                'from_property_id' => $fromPropertyId,
                'to_organization_id' => $context->organizationId(),
                'to_property_id' => $context->propertyId(),
            ]);
        }

        return $redirector->response($request);
    }

    public function organization(Request $request, TenantContext $context, TenantContextSwitchRedirector $redirector, MembershipAccessService $access): RedirectResponse
    {
        abort_unless(config('hotel.tenancy.multi_property_ui'), 404);
        $data = $request->validate(['organization_id' => ['required', 'integer', 'exists:organizations,id'], 'return_to' => ['nullable', 'string', 'max:2048']]);

        $fromOrganizationId = $context->organizationId();
        $fromPropertyId = $context->propertyId();
        try {
            $context->setOrganization(\App\Models\Organization::query()->findOrFail($data['organization_id']));
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            return $redirector->response($request)->with('error', 'This organization is no longer available to your account.');
        }

        $context->clearTenantSensitiveSessionState();
        if ($fromOrganizationId !== $context->organizationId() || $fromPropertyId !== $context->propertyId()) {
            $access->audit($context->requireOrganization(), $request->user(), 'tenant.organization_switched', null, $context->currentProperty(), [
                'from_organization_id' => $fromOrganizationId,
                'from_property_id' => $fromPropertyId,
                'to_organization_id' => $context->organizationId(),
                'to_property_id' => $context->propertyId(),
            ]);
        }

        return $redirector->response($request);
    }
}
