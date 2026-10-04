@extends('layouts.guest')
@section('content')
<main class="login-page"><section class="login-panel" aria-labelledby="register-title"><div class="login-content">
    <x-app-logo class="auth-form-logo" />
    <div class="login-heading"><h1 id="register-title">Create your Lodgix account</h1><p>Start with your account. We will guide you through organization and hotel setup next.</p></div>
    @if ($errors->any())<x-feedback.alert type="danger">{{ $errors->first() }}</x-feedback.alert>@endif
    <form method="POST" action="{{ route('register.store') }}" class="login-form">@csrf
        <x-form.input name="name" label="Full name" autocomplete="name" required />
        <x-form.input name="email" type="email" label="Work email" autocomplete="email" required />
        <x-form.input name="password" type="password" label="Password" autocomplete="new-password" required />
        <x-form.input name="password_confirmation" type="password" label="Confirm password" autocomplete="new-password" required />
        <label class="check-field"><input type="checkbox" name="terms" value="1" @checked(old('terms')) required> <span>I agree to the current Lodgix terms and privacy requirements.</span></label>
        <div aria-hidden="true" style="position:absolute;left:-9999px"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <x-ui.button type="submit" class="login-submit">Create account <x-ui.icon name="arrow-right" size="17" /></x-ui.button>
    </form><a class="auth-secondary-link" href="{{ route('login') }}">Already have an account? Sign in</a>
</div></section><aside class="login-promo" aria-label="Lodgix onboarding"><div class="promo-content"><span class="promo-kicker">{{ config('hotel.brand.name') }} onboarding</span><h2>Set up your hotel workspace with confidence.</h2><p>Verify your email, choose an available plan and create your first property in a short guided flow.</p></div></aside></main>
@endsection
