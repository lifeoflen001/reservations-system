@props([
    'src' => null,
    'mobileSrc' => null,
    'alt' => '',
    'width' => null,
    'height' => null,
    'srcset' => null,
    'sizes' => null,
    'loading' => 'lazy',
    'aspectRatio' => '16 / 10',
    'browserShell' => false,
    'theme' => 'light',
    'fetchPriority' => null,
    'placeholderTitle' => 'Illustrative product view',
    'placeholderText' => null,
])

@php
    $imagePath = $src ? ltrim((string) $src, '/') : null;
    $imageAvailable = $imagePath && (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://') || file_exists(public_path($imagePath)));
    $mobileImagePath = $mobileSrc ? ltrim((string) $mobileSrc, '/') : null;
    $mobileImageAvailable = $mobileImagePath && file_exists(public_path($mobileImagePath));
@endphp

<figure {{ $attributes->merge(['class' => 'public-screenshot public-screenshot--'.$theme.($browserShell ? ' public-screenshot--browser' : '').($mobileImageAvailable ? ' public-screenshot--mobile-variant' : '')]) }} style="--public-screenshot-ratio: {{ $aspectRatio }}">
    @if($browserShell)<div class="public-screenshot__browser-bar" aria-hidden="true"><i></i><i></i><i></i></div>@endif
    <div class="public-screenshot__viewport">
        @if($imageAvailable)
            @if($mobileImageAvailable)<picture><source media="(max-width: 600px)" srcset="{{ asset($mobileImagePath) }}">@endif
            <img src="{{ str_starts_with($imagePath, 'http') ? $imagePath : asset($imagePath) }}" alt="{{ $alt }}" @if($width) width="{{ $width }}" @endif @if($height) height="{{ $height }}" @endif @if($srcset) srcset="{{ $srcset }}" @endif @if($sizes) sizes="{{ $sizes }}" @endif loading="{{ $loading }}" decoding="async" @if($fetchPriority) fetchpriority="{{ $fetchPriority }}" @endif>
            @if($mobileImageAvailable)</picture>@endif
        @else
            <div class="public-screenshot__placeholder" role="img" aria-label="{{ $alt ?: 'Illustrative Lodgix feature view, not a product screenshot' }}">
                <x-ui.icon name="grid" size="28" />
                <strong>{{ $placeholderTitle }}</strong>
                <span>{{ $placeholderText ?: 'This visual represents a Lodgix feature area, not a live application screen.' }}</span>
            </div>
        @endif
    </div>
</figure>
