@props(['ctaRoute', 'ctaLabel', 'solutions' => [], 'solutionsActive' => false])
@php
    $publicNavigation = app(\App\Services\PublicWebsiteContentService::class)->navigation();
    $navigationByDestination = collect($publicNavigation)->keyBy('destination');
    $productNavigation = $navigationByDestination->get('public.product');
    $pricingNavigation = $navigationByDestination->get('public.pricing');
    $contactNavigation = $navigationByDestination->get('public.contact');
    $solutionsNavigation = $navigationByDestination->get('solutions');
@endphp

<div class="public-mobile-menu" id="public-mobile-menu" data-public-mobile-menu hidden>
    <div class="public-mobile-menu__backdrop" data-public-menu-close></div>
    <div class="public-mobile-menu__panel" role="dialog" aria-modal="true" aria-labelledby="public-mobile-menu-title">
        <div class="public-mobile-menu__header">
            <strong id="public-mobile-menu-title">Menu</strong>
            <button class="public-menu-close" type="button" data-public-menu-close aria-label="Close navigation menu"><x-ui.icon name="plus" size="21" /></button>
        </div>
        <nav class="public-mobile-menu__links" aria-label="Mobile public navigation">
            @if(!$publicNavigation || $productNavigation)
                <a href="{{ $productNavigation['url'] ?? route('public.product') }}" @class(['is-active' => request()->routeIs('public.product')]) @if(request()->routeIs('public.product')) aria-current="page" @endif>{{ $productNavigation['label'] ?? 'Product' }}</a>
            @endif
            @if(!$publicNavigation || $solutionsNavigation)
                <div class="public-mobile-solutions" data-mobile-solutions>
                <button type="button" class="public-mobile-solutions__toggle {{ $solutionsActive ? 'is-active' : '' }}" data-mobile-solutions-toggle aria-expanded="{{ $solutionsActive ? 'true' : 'false' }}" aria-controls="public-mobile-solutions-list">
                    {{ $solutionsNavigation['label'] ?? 'Solutions' }} <x-ui.icon name="chevron-down" size="16" />
                </button>
                <div class="public-mobile-solutions__list" id="public-mobile-solutions-list" data-mobile-solutions-list @if(!$solutionsActive) hidden @endif>
                    @foreach($solutions as $item)
                        <a href="{{ route($item['route']) }}" @class(['is-active' => request()->routeIs($item['route'])]) @if(request()->routeIs($item['route'])) aria-current="page" @endif>{{ $item['label'] }}</a>
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
        </nav>
        <x-public.button :href="$ctaRoute" variant="primary">{{ $ctaLabel }}</x-public.button>
    </div>
</div>
