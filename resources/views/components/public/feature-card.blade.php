@props(['title', 'description' => null, 'icon' => null, 'variant' => 'feature'])

<x-public.card :variant="$variant">
    @if($icon)<span class="public-card__icon"><x-ui.icon :name="$icon" size="20" /></span>@endif
    <h3>{{ $title }}</h3>
    @if($description)<p>{{ $description }}</p>@endif
    {{ $slot }}
</x-public.card>
