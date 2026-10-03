<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($brandIcon = asset('assets/branding/lodgix-mark.png'))
    <link rel="icon" type="image/png" href="{{ $brandIcon }}">
    <meta name="theme-color" content="#ef7d22">
    <title>{{ $title ?? 'Platform Administration' }} · {{ config('hotel.brand.name') }}</title>
    <script>const platformTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; document.documentElement.dataset.theme = platformTheme;</script>
    @vite(['resources/css/platform.css'])
</head>
<body class="platform-body">
@php($administrator = auth('platform')->user())
@php($supportSession = app(\App\Services\Platform\SupportAccessService::class)->current($administrator))
<div class="platform-shell">
    <aside class="platform-sidebar" aria-label="Platform administration">
        <a class="platform-brand" href="{{ route('platform.dashboard') }}" aria-label="Lodgix Platform Administration home">
            <img src="{{ asset('assets/branding/lodgix.png') }}" alt="Lodgix">
        </a>
        <div class="platform-brand__label">Control plane</div>
        <nav class="platform-nav">
            <div class="platform-nav__label">Platform</div>
            <a class="{{ request()->routeIs('platform.dashboard') ? 'is-active' : '' }}" href="{{ route('platform.dashboard') }}">Overview</a>
            <a class="{{ request()->routeIs('platform.organizations*') ? 'is-active' : '' }}" href="{{ route('platform.organizations') }}">Organizations</a>
            <a class="{{ request()->routeIs('platform.properties*') ? 'is-active' : '' }}" href="{{ route('platform.properties') }}">Properties</a>
            <a class="{{ request()->routeIs('platform.subscriptions*') ? 'is-active' : '' }}" href="{{ route('platform.subscriptions') }}">Subscriptions</a>
            <div class="platform-nav__label">Operations</div>
            <a class="{{ request()->routeIs('platform.support*') ? 'is-active' : '' }}" href="{{ route('platform.support') }}">Support access</a>
            <a class="{{ request()->routeIs('platform.health') ? 'is-active' : '' }}" href="{{ route('platform.health') }}">System health</a>
            <a class="{{ request()->routeIs('platform.audit') ? 'is-active' : '' }}" href="{{ route('platform.audit') }}">Platform audit</a>
            <div class="platform-nav__label">System</div>
            <a class="{{ request()->routeIs('platform.administrators') ? 'is-active' : '' }}" href="{{ route('platform.administrators') }}">Administrators</a>
        </nav>
        <div class="platform-sidebar__footer">{{ config('hotel.brand.name') }} Platform Administration</div>
    </aside>
    <section class="platform-main">
        <header class="platform-topbar">
            <div class="platform-topbar__title"><strong>Lodgix Control Plane</strong><span>Platform administration · {{ $administrator?->name }}</span></div>
            <div class="platform-topbar__actions">
                <a class="ui-button ui-button--secondary ui-button--compact" href="{{ route('platform.profile') }}">Profile</a>
                <form method="POST" action="{{ route('platform.logout') }}">@csrf<button class="ui-button ui-button--ghost ui-button--compact" type="submit">Sign out</button></form>
            </div>
        </header>
        @if($supportSession)
            <div class="platform-support-banner" role="status"><div><strong>Support mode</strong> <span>{{ $supportSession->organization?->name }}{{ $supportSession->property ? ' · '.$supportSession->property->name : '' }} · expires {{ $supportSession->expires_at?->diffForHumans() }}</span></div><form method="POST" action="{{ route('platform.support.end') }}">@csrf<button class="ui-button ui-button--warning ui-button--compact" type="submit">Exit support mode</button></form></div>
        @endif
        <main class="platform-content" tabindex="-1">
            @if(session('success'))<x-feedback.toast type="success" :message="session('success')" />@endif
            @if(session('error'))<x-feedback.toast type="danger" :message="session('error')" />@endif
            @if(session('warning'))<x-feedback.toast type="warning" :message="session('warning')" />@endif
            @yield('content')
        </main>
    </section>
</div>
</body>
</html>
