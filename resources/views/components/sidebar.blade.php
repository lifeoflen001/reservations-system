@php
    use App\Models\Client;
    use App\Models\HousekeepingTask;
    use App\Models\MaintenanceTask;
    use App\Models\Reservation;
    use App\Models\Room;
    use App\Models\User;

    $groups = [
        'Main' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'grid'],
            ['label' => 'Reservations', 'route' => 'reservations.*', 'icon' => 'calendar'],
            ['label' => 'Room Planning', 'route' => 'room-planning.*', 'icon' => 'calendar'],
        ],
        'Operations' => [
            ['label' => 'Clients', 'route' => 'clients.*', 'icon' => 'users'],
            ['label' => 'Rooms', 'route' => 'rooms.*', 'icon' => 'bed'],
            ['label' => 'Tasks', 'route' => 'tasks.*', 'icon' => 'check-square'],
            ['label' => 'Housekeeping', 'route' => 'housekeeping.*', 'icon' => 'broom'],
            ['label' => 'Maintenance', 'route' => 'maintenance.*', 'icon' => 'wrench'],
            ['label' => 'POS', 'route' => 'pos.*', 'href' => 'pos.terminal', 'icon' => 'card'],
        ],
        'Management' => [
            ['label' => 'Announcements', 'route' => 'announcements.*', 'icon' => 'bell'],
            ['label' => 'Staff', 'route' => 'staff.*', 'icon' => 'users'],
            ['label' => 'Payments', 'route' => 'payments.*', 'icon' => 'card'],
            ['label' => 'Reports', 'route' => 'reports.*', 'icon' => 'chart'],
        ],
        'System' => [
            ['label' => 'Settings', 'route' => 'settings.*', 'icon' => 'settings'],
        ],
    ];
@endphp
@php
    $edition = app(\App\Services\SystemSettingsService::class)->get('edition', 'Pro (Development)');
@endphp

<aside class="sidebar" data-sidebar aria-label="Primary navigation">
    <div class="sidebar__brand">
        <x-app-logo />
    </div>
    <button class="sidebar__close" type="button" data-sidebar-close aria-label="Close navigation" data-tooltip="Close navigation"><x-ui.icon name="plus" size="20" /></button>

    <nav class="sidebar__nav">
        @foreach ($groups as $group => $items)
            @php
                $visibleItems = collect($items)->filter(function ($item) {
                    return match ($item['label']) {
                        'Dashboard' => Gate::allows('dashboard.view'),
                        'Reservations' => Gate::allows('viewAny', Reservation::class),
                        'Room Planning' => Gate::allows('room_planning.view'),
                        'Clients' => Gate::allows('viewAny', Client::class),
                        'Rooms' => Gate::allows('viewAny', Room::class),
                        'Tasks' => Gate::allows('viewAny', \App\Models\Task::class),
                        'Housekeeping' => Gate::allows('viewAny', HousekeepingTask::class),
                        'Maintenance' => Gate::allows('viewAny', MaintenanceTask::class),
                        'POS' => auth()->user()->hasPermission('pos.access') || auth()->user()->hasPermission('pos.sell'),
                        'Announcements' => auth()->user()->hasPermission('announcements.view') || auth()->user()->hasPermission('announcements.manage'),
                        'Staff' => Gate::allows('viewAny', User::class),
                        'Payments' => auth()->user()->hasPermission('payments.view') || auth()->user()->hasPermission('payments.manage'),
                        'Reports' => auth()->user()->hasPermission('reports.view') || auth()->user()->hasPermission('reports.manage'),
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
        <div class="edition-row"><span>Edition</span><x-ui.badge variant="brand">{{ $edition }}</x-ui.badge></div>
        <small>v{{ config('app.version', '43.1.0') }}</small>
    </div>
</aside>
<div class="sidebar-backdrop" data-sidebar-backdrop></div>
