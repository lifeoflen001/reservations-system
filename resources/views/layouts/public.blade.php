@php
    $publicBrand = config('hotel.brand');
    $brandAsset = static function (string $key, string $fallback): string {
        $configured = (string) config('hotel.brand.'.$key, $fallback);
        $relative = ltrim($configured, '/');

        return file_exists(public_path($relative)) ? asset($relative) : asset($fallback);
    };
    $pageTitle = $title ?? ($publicBrand['product_name'].' — Hotel Management System for Connected Hotel Operations');
    $pageDescription = $description ?? 'Manage reservations, rooms, hotel operations, POS, payments, finance and reporting from one connected Lodgix workspace for daily hotel operations.';
    $pageCanonical = $canonical ?? url('/');
    $pageRobots = $robots ?? 'index,follow';
    $defaultSocialImage = 'assets/images/landing/lodgix-social-preview.webp';
    $socialImage = $ogImage ?? (file_exists(public_path($defaultSocialImage)) ? asset($defaultSocialImage) : $brandAsset('mark', 'assets/branding/lodgix-mark.png'));
    $commonStructuredData = [
        ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $publicBrand['product_name'], 'url' => url('/'), 'logo' => $socialImage],
        ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $publicBrand['product_name'], 'url' => url('/')],
    ];
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#e67e2f">
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="robots" content="{{ $pageRobots }}">
    <link rel="canonical" href="{{ $pageCanonical }}">
    <link rel="icon" href="{{ $brandAsset('favicon', 'favicon.ico') }}" type="image/x-icon" sizes="any">
    <link rel="alternate icon" type="image/png" href="{{ $brandAsset('mark', 'assets/branding/lodgix-mark.png') }}">
    <link rel="apple-touch-icon" href="{{ $brandAsset('apple_touch_icon', 'assets/branding/lodgix-mark.png') }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $publicBrand['product_name'] }}">
    <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $pageCanonical }}">
    <meta property="og:image" content="{{ $socialImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $publicBrand['product_name'] }} hotel management system">
    <meta property="og:image:type" content="image/webp">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:url" content="{{ $pageCanonical }}">
    <meta name="twitter:image" content="{{ $socialImage }}">
    <title>{{ $pageTitle }}</title>
    @foreach($commonStructuredData as $structuredItem)<script type="application/ld+json">{!! json_encode($structuredItem, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>@endforeach
    @stack('structured-data')
    @vite(['resources/css/public.css', 'resources/js/public.js'])
</head>
<body class="public-body">
    <a class="public-skip-link" href="#main-content">Skip to main content</a>
    <x-public.navbar />
    <main id="main-content" tabindex="-1">
        @yield('content')
    </main>
    <x-public.footer />
    @stack('scripts')
</body>
</html>
