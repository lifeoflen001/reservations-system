@props(['name', 'label' => null, 'value' => null, 'placeholder' => null, 'help' => null, 'required' => false, 'fieldClass' => null, 'showError' => true])

<div class="form-field {{ $fieldClass }}">
    @if ($label)<label for="{{ $name }}">{{ $label }} @if ($required)<span class="required-mark">*</span>@endif</label>@endif
    <textarea id="{{ $name }}" name="{{ $name }}" @required($required) @if($placeholder) placeholder="{{ $placeholder }}" @endif {{ $attributes->merge(['class' => 'form-control']) }}>{{ old($name, $value) }}</textarea>
    @if ($help)<small class="form-help">{{ $help }}</small>@endif
    @if ($showError) @error($name)<small class="form-error">{{ $message }}</small>@enderror @endif
</div>
