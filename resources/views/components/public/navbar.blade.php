@php
    $publicBrand = config('hotel.brand');
    $logoPath = (string) ($publicBrand['logo_light'] ?? 'assets/branding/lodgix.png');
    $logoAvailable = file_exists(public_path(ltrim($logoPath, '/')));
    $ctaRoute = auth()->check() ? route('dashboard') : route('public.contact', ['enquiry_type' => 'demo']);
    $ctaLabel = auth()->check() ? 'Open Dashboard' : 'Request a demo';
    $solutionGroups = [
        ['label' => 'Hotel operations', 'items' => [
            ['label' => 'Reservations', 'route' => 'public.operations', 'description' => 'Bookings, arrivals and guest context'],
            ['label' => 'Rooms & planning', 'route' => 'public.operations', 'description' => 'Assignments, readiness and room flow'],
            ['label' => 'Housekeeping', 'route' => 'public.operations', 'description' => 'Room work and daily readiness'],
            ['label' => 'Maintenance & tasks', 'route' => 'public.operations', 'description' => 'Issues, follow-up and team work'],
        ]],
        ['label' => 'Revenue & finance', 'items' => [
            ['label' => 'POS & guest charges', 'route' => 'public.pos', 'description' => 'Outlet sales connected to stays'],
            ['label' => 'Payments & reporting', 'route' => 'public.finance', 'description' => 'Movement, reconciliation and insight'],
        ]],
        ['label' => 'Platform', 'items' => [
            ['label' => 'Security', 'route' => 'public.security', 'description' => 'Roles, permissions and controls'],
            ['label' => 'Integrations', 'route' => 'public.integrations', 'description' => 'Connections and provider setup'],
        ]],
    ];
    $solutions = collect($solutionGroups)->flatMap(fn (array $group) => $group['items'])->all();
    $solutionsActive = request()->routeIs('public.operations', 'public.pos', 'public.finance', 'public.security', 'public.integrations');
@endphp

<header class="public-navbar" data-public-navbar>
    <x-public.container>
        <nav class="public-navbar__inner" aria-label="Public navigation">
            <a class="public-brand" href="{{ url('/') }}" aria-label="{{ $publicBrand['product_name'] }} home">
                @if($logoAvailable)
                    <img class="public-brand__wordmark" src="{{ asset($logoPath) }}" alt="{{ $publicBrand['product_name'] }}">
                @else
                    <span class="public-brand__text">{{ $publicBrand['product_name'] }}</span>
                @endif
            </a>

            <div class="public-navbar__links" data-public-desktop-links>
                <a href="{{ route('public.product') }}" @class(['is-active' => request()->routeIs('public.product')]) @if(request()->routeIs('public.product')) aria-current="page" @endif>Product</a>
                <div class="public-solutions" data-public-solutions>
                    <button class="public-solutions__trigger {{ $solutionsActive ? 'is-active' : '' }}" type="button" data-solutions-toggle aria-expanded="false" aria-controls="public-solutions-menu" aria-haspopup="true">
                        Solutions <x-ui.icon name="chevron-down" size="15" />
                    </button>
                    <div class="public-solutions__menu" id="public-solutions-menu" data-solutions-menu hidden role="menu">
                        @foreach($solutionGroups as $group)
                            <div class="public-solutions__group">
                                <strong class="public-solutions__group-title">{{ $group['label'] }}</strong>
                                @foreach($group['items'] as $item)
                                    <a role="menuitem" href="{{ route($item['route']) }}" @class(['is-active' => request()->routeIs($item['route'])]) @if(request()->routeIs($item['route'])) aria-current="page" @endif>
                                        <strong>{{ $item['label'] }}</strong><small>{{ $item['description'] }}</small>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
                <a href="{{ route('public.pricing') }}" @class(['is-active' => request()->routeIs('public.pricing')]) @if(request()->routeIs('public.pricing')) aria-current="page" @endif>Pricing</a>
                <a href="{{ route('public.contact') }}" @class(['is-active' => request()->routeIs('public.contact')]) @if(request()->routeIs('public.contact')) aria-current="page" @endif>Contact</a>
            </div>

            <div class="public-navbar__actions">
                @if(auth()->check())
                    <x-public.button :href="$ctaRoute" variant="primary">{{ $ctaLabel }}</x-public.button>
                @else
                    <x-public.button :href="route('login')" variant="secondary">Sign In</x-public.button>
                    <x-public.button :href="$ctaRoute" variant="primary">{{ $ctaLabel }}</x-public.button>
                @endif
                <button class="public-menu-toggle" type="button" data-public-menu-open aria-label="Open navigation menu" aria-controls="public-mobile-menu" aria-expanded="false">
                    <x-ui.icon name="menu" size="21" />
                </button>
            </div>
        </nav>
    </x-public.container>
</header>

<x-public.mobile-nav :cta-route="$ctaRoute" :cta-label="$ctaLabel" :solutions="$solutionGroups" :solutions-active="$solutionsActive" :authenticated="auth()->check()" />
