@props([
    'id' => 'global-confirm-modal',
    'title' => 'Please confirm',
    'message' => 'Are you sure you want to continue?',
    'confirmLabel' => 'Continue',
    'cancelLabel' => 'Cancel',
])

<x-ui.modal :id="$id" :title="$title">
    <div class="confirm-modal" data-confirm-modal-content>
        <div class="confirm-modal__icon" aria-hidden="true"><x-ui.icon name="alert" size="25" /></div>
        <div class="confirm-modal__copy">
            <p class="confirm-modal__message" data-confirm-message>{{ $message }}</p>
            <p class="confirm-modal__hint">This action may change active hotel records.</p>
        </div>
    </div>
    <div class="confirm-modal__actions">
        <button type="button" class="ui-button ui-button--secondary" data-modal-close>{{ $cancelLabel }}</button>
        <button type="button" class="ui-button ui-button--warning" data-confirm-submit>{{ $confirmLabel }}</button>
    </div>
</x-ui.modal>
