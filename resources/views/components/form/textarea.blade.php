@props(['name', 'label' => null, 'value' => null, 'placeholder' => null, 'help' => null, 'required' => false, 'fieldClass' => null, 'showError' => true])

@php($notesClass = preg_match('/(^|_)notes$/', $name) ? 'form-field--full' : '')
@php($wrapperClass = trim(($fieldClass ?? '').' '.($attributes->get('class') ?? '').' '.$notesClass))
@php($controlAttributes = $attributes->except('class'))

<div class="form-field {{ $wrapperClass }}">
    @if ($label)<label for="{{ $name }}">{{ $label }} @if ($required)<span class="required-mark">*</span>@endif</label>@endif
    <textarea id="{{ $name }}" name="{{ $name }}" @required($required) @if($placeholder) placeholder="{{ $placeholder }}" @endif {{ $controlAttributes->merge(['class' => 'form-control']) }}>{{ old($name, $value) }}</textarea>
    @if ($help)<small class="form-help">{{ $help }}</small>@endif
    @if ($showError) @error($name)<small class="form-error">{{ $message }}</small>@enderror @endif
</div>
