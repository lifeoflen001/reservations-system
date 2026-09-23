<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationPreferencesRequest;
use App\Models\UserNotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Support\TablePagination;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('notifications.view'), 403);
        $filter = $request->string('filter')->toString();
        $query = $request->user()->notifications()->latest();
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
        $request->user()->notifications()->whereKey($notification)->update(['read_at' => now()]);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('notifications.manage') || $request->user()->hasPermission('notifications.view'), 403);
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Notifications marked as read.');
    }

    public function updatePreferences(NotificationPreferencesRequest $request): RedirectResponse
    {
        UserNotificationPreference::updateOrCreate(['user_id' => $request->user()->id], ['channels' => $request->input('channels', ['in_app']), 'categories' => $request->input('categories', ['operational', 'financial', 'integrations'])]);

        return back()->with('success', 'Notification preferences saved.');
    }
}
