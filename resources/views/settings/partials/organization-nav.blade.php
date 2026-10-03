<nav class="settings-scope-nav" aria-label="Organization administration">
    @can('organization.view')<a class="{{ request()->routeIs('settings.organization.*') ? 'is-active' : '' }}" href="{{ route('settings.organization.index') }}">Organization</a>@endcan
    @can('organization.view')<a class="{{ request()->routeIs('settings.subscription') ? 'is-active' : '' }}" href="{{ route('settings.subscription') }}">Subscription</a>@endcan
    @can('properties.view')<a class="{{ request()->routeIs('settings.properties.*') ? 'is-active' : '' }}" href="{{ route('settings.properties.index') }}">Properties</a>@endcan
    @can('members.view')<a class="{{ request()->routeIs('settings.members.*', 'settings.properties.access*') ? 'is-active' : '' }}" href="{{ route('settings.members.index') }}">Members &amp; access</a>@endcan
    @can('audit.view')<a class="{{ request()->routeIs('settings.audit.*') ? 'is-active' : '' }}" href="{{ route('settings.audit.index') }}">Audit</a>@endcan
</nav>
