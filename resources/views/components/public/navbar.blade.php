@php
    $publicBrand = config('hotel.brand');
    $logoPath = (string) ($publicBrand['logo_light'] ?? 'assets/branding/lodgix.png');
    $logoAvailable = file_exists(public_path(ltrim($logoPath, '/')));
    $ctaRoute = auth()->check() ? route('dashboard') : route('login');
    $ctaLabel = auth()->check() ? 'Open Dashboard' : 'Sign In';
    $publicNavigation = app(\App\Services\PublicWebsiteContentService::class)->navigation();
    $navigationByDestination = collect($publicNavigation)->keyBy('destination');
    $productNavigation = $navigationByDestination->get('public.product');
    $pricingNavigation = $navigationByDestination->get('public.pricing');
    $contactNavigation = $navigationByDestination->get('public.contact');
    $solutionsNavigation = $navigationByDestination->get('solutions');
    $solutions = [
        ['label' => 'Operations', 'route' => 'public.operations', 'description' => 'Reservations, rooms and teams'],
        ['label' => 'POS', 'route' => 'public.pos', 'description' => 'Outlet sales and guest charges'],
        ['label' => 'Finance', 'route' => 'public.finance', 'description' => 'Payments, accounts and reporting'],
        ['label' => 'Security', 'route' => 'public.security', 'description' => 'Roles, permissions and controls'],
        ['label' => 'Integrations', 'route' => 'public.integrations', 'description' => 'Connections and operational updates'],
    ];
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
                @if(!$publicNavigation || $productNavigation)
                    <a href="{{ $productNavigation['url'] ?? route('public.product') }}" @class(['is-active' => request()->routeIs('public.product')]) @if(request()->routeIs('public.product')) aria-current="page" @endif>{{ $productNavigation['label'] ?? 'Product' }}</a>
                @endif
                @if(!$publicNavigation || $solutionsNavigation)
                    <div class="public-solutions" data-public-solutions>
                        <button class="public-solutions__trigger {{ $solutionsActive ? 'is-active' : '' }}" type="button" data-solutions-toggle aria-expanded="false" aria-controls="public-solutions-menu" aria-haspopup="true">
                            {{ $solutionsNavigation['label'] ?? 'Solutions' }} <x-ui.icon name="chevron-down" size="15" />
                        </button>
                        <div class="public-solutions__menu" id="public-solutions-menu" data-solutions-menu hidden>
                            @foreach($solutions as $item)
                                <a href="{{ route($item['route']) }}" @class(['is-active' => request()->routeIs($item['route'])]) @if(request()->routeIs($item['route'])) aria-current="page" @endif>
                                    <strong>{{ $item['label'] }}</strong><small>{{ $item['description'] }}</small>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if(!$publicNavigation || $pricingNavigation)
                    <a href="{{ $pricingNavigation['url'] ?? route('public.pricing') }}" @class(['is-active' => request()->routeIs('public.pricing')]) @if(request()->routeIs('public.pricing')) aria-current="page" @endif>{{ $pricingNavigation['label'] ?? 'Pricing' }}</a>
                @endif
                @if(!$publicNavigation || $contactNavigation)
                    <a href="{{ $contactNavigation['url'] ?? route('public.contact') }}" @class(['is-active' => request()->routeIs('public.contact')]) @if(request()->routeIs('public.contact')) aria-current="page" @endif>{{ $contactNavigation['label'] ?? 'Contact' }}</a>
                @endif
            </div>

            <div class="public-navbar__actions">
                @if(auth()->check())
                    <x-public.button :href="$ctaRoute" variant="secondary">{{ $ctaLabel }}</x-public.button>
                @else
                    <x-public.button href="{{ route('login') }}" variant="secondary">Sign In</x-public.button>
                    <x-public.button href="{{ route('register') }}" variant="primary">Get Started</x-public.button>
                @endif
                <button class="public-menu-toggle" type="button" data-public-menu-open aria-label="Open navigation menu" aria-controls="public-mobile-menu" aria-expanded="false">
                    <x-ui.icon name="menu" size="21" />
                </button>
            </div>
        </nav>
    </x-public.container>
</header>

<x-public.mobile-nav :cta-route="$ctaRoute" :cta-label="$ctaLabel" :solutions="$solutions" :solutions-active="$solutionsActive" />
