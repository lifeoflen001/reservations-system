@props([
    'id',
    'title' => null,
    'size' => 'default',
    'open' => false,
    'staticBackdrop' => true,
    'dirtyGuard' => true,
])

<div class="modal" id="{{ $id }}" data-modal data-modal-static-backdrop="{{ $staticBackdrop ? 'true' : 'false' }}" data-modal-dirty-guard="{{ $dirtyGuard ? 'true' : 'false' }}" @if($open) data-modal-auto-open @endif role="dialog" aria-modal="true" @if($title) aria-labelledby="{{ $id }}-title" @endif hidden>
    <div class="modal__backdrop" aria-hidden="true"></div>
    <div class="modal__dialog modal__dialog--{{ $size }}" role="document" tabindex="-1">
        <header class="modal__header">
            @if($title)<h2 id="{{ $id }}-title">{{ $title }}</h2>@endif
            <button type="button" class="modal__close" data-modal-close aria-label="Close" data-tooltip="Close"><x-ui.icon name="plus" size="20" /></button>
        </header>
        <div class="modal__body">{{ $slot }}</div>
    </div>
</div>
