@props(['name', 'label' => null, 'value' => null, 'help' => null, 'required' => false, 'fieldClass' => null, 'showError' => true, 'id' => null, 'searchable' => false, 'options' => null])

@php($wrapperClass = trim(($fieldClass ?? '').' '.($attributes->get('class') ?? '')))
@php($controlAttributes = $attributes->except('class'))

<div class="form-field {{ $wrapperClass }}">
    @if ($label)<label for="{{ $id ?? $name }}">{{ $label }} @if ($required)<span class="required-mark">*</span>@endif</label>@endif
    <div class="form-select" data-pms-select-wrapper data-pms-searchable="{{ $searchable ? 'true' : 'false' }}">
        <select id="{{ $id ?? $name }}" name="{{ $name }}" data-pms-select @required($required) {{ $controlAttributes->merge(['class' => 'form-control']) }}>
            @if(is_array($options))
                @foreach($options as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            @endif
            {{ $slot }}
        </select>
        <span class="form-select__chevron" aria-hidden="true"><x-ui.icon name="chevron-down" size="15" /></span>
    </div>
    @if ($help)<small class="form-help">{{ $help }}</small>@endif
    @if ($showError) @error($name)<small class="form-error">{{ $message }}</small>@enderror @endif
</div>
