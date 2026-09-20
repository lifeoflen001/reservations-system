@php
    $currentUser = auth()->user();
    $userName = $currentUser->display_name;
    $initials = collect(preg_split('/\s+/', trim($userName)))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('');
@endphp

<header class="topbar">
    <button class="topbar__menu" type="button" data-sidebar-toggle aria-label="Toggle navigation" data-tooltip="Toggle navigation"><x-ui.icon name="menu" size="20" /></button>
    <form class="global-search" action="{{ route('search') }}" method="GET" role="search" data-global-search>
        <button type="submit" class="global-search__submit" aria-label="Search" data-tooltip="Search"><x-ui.icon name="search" size="19" /></button>
        <input type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="Search anywhere..." aria-label="Search anywhere" data-global-search-input>
        <kbd>Ctrl+K</kbd>
    </form>

    <div class="topbar__actions">
        <div class="topbar__date"><x-ui.icon name="calendar" size="18" /><span data-live-clock data-timezone="{{ config('app.timezone') }}">{{ now()->format('M d, Y h:i A') }}</span></div>
        <button type="button" class="topbar__icon" data-theme-toggle aria-label="Toggle dark mode" data-tooltip="Toggle dark mode"><x-ui.icon name="moon" size="19" /></button>
        <div class="dropdown" data-dropdown>
            <div class="dropdown__menu dropdown__menu--language" data-dropdown-menu hidden>
                <span class="dropdown__label">Language</span>
                <button type="button" class="dropdown__item is-selected">{{ \App\Models\Language::where('code', app()->getLocale())->value('name') ?? strtoupper(app()->getLocale()) }} <span>{{ strtoupper(app()->getLocale()) }}</span></button>
            </div>
        </div>
        <button type="button" class="topbar__icon" data-refresh aria-label="Refresh data" data-tooltip="Refresh data"><x-ui.icon name="refresh" size="19" /></button>
        <button type="button" class="topbar__icon topbar__icon--fullscreen" data-fullscreen-toggle aria-label="Enter fullscreen" data-tooltip="Enter fullscreen">
            <x-ui.icon name="fullscreen" size="19" data-fullscreen-enter />
            <x-ui.icon name="fullscreen-exit" size="19" data-fullscreen-exit hidden />
        </button>
        @if(auth()->user()->hasPermission('notifications.view'))
        @php($unreadCount = auth()->user()->unreadNotifications()->count())
        @php($unreadNotifications = auth()->user()->unreadNotifications()->latest()->limit(5)->get())
        <div class="dropdown notifications-dropdown" data-dropdown>
            <button type="button" class="topbar__icon notification-toggle" data-dropdown-toggle aria-expanded="false" aria-label="Notifications" data-tooltip="Notifications"><x-ui.icon name="bell" size="19" />@if($unreadCount > 0)<span class="notification-count">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>@endif</button>
            <div class="dropdown__menu dropdown__menu--notifications" data-dropdown-menu hidden><div class="notification-menu__heading"><div class="notification-menu__title"><span class="notification-menu__title-icon"><x-ui.icon name="bell" size="16" /></span><div><strong>Notifications</strong>@if($unreadCount > 0)<span>{{ $unreadCount }} unread</span>@else<span>All caught up</span>@endif</div></div><div class="notification-menu__actions">@if($unreadCount > 0)<form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="notification-menu__action" type="submit">Mark all read</button></form>@endif<a class="notification-menu__action" href="{{ route('notifications.index') }}">View all</a></div></div><div class="notification-menu__list">@forelse($unreadNotifications as $notification)<a class="notification-menu__item" href="{{ route('notifications.index') }}"><span class="notification-menu__indicator" aria-hidden="true"></span><span class="notification-menu__copy"><strong>{{ $notification->data['title'] ?? 'Notification' }}</strong><time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time></span><span class="notification-menu__arrow"><x-ui.icon name="chevron-right" size="15" /></span></a>@empty<div class="notification-menu__empty"><span class="notification-menu__empty-icon"><x-ui.icon name="check" size="16" /></span><strong>You're all caught up</strong><small>No new notifications right now.</small></div>@endforelse</div><a class="notification-menu__footer" href="{{ route('notifications.index') }}">Open notification center <x-ui.icon name="arrow-right" size="14" /></a></div>
        </div>
        @endif

        <div class="dropdown profile-dropdown" data-dropdown>
            <button type="button" class="profile-control" data-dropdown-toggle aria-expanded="false">
                <span class="avatar {{ $currentUser->avatar_path ? 'avatar--image' : '' }}">@if($currentUser->avatar_path)<img src="{{ route('profile.avatar') }}?v={{ $currentUser->updated_at?->timestamp }}" alt="{{ $userName }}">@else{{ $initials ?: 'U' }}@endif</span>
                <span class="profile-copy"><strong>{{ $userName }}</strong><small>{{ auth()->user()->role?->label ?? 'Administrator' }}</small></span>
            </button>
            <div class="dropdown__menu dropdown__menu--profile" data-dropdown-menu hidden>
               <!-- <div class="profile-menu__identity"><span class="avatar avatar--small">{{ $initials ?: 'U' }}</span><span><strong>{{ $userName }}</strong><small>{{ auth()->user()->role?->label ?? 'Administrator' }}</small></span></div>
                <div class="dropdown__separator"></div> -->
                <a class="dropdown__item" href="{{ route('profile') }}"><x-ui.icon name="users" size="16" /> My profile</a>
                <a class="dropdown__item" href="{{ route('password.change') }}"><x-ui.icon name="key" size="16" /> Change password</a>
                <div class="dropdown__separator"></div>
                <form method="POST" action="{{ route('logout') }}"><button type="submit" class="dropdown__item dropdown__item--danger"><x-ui.icon name="logout" size="16" /> Sign out</button>@csrf</form>
            </div>
        </div>
    </div>
</header>
