@props(['variant' => 'primary', 'href' => null, 'type' => 'button', 'disabled' => false])

@php($classes = 'public-button public-button--'.$variant)

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
