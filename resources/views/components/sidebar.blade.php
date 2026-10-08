@php
    use App\Models\Client;
    use App\Models\HousekeepingTask;
    use App\Models\MaintenanceTask;
    use App\Models\Reservation;
    use App\Models\Room;
    use App\Models\User;

    $groups = [
        'Overview' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'grid'],
        ],
        'Front Desk' => [
            ['label' => 'Reservations', 'route' => 'reservations.*', 'icon' => 'calendar'],
            ['label' => 'Rooms', 'route' => 'rooms.*', 'icon' => 'bed'],
            ['label' => 'Room Planning', 'route' => 'room-planning.*', 'icon' => 'calendar'],
            ['label' => 'Clients', 'route' => 'clients.*', 'icon' => 'users'],
        ],
        'Operations' => [
            ['label' => 'Housekeeping', 'route' => 'housekeeping.*', 'icon' => 'broom'],
            ['label' => 'Maintenance', 'route' => 'maintenance.*', 'icon' => 'wrench'],
            ['label' => 'Announcements', 'route' => 'announcements.*', 'icon' => 'bell'],
            ['label' => 'Tasks', 'route' => 'tasks.*', 'icon' => 'check-square'],
        ],
        'Finance' => [
            ['label' => 'Payments', 'route' => 'payments.*', 'icon' => 'card'],
            ['label' => 'Finance', 'route' => 'finance.*', 'href' => 'finance.overview', 'icon' => 'currency'],
            ['label' => 'POS', 'route' => 'pos.*', 'href' => 'pos.terminal', 'icon' => 'card'],
        ],
        'Team' => [
            ['label' => 'Staff', 'route' => 'staff.*', 'icon' => 'users'],
        ],
        'System' => [
            ['label' => 'Settings', 'route' => 'settings.*', 'icon' => 'settings'],
            ['label' => 'Website', 'route' => 'website.*', 'href' => 'website.dashboard', 'icon' => 'document'],
        ],
    ];
@endphp
@php
    $entitlementService = app(\App\Services\EntitlementService::class);
    $currentOrganization = app(\App\Services\Tenancy\TenantContext::class)->currentOrganization();
    $hasFeature = static fn (string $feature): bool => $currentOrganization !== null && $entitlementService->hasFeature($currentOrganization, $feature);
@endphp

<aside class="sidebar" data-sidebar aria-label="Primary navigation">
    <div class="sidebar__brand">
        <x-app-logo />
    </div>
    <button class="sidebar__close" type="button" data-sidebar-close aria-label="Close navigation" data-tooltip="Close navigation"><x-ui.icon name="plus" size="20" /></button>

    <nav class="sidebar__nav">
        @foreach ($groups as $group => $items)
            @php
                $visibleItems = collect($items)->filter(function ($item) use ($hasFeature) {
                    return match ($item['label']) {
                        'Dashboard' => Gate::allows('dashboard.view'),
                        'Reservations' => Gate::allows('viewAny', Reservation::class),
                        'Room Planning' => Gate::allows('room_planning.view'),
                        'Clients' => Gate::allows('viewAny', Client::class),
                        'Rooms' => Gate::allows('viewAny', Room::class),
                        'Tasks' => Gate::allows('viewAny', \App\Models\Task::class),
                        'Housekeeping' => Gate::allows('viewAny', HousekeepingTask::class),
                        'Maintenance' => Gate::allows('viewAny', MaintenanceTask::class),
                        'POS' => $hasFeature('pos') && (auth()->user()->hasPermission('pos.access') || auth()->user()->hasPermission('pos.sell')),
                        'Announcements' => auth()->user()->hasPermission('announcements.view') || auth()->user()->hasPermission('announcements.manage'),
                        'Staff' => Gate::allows('viewAny', User::class),
                        'Payments' => auth()->user()->hasPermission('payments.view') || auth()->user()->hasPermission('payments.manage'),
                        'Finance' => $hasFeature('finance') && auth()->user()->hasPermission('finance.view'),
                        'Website' => auth()->user()->hasPermission('website.view'),
                        'Settings' => auth()->user()->hasPermission('settings.view') || auth()->user()->hasPermission('settings.manage'),
                        default => false,
                    };
                })
            @endphp
            @if($visibleItems->isNotEmpty())
                <div class="nav-group">
                    <p class="nav-group__label">{{ $group }}</p>
                    @foreach ($visibleItems as $item)
                        @php($active = request()->routeIs($item['route']))
                        @php($hrefRoute = $item['href'] ?? str_replace('.*', '.index', $item['route']))
                        <a class="nav-item {{ $active ? 'is-active' : '' }}" href="{{ Route::has($hrefRoute) ? route($hrefRoute) : '#' }}" aria-label="{{ $item['label'] }}" @if($active) aria-current="page" @endif data-tooltip="{{ $item['label'] }}">
                            <x-ui.icon :name="$item['icon']" size="19" />
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        @endforeach
    </nav>

    <div class="sidebar__footer">
        <small>v{{ config('app.version', '43.1.0') }}</small>
    </div>
</aside>
<div class="sidebar-backdrop" data-sidebar-backdrop></div>
