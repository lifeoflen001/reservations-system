@props(['type' => 'info'])

<div {{ $attributes->merge(['class' => 'feedback feedback--'.$type]) }} role="status"><x-ui.icon :name="$type === 'danger' ? 'alert' : 'info'" size="17" /><span>{{ $slot }}</span></div>
