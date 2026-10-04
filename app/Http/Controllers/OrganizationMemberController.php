<?php

namespace App\Http\Controllers;

use App\Models\OrganizationMembership;
use App\Models\Property;
use App\Models\Role;
use App\Services\Tenancy\MembershipAccessService;
use App\Services\Tenancy\OrganizationOwnershipService;
use App\Support\TablePagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationMemberController extends Controller
{
    public function index(Request $request, MembershipAccessService $access): View
    {
        $organization = $access->currentOrganizationFor($request->user());
        $members = $access->members($organization)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->input('search')).'%';
                $query->whereHas('user', fn ($user) => $user->where('name', 'like', $term)->orWhere('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when(in_array($request->input('status'), ['active', 'inactive'], true), fn ($query) => $query->where('status', $request->input('status')))
            ->paginate(TablePagination::perPage($request, 20))->withQueryString();

        return view('settings.members', [
            'organization' => $organization,
            'members' => $members,
        ]);
    }

    public function edit(Request $request, OrganizationMembership $membership, MembershipAccessService $access, OrganizationOwnershipService $ownership): View
    {
        $organization = $access->currentOrganizationFor($request->user());
        $membership = $access->assertMember($membership, $organization);

        return view('settings.member-edit', [
            'organization' => $organization,
            'membership' => $membership,
            'canManageOwnership' => $ownership->canManageOwnership($request->user()),
            'properties' => Property::query()->where('organization_id', $organization->getKey())->orderBy('name')->get(['id', 'name', 'property_code', 'status']),
            'roles' => Role::query()->where('is_active', true)->orderBy('label')->get(['id', 'label', 'name']),
        ]);
    }

    public function update(Request $request, OrganizationMembership $membership, MembershipAccessService $access): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['nullable', 'integer'],
            'status' => ['required', 'in:active,inactive'],
            'property_ids' => ['array'],
            'property_ids.*' => ['integer'],
        ]);
        $access->updateMembership($membership, $request->user(), $data['role_id'] ?? null, $data['status'], $data['property_ids'] ?? []);

        return redirect()->route('settings.members.index')->with('success', 'Organization access updated.');
    }

    public function grantOwner(Request $request, OrganizationMembership $membership, OrganizationOwnershipService $ownership): RedirectResponse
    {
        $ownership->grantOwner($membership, $request->user());

        return redirect()->route('settings.members.edit', $membership)->with('success', 'Organization owner access granted.');
    }

    public function removeOwner(Request $request, OrganizationMembership $membership, OrganizationOwnershipService $ownership): RedirectResponse
    {
        $ownership->removeOwner($membership, $request->user());

        return redirect()->route('settings.members.edit', $membership)->with('success', 'Organization owner access removed.');
    }
}
