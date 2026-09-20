@props(['title', 'subtitle' => null])

<div class="page-header">
    <div>
        <h1 class="page-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @if (trim($slot))
        <div class="page-header__actions">{{ $slot }}</div>
    @endif
</div>
