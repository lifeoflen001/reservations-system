@props(['variant' => 'standard'])

<article {{ $attributes->merge(['class' => 'public-card public-card--'.$variant]) }}>
    {{ $slot }}
</article>
