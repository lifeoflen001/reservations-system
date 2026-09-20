@extends('layouts.guest')

@section('content')
    <main class="login-page auth-page--compact">
        <section class="login-panel" aria-labelledby="forgot-password-title">
            <div class="login-content">
                <x-app-logo class="auth-form-logo" />
                <div class="login-heading">
                    <h1 id="forgot-password-title">Forgot your password?</h1>
                    <p>Enter your account email and we’ll send you a secure reset link.</p>
                </div>

                @if (session('success'))
                    <x-feedback.alert type="success">{{ session('success') }}</x-feedback.alert>
                @endif
                @if ($errors->any())
                    <x-feedback.alert type="danger">{{ $errors->first() }}</x-feedback.alert>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="login-form">
                    @csrf
                    <x-form.input name="email" type="email" label="Email address" value="{{ old('email') }}" autocomplete="email" :show-error="false" required />
                    <x-ui.button type="submit" class="login-submit"><x-ui.icon name="arrow-right" size="17" /> Send reset link</x-ui.button>
                </form>
                <a class="auth-secondary-link" href="{{ route('login') }}">Return to sign in</a>
            </div>
        </section>
        <aside class="login-promo" aria-label="Account recovery overview">
            <div class="promo-content">
                <span class="promo-kicker">{{ config('hotel.brand.name') }} Account recovery</span>
                <h2>Get back to your hotel workspace securely.</h2>
                <p>We’ll help you restore access without exposing your account details.</p>
                <div class="promo-features">
                    <article class="promo-feature"><x-ui.icon name="shield" size="22" /><h3>Secure reset</h3><p>Reset links are single-use and expire automatically.</p></article>
                    <article class="promo-feature"><x-ui.icon name="key" size="22" /><h3>Strong access</h3><p>Create a new password that follows your hotel’s security policy.</p></article>
                    <article class="promo-feature"><x-ui.icon name="arrow-right" size="22" /><h3>Quick return</h3><p>Use the link in your email to return to HotelDesk.</p></article>
                </div>
            </div>
        </aside>
    </main>
@endsection
