@php($pricingCms = app(\App\Services\PublicWebsiteContentService::class)->page('pricing'))
@php($pricingHero = $pricingCms['sections']['hero'] ?? [])
@php($pricingPlans = app(\App\Services\PublicWebsiteContentService::class)->pricingPlans())
@extends('layouts.public', [
    'title' => $pricingCms['page']->seo_title ?? 'Lodgix Pricing — Hotel Management System Plans',
    'description' => $pricingCms['page']->seo_description ?? 'Explore Lodgix plan options for hotel operations, POS, finance, reporting and integrations. Pricing is discussed based on each property’s requirements.',
    'canonical' => route('public.pricing'),
])

@section('content')
<div class="public-page public-page--pricing">
    <x-public.page-hero :eyebrow="$pricingHero['eyebrow'] ?? 'Pricing for independent hotels and lodges'" :heading="$pricingHero['heading'] ?? 'Flexible plans built around your property.'" description="Pricing is tailored to your property size, required modules and implementation needs." layout="centered" class="public-page-hero--compact" />

    <x-public.section class="public-pricing-section">
        <x-public.section-heading align="center" eyebrow="Find the right fit" heading="Explore a starting point." description="Tell us about your property and the workflows you want to bring together." />
        @if($pricingPlans)
        <div class="public-pricing-grid">
            @foreach($pricingPlans as $plan)
            <article class="public-card public-pricing-card {{ $plan['is_highlighted'] ? 'public-pricing-card--highlighted' : '' }}">
                <p class="public-pricing-card__eyebrow">{{ $plan['name'] }}</p>
                <h3>{{ $plan['short_description'] }}</h3>
                <p class="public-pricing-card__price">{{ $plan['price_display'] }} @if($plan['billing_label'])<span>{{ $plan['billing_label'] }}</span>@endif</p>
                <ul>@foreach($plan['features'] as $feature)<li>{{ $feature }}</li>@endforeach</ul>
                <x-public.button :href="$plan['cta_url'] ?: route('public.contact', ['enquiry_type' => 'pricing'])" variant="secondary">{{ $plan['cta_label'] }}</x-public.button>
            </article>
            @endforeach
        </div>
        @else
        <div class="public-pricing-grid">
            <article class="public-card public-pricing-card">
                <p class="public-pricing-card__eyebrow">Starter</p>
                <h3>Essential hotel operations</h3>
                <p class="public-pricing-card__description">For smaller properties organizing the essentials of each day.</p>
                <p class="public-pricing-card__price">Tailored pricing</p>
                <ul>
                    <li>Reservations and guest records</li>
                    <li>Rooms and room planning</li>
                    <li>Housekeeping, maintenance and tasks</li>
                    <li>Operational reports</li>
                </ul>
                <x-public.button :href="route('public.contact', ['enquiry_type' => 'pricing'])" variant="secondary">Request pricing</x-public.button>
            </article>

            <article class="public-card public-pricing-card">
                <p class="public-pricing-card__eyebrow">Professional</p>
                <h3>Connected operations, POS and finance</h3>
                <p class="public-pricing-card__description">For properties connecting service, payment and finance workflows.</p>
                <p class="public-pricing-card__price">Tailored pricing</p>
                <ul>
                    <li>Core hotel operations</li>
                    <li>POS and guest charges</li>
                    <li>Payments and finance workflows</li>
                    <li>Staff access and reporting needs</li>
                </ul>
                <x-public.button :href="route('public.contact', ['enquiry_type' => 'pricing'])" variant="secondary">Request pricing</x-public.button>
            </article>

            <article class="public-card public-pricing-card">
                <p class="public-pricing-card__eyebrow">Enterprise</p>
                <h3>Advanced controls and connections</h3>
                <p class="public-pricing-card__description">For complex property requirements and a detailed scope discussion.</p>
                <p class="public-pricing-card__price">Tailored pricing</p>
                <ul>
                    <li>Advanced roles and permissions</li>
                    <li>Integration requirements</li>
                    <li>Implementation and support needs</li>
                </ul>
                <x-public.button :href="route('public.contact', ['enquiry_type' => 'pricing'])" variant="secondary">Request pricing</x-public.button>
            </article>
        </div>
        @endif
        <p class="public-pricing-note">We’ll confirm the right scope and pricing with you based on your property’s needs.</p>
    </x-public.section>

    <x-public.section class="public-pricing-factors public-section--muted">
        <x-public.section-heading eyebrow="Your requirements" heading="What shapes your plan?" description="We’ll take the size and needs of your property into account." />
        <div class="public-pricing-factors__grid">
            <div><strong>Property size</strong><span>Property type, room count and operating scope.</span></div>
            <div><strong>Required workflows</strong><span>The Lodgix areas that support your teams.</span></div>
            <div><strong>Services and connections</strong><span>Integration, implementation and support needs.</span></div>
        </div>
    </x-public.section>

    <x-public.section class="public-pricing-comparison">
        <x-public.section-heading align="center" eyebrow="Scope guide" heading="Compare the starting scope." description="Use this as a conversation guide; the final scope is confirmed around your property and operating requirements." />
        <div class="public-comparison-wrap">
            <table class="public-comparison">
                <caption>Typical starting scope by plan</caption>
                <thead><tr><th scope="col">Capability</th><th scope="col">Starter</th><th scope="col">Professional</th><th scope="col">Enterprise</th></tr></thead>
                <tbody>
                    @foreach([
                        ['Reservations and guest records', 'Included', 'Included', 'Included'],
                        ['Rooms and room planning', 'Included', 'Included', 'Included'],
                        ['Housekeeping, maintenance and tasks', 'Included', 'Included', 'Included'],
                        ['Operational reporting', 'Included', 'Included', 'Included'],
                        ['POS and guest charges', '—', 'Included', 'Scope discussion'],
                        ['Payments and finance workflows', '—', 'Included', 'Scope discussion'],
                        ['Advanced roles and permissions', '—', 'Scope discussion', 'Scope discussion'],
                        ['Provider integrations', '—', 'Scope discussion', 'Scope discussion'],
                    ] as [$capability, $starter, $professional, $enterprise])
                        <tr><th scope="row">{{ $capability }}</th><td data-label="Starter">{{ $starter }}</td><td data-label="Professional">{{ $professional }}</td><td data-label="Enterprise">{{ $enterprise }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="public-pricing-note">“Included” describes a typical starting scope, not a contractual entitlement. We’ll confirm the right fit with you.</p>
    </x-public.section>

    <x-public.section class="public-pricing-faq">
        <x-public.section-heading align="center" eyebrow="Common questions" heading="A few things to clarify." />
        <div class="public-pricing-faq__list">
            <details><summary>Can I discuss selected modules?</summary><p>Yes. Tell us which hotel workflows you need and we’ll discuss the right scope for your property.</p></details>
            <details><summary>Can POS and Finance be included?</summary><p>POS and Finance can be included in the pricing conversation based on your requirements.</p></details>
            <details><summary>Can integrations be configured?</summary><p>Integration options depend on the provider and workflow you want to support.</p></details>
            <details><summary>Is pricing based on hotel size?</summary><p>Property size, selected workflows and implementation needs can all shape a quote.</p></details>
        </div>
    </x-public.section>

    <x-public.section class="public-final-cta public-page-cta">
        <div class="public-final-cta__content"><x-public.eyebrow>Talk through your requirements</x-public.eyebrow>
            <h2>Need a plan that fits your hotel?</h2>
            <p>Tell us about your property and the Lodgix modules you’re interested in.</p>
            <div class="public-final-cta__actions"><x-public.button :href="route('public.contact', ['enquiry_type' => 'pricing'])" variant="primary">Discuss your plan</x-public.button></div>
        </div>
    </x-public.section>
</div>
@endsection
