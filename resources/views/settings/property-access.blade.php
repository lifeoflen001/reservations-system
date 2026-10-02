@extends('layouts.app')

@section('content')
<x-page-header title="Manage property access" subtitle="Control which organization members can operate {{ $property->name }}." />
<div class="organization-admin-layout">
    @include('settings.partials.organization-nav')
    <section class="ui-card organization-table-card member-access-editor">
        <header class="ui-card__header"><div><p class="settings-eyebrow">Property access</p><h2>{{ $property->name }}</h2><p class="form-help">Organization: {{ $organization->name }} · Access is separate from employee records.</p></div><x-ui.badge :variant="$property->status === 'active' ? 'success' : 'neutral'">{{ ucfirst($property->status) }}</x-ui.badge></header>
        <form method="POST" action="{{ route('settings.properties.access.update', $property) }}">
            @csrf @method('PUT')
            <div class="access-property-grid access-member-grid">
                @forelse($allMembers as $member)
                    <label class="access-property-option {{ $member->status !== 'active' ? 'is-disabled' : '' }}"><input type="checkbox" name="membership_ids[]" value="{{ $member->id }}" @checked(in_array($member->id, $selectedMemberIds, true)) @disabled($member->status !== 'active')><span><strong>{{ $member->user?->name ?: $member->user?->email }}</strong><small>{{ $member->role?->label ?: 'No role' }} · {{ ucfirst($member->status) }}</small></span></label>
                @empty
                    <p class="table-empty">No organization members are available.</p>
                @endforelse
            </div>
            <div class="form-field form-field--full settings-form-footer"><a class="ui-button ui-button--secondary" href="{{ route('settings.properties.index') }}">Back to properties</a><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> Save property access</button></div>
        </form>
    </section>
</div>
@endsection
