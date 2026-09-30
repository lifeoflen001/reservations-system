@extends('layouts.public')

@php
    $title = 'Lodgix — Hotel Management System for Connected Hotel Operations';
    $description = 'Manage reservations, rooms, hotel operations, POS, payments, finance and reporting from one connected Lodgix workspace for daily hotel operations.';
    $capabilities = [
        ['icon' => 'grid', 'title' => 'Product', 'description' => 'A connected operating view for the hotel day.', 'route' => 'public.product'],
        ['icon' => 'calendar', 'title' => 'Hotel operations', 'description' => 'Reservations, rooms, housekeeping and maintenance in sync.', 'route' => 'public.operations'],
        ['icon' => 'card', 'title' => 'POS & guest charges', 'description' => 'Move outlet sales cleanly into payment or folio.', 'route' => 'public.pos'],
        ['icon' => 'currency', 'title' => 'Finance & payments', 'description' => 'Follow balances, expenses, transfers and reconciliation.', 'route' => 'public.finance'],
        ['icon' => 'shield', 'title' => 'Security & control', 'description' => 'Keep access and sensitive hotel activity accountable.', 'route' => 'public.security'],
        ['icon' => 'share', 'title' => 'Integrations', 'description' => 'Connect supporting services with clear boundaries.', 'route' => 'public.integrations'],
    ];
    $operationsFeatures = [
        ['icon' => 'calendar', 'title' => 'Reservations and arrivals', 'description' => 'Keep the booking, guest and stay context together.'],
        ['icon' => 'bed', 'title' => 'Room planning', 'description' => 'See assignments and room readiness across the day.'],
        ['icon' => 'broom', 'title' => 'Housekeeping and maintenance', 'description' => 'Coordinate the work that keeps rooms ready.'],
    ];
    $structuredData = json_encode([
        '@context' => 'https://schema.org', '@type' => 'SoftwareApplication',
        'name' => config('hotel.brand.product_name', 'Lodgix'), 'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web', 'description' => $description, 'url' => url('/'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp

@push('structured-data')<script type="application/ld+json">{!! $structuredData !!}</script>@endpush

@section('content')
    <section class="public-home-hero">
        <x-public.container>
            <div class="public-home-hero__grid">
                <div class="public-home-hero__copy">
                    <x-public.eyebrow>Complete hotel operations platform</x-public.eyebrow>
                    <h1>Run the daily hotel operation from one clear workspace.</h1>
                    <p>{{ $description }}</p>
                    <div class="public-home-hero__actions">
                        <x-public.button :href="route('public.product')" variant="primary">Explore the platform <x-ui.icon name="arrow-right" size="16" /></x-public.button>
                        <x-public.button :href="auth()->check() ? route('dashboard') : route('login')" variant="secondary">{{ auth()->check() ? 'Open Dashboard' : 'Sign In' }}</x-public.button>
                    </div>
                    <div class="public-home-hero__points" aria-label="Lodgix capabilities">
                        <span><x-ui.icon name="check" size="14" /> Connected records</span>
                        <span><x-ui.icon name="check" size="14" /> Clear team access</span>
                        <span><x-ui.icon name="check" size="14" /> Responsive workspace</span>
                    </div>
                </div>
                <x-public.screenshot-frame
                    class="public-home-hero__visual"
                    browser-shell
                    src="assets/images/landing/lodgix-dashboard-light.webp"
                    :width="1654"
                    :height="921"
                    aspect-ratio="1654 / 921"
                    alt="Sanitized Lodgix dashboard preview showing reservations, occupancy and operational information."
                    loading="eager"
                    fetch-priority="high"
                />
            </div>
        </x-public.container>
    </section>

    <x-public.section class="public-home-intro">
        <div class="public-home-intro__grid"><div><x-public.eyebrow>One connected record</x-public.eyebrow><h2>One platform. Every part of the stay.</h2></div><p>Lodgix keeps reservations, rooms, teams, sales, payments and reporting in one operational workspace. Each team sees the context it needs while the hotel keeps a clearer record of what happened.</p></div>
    </x-public.section>

    <x-public.section class="public-section--muted public-home-capabilities">
        <x-public.section-heading align="center" eyebrow="Explore Lodgix" heading="The working tools behind the front desk." description="Choose a product area to see how the pieces fit together." />
        <div class="public-home-capabilities__grid">
            @foreach($capabilities as $capability)
                <a class="public-capability-card public-capability-card--link" href="{{ route($capability['route']) }}"><span class="public-capability-card__icon"><x-ui.icon :name="$capability['icon']" size="19" /></span><span class="public-capability-card__copy"><strong>{{ $capability['title'] }}</strong><small>{{ $capability['description'] }}</small></span><x-ui.icon name="arrow-right" size="16" class="public-capability-card__arrow" /></a>
            @endforeach
        </div>
    </x-public.section>

    <x-public.feature-section class="public-home-showcase" eyebrow="Hotel operations" heading="Keep every stay and every room operation in sync." description="Move from reservation to arrival, room assignment and room readiness without losing the operational view." :features="$operationsFeatures"><x-public.screenshot-frame browser-shell src="assets/images/landing/lodgix-room-planning.webp" :width="1846" :height="921" aspect-ratio="1846 / 921" alt="Sanitized Lodgix room planning calendar and room operations screen." /></x-public.feature-section>

    <x-public.section theme="dark" class="public-home-workflow"><x-public.section-heading theme="dark" align="center" eyebrow="Connected hotel workflow" heading="Follow the stay from booking to report." description="The operating record stays connected as teams move through the day." /><x-public.workflow /></x-public.section>

    <x-public.section class="public-section--muted public-home-control"><div class="public-home-control__grid"><x-public.card variant="feature"><span class="public-card__icon"><x-ui.icon name="shield" size="19" /></span><x-public.eyebrow>Security & control</x-public.eyebrow><h3>Keep the operation visible after the booking.</h3><p>Use roles, protected application areas and traceable financial actions to keep responsibility clear.</p><a class="public-inline-link" href="{{ route('public.security') }}">Explore security <x-ui.icon name="arrow-right" size="14" /></a></x-public.card><x-public.card variant="feature"><span class="public-card__icon"><x-ui.icon name="share" size="19" /></span><x-public.eyebrow>Integration readiness</x-public.eyebrow><h3>Connect supporting services with clear boundaries.</h3><p>Review the verified status of email, notifications, APIs, webhooks and provider-ready workflows.</p><a class="public-inline-link" href="{{ route('public.integrations') }}">See integrations <x-ui.icon name="arrow-right" size="14" /></a></x-public.card></div></x-public.section>

    <x-public.section theme="dark" class="public-final-cta public-home-cta"><div class="public-final-cta__content"><x-public.eyebrow>Bring it together</x-public.eyebrow><h2>Give every hotel team a clearer operating view.</h2><p>Reservations, rooms, operations, POS, finance and reporting — in one Lodgix workspace.</p><div class="public-final-cta__actions"><x-public.button :href="auth()->check() ? route('dashboard') : route('login')" variant="primary">{{ auth()->check() ? 'Open Dashboard' : 'Sign In' }} <x-ui.icon name="arrow-right" size="16" /></x-public.button><x-public.button :href="route('public.product')" variant="dark">Explore the platform</x-public.button></div></div></x-public.section>
@endsection
