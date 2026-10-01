@props(['ctaRoute', 'ctaLabel', 'solutions' => [], 'solutionsActive' => false])

<div class="public-mobile-menu" id="public-mobile-menu" data-public-mobile-menu hidden>
    <div class="public-mobile-menu__backdrop" data-public-menu-close></div>
    <div class="public-mobile-menu__panel" role="dialog" aria-modal="true" aria-labelledby="public-mobile-menu-title">
        <div class="public-mobile-menu__header">
            <strong id="public-mobile-menu-title">Menu</strong>
            <button class="public-menu-close" type="button" data-public-menu-close aria-label="Close navigation menu"><x-ui.icon name="plus" size="21" /></button>
        </div>
        <nav class="public-mobile-menu__links" aria-label="Mobile public navigation">
            <a href="{{ route('public.product') }}" @class(['is-active' => request()->routeIs('public.product')]) @if(request()->routeIs('public.product')) aria-current="page" @endif>Product</a>
            <div class="public-mobile-solutions" data-mobile-solutions>
                <button type="button" class="public-mobile-solutions__toggle {{ $solutionsActive ? 'is-active' : '' }}" data-mobile-solutions-toggle aria-expanded="{{ $solutionsActive ? 'true' : 'false' }}" aria-controls="public-mobile-solutions-list">
                    Solutions <x-ui.icon name="chevron-down" size="16" />
                </button>
                <div class="public-mobile-solutions__list" id="public-mobile-solutions-list" data-mobile-solutions-list @if(!$solutionsActive) hidden @endif>
                    @foreach($solutions as $item)
                        <a href="{{ route($item['route']) }}" @class(['is-active' => request()->routeIs($item['route'])]) @if(request()->routeIs($item['route'])) aria-current="page" @endif>{{ $item['label'] }}</a>
                    @endforeach
                </div>
            </div>
            <a href="{{ route('public.pricing') }}" @class(['is-active' => request()->routeIs('public.pricing')]) @if(request()->routeIs('public.pricing')) aria-current="page" @endif>Pricing</a>
            <a href="{{ route('public.contact') }}" @class(['is-active' => request()->routeIs('public.contact')]) @if(request()->routeIs('public.contact')) aria-current="page" @endif>Contact</a>
        </nav>
        <x-public.button :href="$ctaRoute" variant="primary">{{ $ctaLabel }}</x-public.button>
    </div>
</div>
