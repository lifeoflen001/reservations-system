@props(['id' => null, 'theme' => 'light'])

<section @if($id) id="{{ $id }}" @endif {{ $attributes->merge(['class' => 'public-section public-section--'.$theme]) }}>
    <x-public.container>
        {{ $slot }}
    </x-public.container>
</section>
