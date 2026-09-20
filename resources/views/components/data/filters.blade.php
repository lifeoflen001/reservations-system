@props(['action' => null, 'method' => 'GET'])

<form method="{{ $method }}" @if($action) action="{{ $action }}" @endif {{ $attributes->merge(['class' => 'filter-toolbar']) }}>
    {{ $slot }}
</form>
