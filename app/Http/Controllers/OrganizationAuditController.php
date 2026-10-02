<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\Tenancy\MembershipAccessService;
use App\Support\TablePagination;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationAuditController extends Controller
{
    public function index(Request $request, MembershipAccessService $access): View
    {
        $organization = $access->currentOrganizationFor($request->user());
        $logs = $organization->auditLogs()
            ->with(['actor:id,name,first_name,last_name', 'targetUser:id,name,first_name,last_name', 'property:id,name'])
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('to')))
            ->when($request->filled('user_id'), fn ($query) => $query->where(fn ($userQuery) => $userQuery->where('actor_user_id', $request->integer('user_id'))->orWhere('target_user_id', $request->integer('user_id'))))
            ->when($request->filled('property_id'), fn ($query) => $query->where('property_id', $request->integer('property_id')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->input('action')))
            ->latest('created_at')
            ->paginate(TablePagination::perPage($request, 25))->withQueryString();

        return view('settings.audit', [
            'organization' => $organization,
            'logs' => $logs,
            'members' => $access->members($organization)->with('user:id,name,first_name,last_name')->get(),
            'properties' => Property::query()->where('organization_id', $organization->getKey())->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
