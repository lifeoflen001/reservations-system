@props([
    'id',
    'eyebrow',
    'heading',
    'description',
    'features' => [],
    'theme' => 'light',
    'reverse' => false,
])

<section id="{{ $id }}" {{ $attributes->merge(['class' => 'public-section public-module-showcase public-section--'.$theme.($reverse ? ' public-module-showcase--reverse' : '')]) }}>
    <x-public.container>
        <div class="public-module-showcase__grid">
            <div class="public-module-showcase__copy">
                <x-public.eyebrow>{{ $eyebrow }}</x-public.eyebrow>
                <h2>{{ $heading }}</h2>
                <p>{{ $description }}</p>
                <ul class="public-feature-list">
                    @foreach($features as $feature)
                        <li>
                            <span class="public-feature-list__icon"><x-ui.icon name="{{ $feature['icon'] ?? 'check' }}" size="16" /></span>
                            <span>
                                <strong>{{ $feature['title'] }}</strong>
                                @if(!empty($feature['description']))<small>{{ $feature['description'] }}</small>@endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="public-module-showcase__visual">
                {{ $slot }}
            </div>
        </div>
    </x-public.container>
</section>
