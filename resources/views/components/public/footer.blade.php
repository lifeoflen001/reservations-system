@php
    $publicBrand = config('hotel.brand');
    $markPath = (string) ($publicBrand['mark'] ?? 'assets/branding/lodgix-mark.png');
    $markAvailable = file_exists(public_path(ltrim($markPath, '/')));
@endphp

<footer class="public-footer">
    <x-public.container>
        <div class="public-footer__grid">
            <div class="public-footer__identity">
                <a class="public-brand public-brand--footer" href="{{ url('/') }}" aria-label="{{ $publicBrand['product_name'] }} home">
                    <span class="public-brand__mark">@if($markAvailable)<img src="{{ asset($markPath) }}" alt="">@else<x-ui.icon name="building" size="20" />@endif</span>
                    <span class="public-brand__text">{{ $publicBrand['product_name'] }}</span>
                </a>
                <p>{{ $publicBrand['product_name'] }} connects reservations, rooms, hotel operations, staff, POS, finance and reporting in one workspace.</p>
            </div>
            <div>
                <h2>Product</h2>
                <a href="{{ route('public.product') }}">Product</a>
                <a href="{{ route('public.operations') }}">Operations</a>
                <a href="{{ route('public.pos') }}">POS</a>
                <a href="{{ route('public.finance') }}">Finance</a>
            </div>
            <div>
                <h2>Platform</h2>
                <a href="{{ route('public.security') }}">Security</a>
                <a href="{{ route('public.integrations') }}">Integrations</a>
                <a href="{{ auth()->check() ? route('dashboard') : route('login') }}">{{ auth()->check() ? 'Open Dashboard' : 'Sign In' }}</a>
            </div>
        </div>
        <div class="public-footer__bottom">
            <span>© {{ now()->year }} {{ $publicBrand['product_name'] }}</span>
        </div>
    </x-public.container>
</footer>
