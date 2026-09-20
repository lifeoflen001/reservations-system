@props(['compact' => false])

<a href="{{ auth()->check() ? route('dashboard') : route('login') }}" {{ $attributes->merge(['class' => 'app-logo '.($compact ? 'app-logo--compact' : '')]) }} aria-label="{{ config('hotel.brand.name') }} home">
    <span class="app-logo__mark"><img src="{{ asset('assets/branding/lodgix-mark.png') }}" alt="" /></span>
    <span class="app-logo__copy">
        <img class="app-logo__wordmark" src="{{ asset('assets/branding/lodgix.png') }}" alt="{{ config('hotel.brand.name') }}" />
    </span>
</a>
