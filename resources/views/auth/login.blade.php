@extends('layouts.guest')

@section('content')
    <main class="login-page">
        <section class="login-panel" aria-labelledby="login-title">
            <div class="login-content">
                <x-app-logo class="auth-form-logo" />
                <div class="login-heading">
                    <h1 id="login-title">Welcome back</h1>
                    <p>Sign in to manage reservations, rooms and hotel operations.</p>
                </div>

                @if ($errors->any())
                    <x-feedback.alert type="danger">{{ $errors->first() }}</x-feedback.alert>
                @endif
                  <br>
                <form method="POST" action="{{ route('login.store') }}" class="login-form">
                    @csrf
                    <x-form.input name="identity" label="Email or username" value="{{ old('identity', 'admin') }}" autocomplete="username" :show-error="false" required />
                    <x-form.input name="password" type="password" label="Password" autocomplete="current-password" :show-error="false" required />
                    <x-form.checkbox name="remember" label="Remember me" />
                    <x-ui.button type="submit" class="login-submit"><x-ui.icon name="key" size="17" /> Sign in</x-ui.button>
                </form>
                <a class="auth-secondary-link" href="{{ route('password.request') }}">Forgot your password?</a>

                @if (app()->environment('local', 'development'))
                    <!--<div class="dev-notice">
                        <strong>Development login</strong>
                        <span>admin / Admin123!</span>
                        <small>Change these demonstration credentials immediately after the first sign-in.</small>
                    </div> -->
                @endif
            </div>
        </section>

        <aside class="login-promo" aria-label="Hotel operations overview">
            <div class="promo-content">
                <h2>One organized workspace for every hotel operation.</h2>
                <p>Reservations, front desk, housekeeping, maintenance, staff, payments and reporting in a single workspace.</p>
                <div class="promo-features">
                    <article class="promo-feature"><x-ui.icon name="calendar" size="22" /><h3>Reservations</h3><p>Calendar planning, arrivals, departures and room assignment.</p></article>
                    <article class="promo-feature"><x-ui.icon name="bed" size="22" /><h3>Rooms</h3><p>Live availability, housekeeping condition and room blocks.</p></article>
                    <article class="promo-feature"><x-ui.icon name="chart" size="22" /><h3>Operations</h3><p>Revenue, payments, staff, maintenance and operational reports.</p></article>
                </div>
            </div>
        </aside>
    </main>
@endsection
