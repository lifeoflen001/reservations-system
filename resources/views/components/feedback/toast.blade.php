@props(['type' => 'success', 'message' => null])

@if ($message)
    <div class="toast toast--{{ $type }}" data-toast role="status"><span>{{ $message }}</span><button type="button" data-toast-close aria-label="Dismiss">&times;</button></div>
@endif
