@props(['name', 'label', 'checked' => false])

<label class="checkbox-field"><input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked)) {{ $attributes }}><span>{{ $label }}</span></label>
