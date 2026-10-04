@extends('layouts.app')

@section('content')
<x-page-header title="Properties" subtitle="Manage properties inside {{ $organization->name }}. Operational data is never copied when a property is created." />

<div class="property-management-layout">
    <section class="ui-card">
        <header class="ui-card__header">
            <div><h2>Organization properties</h2><p class="form-help">Only properties belonging to your current organization are shown.</p></div>
            <x-ui.badge variant="info">{{ $properties->count() }} total</x-ui.badge>
        </header>
        <div class="table-scroll">
            <table class="data-table property-management-table">
                <thead><tr><th>Property</th><th>Code</th><th>Status</th><th>Timezone</th><th>Currency</th><th>Users</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse($properties as $property)
                    <tr>
                        <td><strong>{{ $property->name }}</strong><small class="table-muted">{{ $property->email ?: 'No contact email' }}</small></td>
                        <td><code>{{ $property->property_code ?: '—' }}</code></td>
                        <td><x-ui.badge :variant="$property->status === 'active' ? 'success' : 'neutral'">{{ ucfirst($property->status) }}</x-ui.badge></td>
                        <td>{{ $property->timezone ?: '—' }}</td>
                        <td>{{ $property->baseCurrency?->code ?: '—' }}</td>
                        <td>{{ $property->user_count ?? 0 }}</td>
                        <td class="table-actions"><div class="row-actions">@if($property->status === 'active')<form method="POST" action="{{ route('context.property') }}">@csrf<input type="hidden" name="property_id" value="{{ $property->id }}"><input type="hidden" name="return_to" value="{{ route('dashboard') }}"><button class="ui-button ui-button--secondary ui-button--small" type="submit">Open</button></form>@endif @can('properties.update')<a class="ui-button ui-button--secondary ui-button--small" href="{{ route('settings.properties.edit', $property) }}">Edit</a>@endcan @can('members.view')<a class="ui-button ui-button--secondary ui-button--small" href="{{ route('settings.properties.access', $property) }}">Manage access</a>@endcan</div></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="table-empty">No properties are configured for this organization.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($canCreate)
    <section class="ui-card property-create-card">
        <header class="ui-card__header"><div><h2>Create property</h2><p class="form-help">The new property starts with empty rooms, reservations, finance, POS and task data.</p></div></header>
        <form method="POST" action="{{ route('settings.properties.store') }}" class="form-grid">@csrf
            <x-form.input name="name" label="Property name" required />
            <x-form.input name="property_code" label="Property code" placeholder="ARU-01" required />
            <x-form.input name="email" type="email" label="Email" />
            <x-form.input name="phone" label="Phone" />
            <x-form.input name="country" label="Country" />
            <x-form.select name="timezone" label="Timezone" :options="collect($timezones)->mapWithKeys(fn ($timezone) => [$timezone => $timezone])->all()" :value="config('hotel.defaults.timezone')" required />
            <x-form.select name="base_currency_id" label="Currency" :options="$currencies->pluck('code', 'id')->all()" required />
            <div class="form-field form-field--full"><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="plus" size="16" /> Create property</button></div>
        </form>
    </section>
    @endif
</div>
@endsection
