@props(['eyebrow' => null, 'heading', 'description' => null, 'align' => 'left', 'theme' => 'light'])

<div {{ $attributes->merge(['class' => 'public-section-heading public-section-heading--'.$align.' public-section-heading--'.$theme]) }}>
    @if($eyebrow)<x-public.eyebrow>{{ $eyebrow }}</x-public.eyebrow>@endif
    <h2>{{ $heading }}</h2>
    @if($description)<p>{{ $description }}</p>@endif
</div>
