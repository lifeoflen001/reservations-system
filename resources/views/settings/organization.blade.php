@extends('layouts.app')

@section('content')
<x-page-header title="Organization settings" subtitle="Manage organization-wide information without changing property operations." />
<div class="organization-admin-layout">
    @include('settings.partials.organization-nav')
    <section class="ui-card organization-scope-card">
        <header class="ui-card__header"><div><p class="settings-eyebrow">Organization settings</p><h2>{{ $organization->name }}</h2><p class="form-help">Applies to all properties in {{ $organization->name }}.</p></div><x-ui.badge variant="info">Customer organization</x-ui.badge></header>
        <form method="POST" action="{{ route('settings.organization.update') }}" class="form-grid">
            @csrf @method('PUT')
            <x-form.input name="name" label="Organization name" :value="$organization->name" required />
            <x-form.input name="billing_email" type="email" label="Billing email" :value="$organization->billing_email" />
            <x-form.input name="country" label="Country" :value="$organization->country" />
            <x-form.input name="default_currency" label="Default currency" :value="$organization->default_currency" placeholder="USD" />
            <x-form.select name="timezone" label="Default timezone" :options="collect(\DateTimeZone::listIdentifiers())->mapWithKeys(fn ($timezone) => [$timezone => $timezone])->all()" :value="$organization->timezone ?: config('hotel.defaults.timezone')" field-class="form-field--full" />
            <div class="form-field form-field--full settings-form-footer"><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> Save organization</button></div>
        </form>
    </section>
    <aside class="ui-card organization-scope-note">
        <p class="settings-eyebrow">Scope guide</p>
        <h2>Organization vs property</h2>
        <p><strong>Organization settings</strong> apply across {{ $organization->properties()->count() }} {{ Str::plural('property', $organization->properties()->count()) }}.</p>
        <p><strong>Property settings</strong> apply only to the active hotel, including rooms, operating hours, and property contact details.</p>
        <a class="ui-button ui-button--secondary" href="{{ route('settings.properties.index') }}">Manage properties</a>
    </aside>
</div>
@endsection
