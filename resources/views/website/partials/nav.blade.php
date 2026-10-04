<div class="website-module-layout">
    <aside class="website-module-sidebar" aria-label="Website administration">
        <div class="website-module-sidebar__heading">
            <span class="website-module-sidebar__mark"><x-ui.icon name="building" size="17" /></span>
            <div><strong>Website</strong><small>Public site manager</small></div>
        </div>
        <nav class="website-module-sidebar__nav">
            @can('website.view')
                <a class="website-module-link {{ request()->routeIs('website.dashboard') ? 'is-active' : '' }}" href="{{ route('website.dashboard') }}"><x-ui.icon name="grid" size="16" /><span>Overview</span></a>
            @endcan
            @can('website.pages.manage')
                <a class="website-module-link {{ request()->routeIs('website.pages.*') ? 'is-active' : '' }}" href="{{ route('website.pages.index') }}"><x-ui.icon name="document" size="16" /><span>Pages</span></a>
            @endcan
            @can('website.seo.manage')
                <a class="website-module-link {{ request()->routeIs('website.seo') ? 'is-active' : '' }}" href="{{ route('website.seo') }}"><x-ui.icon name="search" size="16" /><span>SEO</span></a>
            @endcan
            @can('website.navigation.manage')
                <a class="website-module-link {{ request()->routeIs('website.navigation*') ? 'is-active' : '' }}" href="{{ route('website.navigation') }}"><x-ui.icon name="menu" size="16" /><span>Navigation</span></a>
            @endcan
            @can('website.media.manage')
                <a class="website-module-link {{ request()->routeIs('website.media*') ? 'is-active' : '' }}" href="{{ route('website.media') }}"><x-ui.icon name="camera" size="16" /><span>Media library</span></a>
            @endcan
            @can('website.pricing.manage')
                <a class="website-module-link {{ request()->routeIs('website.pricing*') ? 'is-active' : '' }}" href="{{ route('website.pricing') }}"><x-ui.icon name="currency" size="16" /><span>Pricing</span></a>
            @endcan
            @can('website.enquiries.view')
                <a class="website-module-link {{ request()->routeIs('website.enquiries*') ? 'is-active' : '' }}" href="{{ route('website.enquiries') }}"><x-ui.icon name="mail" size="16" /><span>Enquiries</span></a>
            @endcan
            @can('website.settings.manage')
                <a class="website-module-link {{ request()->routeIs('website.settings*') ? 'is-active' : '' }}" href="{{ route('website.settings') }}"><x-ui.icon name="settings" size="16" /><span>Settings</span></a>
            @endcan
        </nav>
    </aside>
    <div class="website-module-content">
