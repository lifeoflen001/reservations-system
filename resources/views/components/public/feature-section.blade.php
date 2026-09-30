@props([
    'eyebrow' => null,
    'heading',
    'description' => null,
    'features' => [],
    'reverse' => false,
    'theme' => 'light',
])

<section {{ $attributes->merge(['class' => 'public-feature-section public-feature-section--'.$theme.($reverse ? ' public-feature-section--reverse' : '')]) }}>
    <x-public.container>
        <div class="public-feature-section__grid">
            <div class="public-feature-section__copy">
                @if($eyebrow)<x-public.eyebrow>{{ $eyebrow }}</x-public.eyebrow>@endif
                <h2>{{ $heading }}</h2>
                @if($description)<p>{{ $description }}</p>@endif
                @if($features)
                    <ul class="public-feature-list">
                        @foreach($features as $feature)
                            <li>
                                <span class="public-feature-list__icon"><x-ui.icon :name="$feature['icon'] ?? 'check'" size="15" /></span>
                                <span><strong>{{ $feature['title'] }}</strong><small>{{ $feature['description'] }}</small></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            @if($slot->isNotEmpty())
                <div class="public-feature-section__visual">{{ $slot }}</div>
            @endif
        </div>
    </x-public.container>
</section>
