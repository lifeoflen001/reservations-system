@extends('layouts.public')

@php
$title = 'Lodgix — Hotel Management Software for Independent Hotels & Lodges';
$description = 'Hotel and lodge management software for independent properties. Manage reservations, room planning, housekeeping, guest charges, payments and reporting with Lodgix.';
$capabilities = [
['icon' => 'calendar', 'title' => 'Reservations', 'description' => 'Keep stays, arrivals and guest details together.', 'route' => 'public.operations'],
['icon' => 'bed', 'title' => 'Room planning', 'description' => 'See assignments and room readiness across the day.', 'route' => 'public.operations'],
['icon' => 'broom', 'title' => 'Housekeeping', 'description' => 'Coordinate the work that gets rooms ready.', 'route' => 'public.operations'],
['icon' => 'wrench', 'title' => 'Maintenance & tasks', 'description' => 'Keep repairs and daily follow-ups visible.', 'route' => 'public.operations'],
['icon' => 'card', 'title' => 'POS & guest charges', 'description' => 'Connect outlet sales with a guest stay.', 'route' => 'public.pos'],
['icon' => 'chart', 'title' => 'Payments & reports', 'description' => 'Follow payment activity and operating results.', 'route' => 'public.finance'],
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

@push('structured-data')<script type="application/ld+json">
    {!! $structuredData !!}
</script>@endpush

@section('content')
<section class="public-home-hero">
    <x-public.container>
        <div class="public-home-hero__grid">
            <div class="public-home-hero__copy">
                <x-public.eyebrow>For hotels and lodges</x-public.eyebrow>
                <h1>Hotel operations at a glance.</h1>
                <p>{{ $description }}</p>
                <div class="public-home-hero__actions">
                    <x-public.button :href="route('public.product')" variant="primary">Explore the platform <x-ui.icon name="arrow-right" size="16" /></x-public.button>
                    <x-public.button :href="auth()->check() ? route('dashboard') : route('login')" variant="secondary">{{ auth()->check() ? 'Open Dashboard' : 'Sign In' }}</x-public.button>
                </div>
                <div class="public-home-hero__points" aria-label="Lodgix capabilities">
                    <span><x-ui.icon name="check" size="14" /> Reservations and arrivals</span>
                    <span><x-ui.icon name="check" size="14" /> Room readiness</span>
                    <span><x-ui.icon name="check" size="14" /> Payments and reporting</span>
                </div>
            </div>
            <x-public.screenshot-frame
                class="public-home-hero__visual"
                browser-shell
                src="assets/images/landing/lodgix-dashboard-light.jpg"
                mobile-src="assets/images/landing/lodgix-dashboard-mobile.webp"
                :srcset="asset('assets/images/landing/lodgix-dashboard-light-960.jpg').' 960w, '.asset('assets/images/landing/lodgix-dashboard-light-1440.jpg').' 1440w, '.asset('assets/images/landing/lodgix-dashboard-light.jpg').' 1654w'"
                sizes="(max-width: 900px) calc(100vw - 36px), 58vw"
                :width="1654"
                :height="921"
                aspect-ratio="1654 / 921"
                alt="Lodgix hotel operations dashboard showing reservation, room and finance summaries."
                loading="eager"
                fetch-priority="high" />
        </div>
    </x-public.container>
</section>

<x-public.section class="public-section--muted public-home-capabilities">
    <x-public.section-heading align="center" eyebrow="One connected workspace" heading="The essentials for running your property." description="Bring the guest stay, room operations and daily business into one clear view." />
    <div class="public-home-capabilities__grid">
        @foreach($capabilities as $capability)
        <a class="public-capability-card public-capability-card--link" href="{{ route($capability['route']) }}"><span class="public-capability-card__icon"><x-ui.icon :name="$capability['icon']" size="19" /></span><span class="public-capability-card__copy"><strong>{{ $capability['title'] }}</strong><small>{{ $capability['description'] }}</small></span><x-ui.icon name="arrow-right" size="16" class="public-capability-card__arrow" /></a>
        @endforeach
    </div>
</x-public.section>

<x-public.feature-section class="public-home-showcase" eyebrow="Hotel operations" heading="Keep every stay and every room operation in sync." description="Move from reservation to arrival, room assignment and room readiness without losing the operational view." :features="$operationsFeatures"><x-public.screenshot-frame browser-shell src="assets/images/landing/lodgix-room-planning.jpg" :srcset="asset('assets/images/landing/lodgix-room-planning-960.jpg').' 960w, '.asset('assets/images/landing/lodgix-room-planning-1440.jpg').' 1440w, '.asset('assets/images/landing/lodgix-room-planning.jpg').' 1846w'" sizes="(max-width: 900px) calc(100vw - 36px), 62vw" :width="1846" :height="921" aspect-ratio="1846 / 921" alt="Lodgix room planning calendar showing room assignments and operational status." /></x-public.feature-section>

<x-public.section theme="dark" class="public-home-workflow">
    <x-public.section-heading theme="dark" align="center" eyebrow="The guest stay" heading="From booking to checkout." description="Keep the key handoffs visible as your team looks after each stay." />
    <ol class="public-home-journey" aria-label="A guest stay managed with Lodgix">
        <li><span>01</span>
            <div><strong>Book</strong><small>Record the reservation and guest details.</small></div>
        </li>
        <li><span>02</span>
            <div><strong>Prepare</strong><small>Plan the room and coordinate readiness.</small></div>
        </li>
        <li><span>03</span>
            <div><strong>Host</strong><small>Keep the stay and guest charges connected.</small></div>
        </li>
        <li><span>04</span>
            <div><strong>Review</strong><small>Record payment and check operating activity.</small></div>
        </li>
    </ol>
</x-public.section>

<x-public.section class="public-final-cta public-home-cta public-section--muted">
    <div class="public-final-cta__content"><x-public.eyebrow>Made for independent properties</x-public.eyebrow>
        <h2>See how Lodgix fits your hotel or lodge.</h2>
        <p>Explore the tools for reservations, room operations, guest charges and reporting.</p>
        <div class="public-final-cta__actions"><x-public.button :href="route('public.contact')" variant="primary">Talk to Lodgix</x-public.button><x-public.button :href="route('public.product')" variant="secondary">Explore the product</x-public.button></div>
    </div>
</x-public.section>
@endsection
