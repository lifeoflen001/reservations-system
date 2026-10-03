<nav class="platform-support-nav" aria-label="Support workspace modules">
    <a href="{{ route('platform.support.workspace', $session) }}">Overview</a>
    @foreach(['reservations'=>'Reservations','clients'=>'Clients','rooms'=>'Rooms','room-planning'=>'Room planning','tasks'=>'Tasks','housekeeping'=>'Housekeeping','maintenance'=>'Maintenance','pos'=>'POS','finance'=>'Finance','reports'=>'Reports','settings'=>'Settings'] as $key => $label)
        <a href="{{ route('platform.support.workspace.module', [$session, $key]) }}">{{ $label }}</a>
    @endforeach
    <form class="platform-support-search" method="GET" action="{{ route('platform.support.workspace.search', $session) }}"><label class="sr-only" for="support-search">Search support workspace</label><input class="form-control" id="support-search" name="q" value="{{ request('q') }}" placeholder="Search this property"><button class="ui-button ui-button--secondary ui-button--compact" type="submit">Search</button></form>
</nav>
