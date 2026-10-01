@extends('layouts.app')
@section('content')
<x-page-header title="Website settings" subtitle="Manage reusable public copy. Credentials and secrets remain in environment configuration." />
@include('website.partials.nav')

@php
    $setting = fn (string $key) => $settings->firstWhere('key', $key)?->value;
    $productName = $setting('product_name');
@endphp

<div class="website-settings-shell">
    <nav class="website-settings-tabs" aria-label="Website settings sections">
        @foreach([
            'general' => 'General',
            'branding' => 'Branding',
            'contact' => 'Contact',
            'footer' => 'Footer',
            'social' => 'Social',
            'seo' => 'SEO defaults',
        ] as $key => $label)
            <a class="{{ $section === $key ? 'is-active' : '' }}" href="{{ route('website.settings', ['section' => $key]) }}">{{ $label }}</a>
        @endforeach
    </nav>
    @if(in_array($section, ['general', 'contact', 'footer'], true))
    <form method="POST" action="{{ route('website.settings.update') }}" class="website-settings-form" data-website-settings-form>
        @csrf @method('PATCH')
        @if($section === 'general')
            <section class="ui-card website-settings-card">
                <header class="ui-card__header"><div><h2>Brand and public copy</h2><p>Visible website wording only. Application internals are not changed here.</p></div></header>
                <div class="website-settings-fields">
                    <x-form.input name="product_name" label="Public product name" :value="$productName" required />
                    <x-form.input name="default_cta_label" label="Default CTA" :value="$setting('default_cta_label')" />
                    <x-form.textarea name="short_description" label="Short description" rows="3">{{ $setting('short_description') }}</x-form.textarea>
                    <x-form.input name="copyright" label="Copyright" :value="$setting('copyright')" />
                </div>
            </section>
        @elseif($section === 'contact')
            <input type="hidden" name="product_name" value="{{ $productName }}">
            <section class="ui-card website-settings-card">
                <header class="ui-card__header"><div><h2>Contact</h2><p>Support details used by the public website and enquiry workflow.</p></div></header>
                <div class="website-settings-fields">
                    <x-form.input name="support_email" label="Public support email" type="email" :value="$setting('support_email')" />
                    <x-form.input name="contact_email" label="Contact recipient email" type="email" :value="$setting('contact_email')" />
                </div>
            </section>
        @else
            <input type="hidden" name="product_name" value="{{ $productName }}">
            <section class="ui-card website-settings-card">
                <header class="ui-card__header"><div><h2>Footer</h2><p>Public footer copy is managed here without exposing application internals.</p></div></header>
                <div class="website-settings-fields">
                    <x-form.textarea name="footer_description" label="Footer description" rows="3">{{ $setting('footer_description') }}</x-form.textarea>
                    <x-form.input name="copyright" label="Copyright" :value="$setting('copyright')" />
                </div>
            </section>
        @endif
        <div class="website-settings-actions"><span>Changes are saved as public configuration.</span><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="15" /> Save website settings</button></div>
    </form>
    @else
        <section class="ui-card website-settings-card website-settings-status-card">
            <header class="ui-card__header"><div><h2>{{ ucfirst($section) }} settings</h2><p>This category is reserved for approved public-site configuration.</p></div><x-ui.badge variant="neutral">Not configured</x-ui.badge></header>
            <div class="website-settings-status-card__body">
                @if($section === 'branding')
                    <p>Brand identity uses the centralized public configuration and approved media library. No separate branding values are stored yet.</p>
                    @can('website.media.manage')<a class="ui-button ui-button--secondary ui-button--small" href="{{ route('website.media') }}"><x-ui.icon name="camera" size="14" /> Open media library</a>@endcan
                @elseif($section === 'seo')
                    <p>Per-page SEO titles, descriptions, robots settings and social preview metadata are managed in the SEO manager.</p>
                    @can('website.seo.manage')<a class="ui-button ui-button--secondary ui-button--small" href="{{ route('website.seo') }}"><x-ui.icon name="search" size="14" /> Open SEO manager</a>@endcan
                @else
                    <p>No social profile values are configured in the current public settings store.</p>
                @endif
            </div>
        </section>
    @endif
</div>
@include('website.partials.close')
@endsection
