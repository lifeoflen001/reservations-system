@props(['variant' => 'primary', 'type' => 'button', 'loading' => false, 'disabled' => false])

<button type="{{ $type }}" @disabled($disabled) {{ $attributes->merge(['class' => 'ui-button ui-button--'.$variant.($loading ? ' is-loading' : '')]) }}>
    @if ($loading)<span class="ui-spinner" aria-hidden="true"></span>@endif
    {{ $slot }}
</button>
