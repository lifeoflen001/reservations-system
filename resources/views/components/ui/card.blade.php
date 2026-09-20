@props(['title' => null, 'icon' => null, 'footer' => null])

<section {{ $attributes->merge(['class' => 'ui-card']) }}>
    @if ($title || $icon || isset($header))
        <header class="ui-card__header">
            <div class="ui-card__heading">
                @if ($icon)<x-ui.icon :name="$icon" size="18" />@endif
                @if ($title)<h2>{{ $title }}</h2>@endif
            </div>
            @if (isset($header))<div>{{ $header }}</div>@endif
        </header>
    @endif
    <div class="ui-card__body">{{ $slot }}</div>
    @if ($footer)<footer class="ui-card__footer">{{ $footer }}</footer>@endif
</section>
