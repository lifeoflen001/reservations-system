@props(['caption' => null])

<div class="table-wrap">
    <table {{ $attributes->merge(['class' => 'data-table']) }}>
        @if ($caption)<caption class="sr-only">{{ $caption }}</caption>@endif
        {{ $slot }}
    </table>
</div>
