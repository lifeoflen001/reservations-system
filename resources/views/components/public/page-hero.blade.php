@props([
    'eyebrow' => null,
    'heading',
    'description' => null,
    'theme' => 'light',
    'layout' => 'split',
    'class' => '',
    'ctaLabel' => null,
    'ctaHref' => null,
])

<section {{ $attributes->merge(['class' => 'public-page-hero public-page-hero--'.$theme.' public-page-hero--'.$layout.' '.$class]) }}>
    <x-public.container>
        <div class="public-page-hero__grid">
            <div class="public-page-hero__copy">
                @if($eyebrow)<x-public.eyebrow>{{ $eyebrow }}</x-public.eyebrow>@endif
                <h1>{{ $heading }}</h1>
                @if($description)<p>{{ $description }}</p>@endif
                @if($ctaLabel && $ctaHref)<div class="public-page-hero__actions"><x-public.button :href="$ctaHref" variant="primary">{{ $ctaLabel }} <x-ui.icon name="arrow-right" size="16" /></x-public.button></div>@endif
            </div>
            @if($slot->isNotEmpty())<div class="public-page-hero__visual">{{ $slot }}</div>@endif
        </div>
    </x-public.container>
</section>
