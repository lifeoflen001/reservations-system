@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'placeholder' => null, 'help' => null, 'required' => false, 'fieldClass' => null, 'showError' => true])

<div class="form-field {{ $fieldClass }}">
    @if ($label)<label for="{{ $name }}">{{ $label }} @if ($required)<span class="required-mark">*</span>@endif</label>@endif
    @if ($type === 'password')
        <div class="password-input">
            <input id="{{ $name }}" name="{{ $name }}" type="password" value="{{ old($name, $value) }}" @if($placeholder) placeholder="{{ $placeholder }}" @endif @required($required) {{ $attributes->merge(['class' => 'form-control']) }}>
            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false" data-tooltip="Show password"><x-ui.icon name="eye" size="17" data-password-eye /><x-ui.icon name="eye-off" size="17" data-password-eye-off hidden /></button>
        </div>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @if($placeholder) placeholder="{{ $placeholder }}" @endif @required($required) {{ $attributes->merge(['class' => 'form-control']) }}>
    @endif
    @if ($help)<small class="form-help">{{ $help }}</small>@endif
    @if ($showError) @error($name)<small class="form-error">{{ $message }}</small>@enderror @endif
</div>
