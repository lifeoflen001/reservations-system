<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($brandIcon = asset('assets/branding/lodgix-mark.png') . '?v=' . filemtime(public_path('assets/branding/lodgix-mark.png')))
    <link rel="icon" type="image/png" sizes="254x180" href="{{ $brandIcon }}">
    <link rel="shortcut icon" type="image/png" href="{{ $brandIcon }}">
    <link rel="apple-touch-icon" href="{{ $brandIcon }}">
    <link rel="mask-icon" href="{{ asset('assets/branding/lodgix-mark.svg') }}?v={{ filemtime(public_path('assets/branding/lodgix-mark.svg')) }}" color="#ef7d22">
    <meta name="theme-color" content="#ef7d22">
    <title>{{ config('hotel.brand.name') }} · {{ $title ?? 'Sign in' }}</title>
    <script>const configuredTheme = @json(app(\App\Services\SystemSettingsService::class)->get('theme', 'system')); document.documentElement.dataset.theme = localStorage.getItem('hotel-theme') || (configuredTheme === 'system' ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : configuredTheme);</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="guest-body">
    @yield('content')
</body>
</html>
