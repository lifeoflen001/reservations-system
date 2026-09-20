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
    <title>{{ $title ?? config('hotel.brand.name') }}</title>
    @php($profileTheme = auth()->user()?->preferences?->theme)
    <script>const configuredTheme = @json(app(\App\Services\SystemSettingsService::class)->get('theme', 'system')); const profileTheme = @json($profileTheme); const storedTheme = localStorage.getItem('hotel-theme'); const initialTheme = profileTheme && profileTheme !== 'system' ? profileTheme : (storedTheme || configuredTheme); document.documentElement.dataset.theme = initialTheme === 'system' ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : initialTheme;</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body @if(request()->routeIs('room-planning.*')) planning-app-body @endif" data-app-shell data-property-timezone="{{ app(\App\Services\PropertySettingsService::class)->timezone() }}">
    <x-sidebar />
    <div class="app-shell">
        <x-topbar />
        <main class="app-main" tabindex="-1">
            @if (session('success'))<x-feedback.toast type="success" :message="session('success')" />@endif
            @if (session('error'))<x-feedback.toast type="danger" :message="session('error')" />@endif
            @if (session('warning'))<x-feedback.toast type="warning" :message="session('warning')" />@endif
            @if (session('info'))<x-feedback.toast type="info" :message="session('info')" />@endif
            @yield('content')
        </main>
    </div>
    <x-ui.page-skeleton />
    <x-ui.confirm-modal />
</body>
</html>
