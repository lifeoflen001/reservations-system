@php
    $publicBrand = config('hotel.brand');
    $logoPath = (string) ($publicBrand['logo_light'] ?? 'assets/branding/lodgix.png');
    $logoAvailable = file_exists(public_path(ltrim($logoPath, '/')));
    $ctaRoute = auth()->check() ? route('dashboard') : route('login');
    $ctaLabel = auth()->check() ? 'Open Dashboard' : 'Sign In';
    $navigation = [
        ['label' => 'Product', 'route' => 'public.product'],
        ['label' => 'Operations', 'route' => 'public.operations'],
        ['label' => 'POS', 'route' => 'public.pos'],
        ['label' => 'Finance', 'route' => 'public.finance'],
    ];
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
                @foreach($navigation as $item)
                    <a href="{{ route($item['route']) }}" @class(['is-active' => request()->routeIs($item['route'])]) @if(request()->routeIs($item['route'])) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endforeach
            </div>

            <div class="public-navbar__actions">
                <x-public.button :href="$ctaRoute" variant="secondary">{{ $ctaLabel }}</x-public.button>
                <button class="public-menu-toggle" type="button" data-public-menu-open aria-label="Open navigation menu" aria-controls="public-mobile-menu" aria-expanded="false">
                    <x-ui.icon name="menu" size="21" />
                </button>
            </div>
        </nav>
    </x-public.container>
</header>

<x-public.mobile-nav :cta-route="$ctaRoute" :cta-label="$ctaLabel" :navigation="$navigation" />
