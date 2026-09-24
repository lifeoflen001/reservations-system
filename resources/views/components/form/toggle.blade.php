@props([
    'name',
    'label',
    'checked' => false,
    'value' => '1',
    'help' => null,
    'id' => null,
])

<label class="form-toggle" for="{{ $id ?? $name }}">
    <input id="{{ $id ?? $name }}" type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $checked)) {{ $attributes }}>
    <span class="form-toggle__track" aria-hidden="true"><span class="form-toggle__thumb"></span></span>
    <span class="form-toggle__copy">
        <strong>{{ $label }}</strong>
        @if($help)<small>{{ $help }}</small>@endif
    </span>
</label>
