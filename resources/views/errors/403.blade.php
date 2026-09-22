@extends('layouts.app')

@section('content')
    <x-ui.modal id="authorization-warning" title="Access restricted" size="small" :show-close="false" open>
        <div class="confirm-modal authorization-warning" role="alert">
            <div class="confirm-modal__icon" aria-hidden="true"><x-ui.icon name="alert" size="25" /></div>
            <div class="confirm-modal__copy">
                <p class="confirm-modal__message">You have no access to perform this task.</p>
                <p class="confirm-modal__hint">Your account does not have the required permission. Contact an administrator if you believe this is incorrect.</p>
            </div>
        </div>
        <div class="confirm-modal__actions authorization-warning__actions">
            <a class="ui-button ui-button--primary" href="{{ route('dashboard') }}">Go to dashboard</a>
        </div>
    </x-ui.modal>
@endsection
