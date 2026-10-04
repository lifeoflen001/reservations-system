<?php

namespace App\Http\Controllers;

use App\Models\OrganizationInvitation;
use App\Services\InvitationService;
use App\Services\Tenancy\MembershipAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationInvitationController extends Controller
{
    public function show(Request $request, string $token): View|RedirectResponse
    {
        $invitation = OrganizationInvitation::query()->where('token_hash', hash('sha256', $token))->with(['organization', 'role'])->firstOrFail();
        if (! $invitation->isUsable()) return view('invitations.invalid');
        $request->session()->put('pending_invitation_token', $token);
        if (! $request->user()) return redirect()->route('register');
        if (! $request->user()->hasVerifiedEmail()) return redirect()->route('verification.notice');
        return view('invitations.accept', compact('invitation', 'token'));
    }

    public function accept(Request $request, string $token, InvitationService $service): RedirectResponse
    {
        $service->accept($request->user(), $token);
        $request->session()->forget('pending_invitation_token');
        return redirect()->route('post-auth')->with('success', 'You now have access to the organization workspace.');
    }

    public function index(Request $request, MembershipAccessService $access): View
    {
        $organization = $access->currentOrganizationFor($request->user());
        $invitations = $organization->invitations()->with(['role', 'inviter', 'properties'])->latest()->paginate(20)->withQueryString();
        return view('settings.invitations', ['organization' => $organization, 'invitations' => $invitations, 'properties' => $organization->properties()->where('status', 'active')->orderBy('name')->get(), 'roles' => \App\Models\Role::query()->where('is_active', true)->orderBy('label')->get()]);
    }

    public function store(Request $request, MembershipAccessService $access, InvitationService $service): RedirectResponse
    {
        $organization = $access->currentOrganizationFor($request->user());
        $data = $request->validate(['email' => ['required', 'email', 'max:254'], 'role_id' => ['required', 'integer'], 'property_ids' => ['required', 'array', 'min:1'], 'property_ids.*' => ['integer']]);
        $service->create($request->user(), $organization, $data['email'], (int) $data['role_id'], $data['property_ids']);
        return back()->with('success', 'Invitation sent.');
    }

    public function resend(Request $request, OrganizationInvitation $invitation, MembershipAccessService $access, InvitationService $service): RedirectResponse
    {
        $organization = $access->currentOrganizationFor($request->user());
        abort_unless((int) $invitation->organization_id === (int) $organization->getKey(), 404);
        $service->resend($invitation, $request->user());
        return back()->with('success', 'A new invitation link was sent.');
    }

    public function revoke(Request $request, OrganizationInvitation $invitation, MembershipAccessService $access, InvitationService $service): RedirectResponse
    {
        $organization = $access->currentOrganizationFor($request->user());
        abort_unless((int) $invitation->organization_id === (int) $organization->getKey(), 404);
        $service->revoke($invitation, $request->user());
        return back()->with('success', 'Invitation revoked.');
    }
}
