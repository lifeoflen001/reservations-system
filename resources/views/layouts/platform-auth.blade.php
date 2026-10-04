<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $title ?? 'Platform security' }} · {{ config('hotel.brand.name') }}</title>
    <script>document.documentElement.dataset.theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';</script>
    @vite(['resources/css/platform.css'])
</head>
<body class="platform-body">
<main class="platform-login">
    <section class="platform-login__card" aria-labelledby="platform-auth-title">
        <img class="platform-login__brand" src="{{ asset('assets/branding/lodgix.png') }}" alt="Lodgix">
        <span class="ui-badge ui-badge--brand">Platform Administration security</span>
        @if(session('success'))<x-feedback.alert type="success">{{ session('success') }}</x-feedback.alert>@endif
        @if($errors->any())<x-feedback.alert type="danger">{{ $errors->first() }}</x-feedback.alert>@endif
        @yield('content')
    </section>
</main>
</body>
</html>
