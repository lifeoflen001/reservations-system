@props([
    'src' => null,
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
    'placeholderTitle' => 'Product preview',
    'placeholderText' => null,
])

@php
    $imagePath = $src ? ltrim((string) $src, '/') : null;
    $imageAvailable = $imagePath && (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://') || file_exists(public_path($imagePath)));
@endphp

<figure {{ $attributes->merge(['class' => 'public-screenshot public-screenshot--'.$theme.($browserShell ? ' public-screenshot--browser' : '')]) }} style="--public-screenshot-ratio: {{ $aspectRatio }}">
    @if($browserShell)<div class="public-screenshot__browser-bar" aria-hidden="true"><i></i><i></i><i></i></div>@endif
    <div class="public-screenshot__viewport">
        @if($imageAvailable)
            <img src="{{ str_starts_with($imagePath, 'http') ? $imagePath : asset($imagePath) }}" alt="{{ $alt }}" @if($width) width="{{ $width }}" @endif @if($height) height="{{ $height }}" @endif @if($srcset) srcset="{{ $srcset }}" @endif @if($sizes) sizes="{{ $sizes }}" @endif loading="{{ $loading }}" decoding="async" @if($fetchPriority) fetchpriority="{{ $fetchPriority }}" @endif>
        @else
            <div class="public-screenshot__placeholder" role="img" aria-label="{{ $alt ?: 'Product screenshot placeholder' }}">
                <x-ui.icon name="grid" size="28" />
                <strong>{{ $placeholderTitle }}</strong>
                <span>{{ $placeholderText ?: 'Sanitized '.config('hotel.brand.product_name').' screenshots will appear here.' }}</span>
            </div>
        @endif
    </div>
</figure>
