<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><title>Platform Administration · {{ config('hotel.brand.name') }}</title>
    <script>document.documentElement.dataset.theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';</script>
    @vite(['resources/css/platform.css'])
</head>
<body class="platform-body">
<main class="platform-login">
    <section class="platform-login__card" aria-labelledby="platform-login-title">
        <img class="platform-login__brand" src="{{ asset('assets/branding/lodgix.png') }}" alt="Lodgix">
        <span class="ui-badge ui-badge--brand">Platform Administration</span>
        <h1 id="platform-login-title">Sign in to the control plane</h1>
        <p>Manage Lodgix platform metadata, support access and system health. This is separate from hotel staff sign-in.</p>
        @if($errors->any())<x-feedback.alert type="danger">{{ $errors->first() }}</x-feedback.alert>@endif
        <form class="platform-login__form" method="POST" action="{{ route('platform.login.store') }}">
            @csrf
            <div class="form-field"><label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
            <div class="form-field"><label class="form-label" for="password">Password</label><input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required></div>
            <label class="form-checkbox"><input type="checkbox" name="remember" value="1"> <span>Keep me signed in</span></label>
            <button class="ui-button ui-button--primary" type="submit">Sign in to Platform Administration</button>
        </form>
        <p class="form-help">Customer hotel users should continue through the standard <a class="text-link" href="{{ route('login') }}">hotel sign-in</a>.</p>
    </section>
</main>
</body>
</html>
