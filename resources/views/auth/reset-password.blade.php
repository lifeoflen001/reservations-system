@extends('layouts.guest')

@section('content')
    <main class="login-page auth-page--compact">
        <section class="login-panel" aria-labelledby="reset-password-title">
            <div class="login-content">
                <x-app-logo class="auth-form-logo" />
                <div class="login-heading">
                    <h1 id="reset-password-title">Create a new password</h1>
                    <p>Choose a strong password to secure your HotelDesk account.</p>
                </div>

                @if ($errors->any())
                    <x-feedback.alert type="danger">{{ $errors->first() }}</x-feedback.alert>
                @endif

                <form method="POST" action="{{ route('password.update') }}" class="login-form">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <x-form.input name="email" type="email" label="Email address" value="{{ old('email', $email) }}" autocomplete="email" :show-error="false" required />
                    <x-form.input name="password" type="password" label="New password" autocomplete="new-password" help="Follow the configured password policy." :show-error="false" required />
                    <x-form.input name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" :show-error="false" required />
                    <x-ui.button type="submit" class="login-submit"><x-ui.icon name="shield" size="17" /> Reset password</x-ui.button>
                </form>
                <a class="auth-secondary-link" href="{{ route('login') }}">Return to sign in</a>
            </div>
        </section>
        <aside class="login-promo" aria-label="Password security overview">
            <div class="promo-content">
                <span class="promo-kicker">{{ config('hotel.brand.name') }} Protected access</span>
                <h2>A fresh password for every hotel operation.</h2>
                <p>Your new password will be protected using HotelDesk’s configured security rules.</p>
                <div class="promo-features">
                    <article class="promo-feature"><x-ui.icon name="shield" size="22" /><h3>Protected</h3><p>Your password is stored securely and never displayed.</p></article>
                    <article class="promo-feature"><x-ui.icon name="key" size="22" /><h3>Private</h3><p>Only you can use the reset link sent to your email.</p></article>
                    <article class="promo-feature"><x-ui.icon name="check" size="22" /><h3>Ready to use</h3><p>Sign in immediately after your password is updated.</p></article>
                </div>
            </div>
        </aside>
    </main>
@endsection
