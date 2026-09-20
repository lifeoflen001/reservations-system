@props(['label', 'variant' => 'default'])

<button type="button" aria-label="{{ $label }}" data-tooltip="{{ $label }}" {{ $attributes->merge(['class' => 'icon-button icon-button--'.$variant]) }}>
    {{ $slot }}
</button>
