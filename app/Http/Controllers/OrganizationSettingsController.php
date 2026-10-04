<?php

namespace App\Http\Controllers;

use App\Services\Tenancy\MembershipAccessService;
use App\Services\EntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationSettingsController extends Controller
{
    public function index(Request $request, MembershipAccessService $access): View
    {
        $organization = $access->currentOrganizationFor($request->user());

        return view('settings.organization', compact('organization'));
    }

    public function update(Request $request, MembershipAccessService $access): RedirectResponse
    {
        $organization = $access->currentOrganizationFor($request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'billing_email' => ['nullable', 'email', 'max:254'],
            'country' => ['nullable', 'string', 'max:100'],
            'timezone' => ['nullable', 'timezone', 'max:64'],
            'default_currency' => ['nullable', 'string', 'max:10'],
        ]);

        $before = $organization->only(array_keys($data));
        $organization->update($data);
        $access->audit($organization->fresh(), $request->user(), 'organization.updated', null, $organization, [
            'changed_fields' => collect($data)->filter(fn ($value, $key) => (string) ($before[$key] ?? '') !== (string) ($value ?? ''))->keys()->values()->all(),
        ]);

        return back()->with('success', 'Organization settings updated.');
    }

    public function subscription(Request $request, MembershipAccessService $access, EntitlementService $entitlements): View
    {
        $organization = $access->currentOrganizationFor($request->user());
        return view('settings.subscription', [
            'organization' => $organization,
            'entitlements' => $entitlements->snapshot($organization),
        ]);
    }
}
