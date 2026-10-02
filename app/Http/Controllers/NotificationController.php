<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationPreferencesRequest;
use App\Models\UserNotificationPreference;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Support\TablePagination;
use App\Services\Tenancy\TenantContext;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('notifications.view'), 403);
        $filter = $request->string('filter')->toString();
        $query = $this->tenantNotifications($request->user()->notifications());
        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }
        if (in_array($filter, ['operational', 'financial', 'integrations', 'announcements'], true)) {
            $query->where('data', 'like', '%"category":"'.$filter.'"%');
        }

        return view('notifications.index', ['notifications' => $query->paginate(TablePagination::perPage($request, 20))->withQueryString(), 'filter' => $filter]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('notifications.view'), 403);
        $this->tenantNotifications($request->user()->notifications())
            ->whereKey($notification)
            ->update(['read_at' => now()]);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('notifications.manage') || $request->user()->hasPermission('notifications.view'), 403);
        $this->tenantNotifications($request->user()->unreadNotifications())->update(['read_at' => now()]);

        return back()->with('success', 'Notifications marked as read.');
    }

    public function updatePreferences(NotificationPreferencesRequest $request): RedirectResponse
    {
        UserNotificationPreference::updateOrCreate(['user_id' => $request->user()->id], ['channels' => $request->input('channels', ['in_app']), 'categories' => $request->input('categories', ['operational', 'financial', 'integrations'])]);

        return back()->with('success', 'Notification preferences saved.');
    }

    private function tenantNotifications(\Illuminate\Database\Eloquent\Relations\MorphMany $query): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        $context = app(TenantContext::class);
        $organizationId = $context->organizationId();
        $propertyId = $context->propertyId();

        if ($organizationId === null) {
            // Empty legacy/test fixtures predate tenant rows. Once an
            // organization exists, a missing context must fail closed.
            return Organization::query()->exists() ? $query->whereRaw('1 = 0') : $query;
        }

        return $query
            ->where('organization_id', $organizationId)
            ->where(function ($builder) use ($propertyId): void {
                $builder->whereNull('property_id');
                if ($propertyId !== null) {
                    $builder->orWhere('property_id', $propertyId);
                }
            });
    }
}
