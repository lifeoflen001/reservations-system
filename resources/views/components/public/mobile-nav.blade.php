@props(['ctaRoute', 'ctaLabel', 'navigation' => []])

<div class="public-mobile-menu" id="public-mobile-menu" data-public-mobile-menu hidden>
    <div class="public-mobile-menu__backdrop" data-public-menu-close></div>
    <div class="public-mobile-menu__panel" role="dialog" aria-modal="true" aria-labelledby="public-mobile-menu-title">
        <div class="public-mobile-menu__header">
            <strong id="public-mobile-menu-title">Menu</strong>
            <button class="public-menu-close" type="button" data-public-menu-close aria-label="Close navigation menu"><x-ui.icon name="plus" size="21" /></button>
        </div>
        <nav class="public-mobile-menu__links" aria-label="Mobile public navigation">
            @foreach($navigation as $item)
                <a href="{{ route($item['route']) }}" @class(['is-active' => request()->routeIs($item['route'])]) @if(request()->routeIs($item['route'])) aria-current="page" @endif>{{ $item['label'] }}</a>
            @endforeach
        </nav>
        <x-public.button :href="$ctaRoute" variant="primary">{{ $ctaLabel }}</x-public.button>
    </div>
</div>
