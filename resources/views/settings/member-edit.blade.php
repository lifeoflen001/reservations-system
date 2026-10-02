@extends('layouts.app')

@section('content')
<x-page-header title="Edit member access" subtitle="Manage organization access for {{ $organization->name }} without exposing passwords or 2FA secrets." />
<div class="organization-admin-layout">
    @include('settings.partials.organization-nav')
    <section class="ui-card organization-table-card member-access-editor">
        <header class="ui-card__header"><div><p class="settings-eyebrow">User account</p><h2>{{ $membership->user?->name ?: $membership->user?->email }}</h2><p class="form-help">{{ $membership->user?->email }} · This is application access, not an employee profile.</p></div><x-ui.badge :variant="$membership->status === 'active' ? 'success' : 'neutral'">{{ ucfirst($membership->status) }}</x-ui.badge></header>
        <form method="POST" action="{{ route('settings.members.update', $membership) }}" class="form-grid">
            @csrf @method('PUT')
            <x-form.select name="role_id" label="Organization role" :options="$roles->pluck('label', 'id')->all()" :value="$membership->role_id" required help="Roles use the existing permission catalog. Platform-only permissions are not exposed here." />
            <x-form.select name="status" label="Membership status" :options="['active' => 'Active', 'inactive' => 'Inactive / revoked']" :value="$membership->status" required />
            <fieldset class="form-field form-field--full access-property-fieldset"><legend>Property access</legend><p class="form-help">Select only properties in {{ $organization->name }}. There is no implicit all-property role.</p><div class="access-property-grid">
                @foreach($properties as $property)
                    <label class="access-property-option {{ $property->status !== 'active' ? 'is-disabled' : '' }}"><input type="checkbox" name="property_ids[]" value="{{ $property->id }}" @checked($membership->propertyAccess->contains(fn ($access) => (int) $access->property_id === (int) $property->id && $access->status === 'active')) @disabled($property->status !== 'active')><span><strong>{{ $property->name }}</strong><small>{{ $property->property_code ?: 'No code' }} · {{ ucfirst($property->status) }}</small></span></label>
                @endforeach
            </div></fieldset>
            <div class="form-field form-field--full settings-form-footer"><a class="ui-button ui-button--secondary" href="{{ route('settings.members.index') }}">Cancel</a><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> Save access</button></div>
        </form>
    </section>
</div>
@endsection
