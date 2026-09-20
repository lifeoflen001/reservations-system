<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('hotel.brand.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="print-body">
    @yield('content')
</body>
</html>
