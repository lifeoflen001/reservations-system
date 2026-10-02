@extends('layouts.app')

@section('content')
<x-page-header title="Edit {{ $property->name }}" subtitle="Property settings apply only to {{ $organization->name }} / {{ $property->name }}." />
<section class="ui-card property-edit-card">
    <header class="ui-card__header"><div><h2>Property identity</h2><p class="form-help">Organization ownership is fixed and cannot be changed here.</p></div><x-ui.badge :variant="$property->status === 'active' ? 'success' : 'neutral'">{{ ucfirst($property->status) }}</x-ui.badge></header>
    <form method="POST" action="{{ route('settings.properties.update', $property) }}" class="form-grid">@csrf @method('PUT')
        <x-form.input name="name" label="Property name" :value="$property->name" required />
        <x-form.input name="property_code" label="Property code" :value="$property->property_code" required />
        <x-form.input name="email" type="email" label="Email" :value="$property->email" />
        <x-form.input name="phone" label="Phone" :value="$property->phone" />
        <x-form.input name="country" label="Country" :value="$property->country" />
        <x-form.select name="timezone" label="Timezone" :options="collect($timezones)->mapWithKeys(fn ($timezone) => [$timezone => $timezone])->all()" :value="$property->timezone ?: config('hotel.defaults.timezone')" required />
        <x-form.select name="base_currency_id" label="Currency" :options="$currencies->pluck('code', 'id')->all()" :value="$property->base_currency_id" required />
        <x-form.select name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="$property->status" required />
        <div class="form-field form-field--full settings-form-footer"><a class="ui-button ui-button--secondary" href="{{ route('settings.properties.index') }}">Cancel</a><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> Save property</button></div>
    </form>
</section>
@endsection
