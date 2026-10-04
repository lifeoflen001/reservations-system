@extends('layouts.app')

@section('content')
<x-page-header title="Organization members" subtitle="Manage application access for {{ $organization->name }}. Employees and hotel staff remain property-scoped in Staff." />
<div class="organization-admin-layout">
    @include('settings.partials.organization-nav')
    <section class="ui-card organization-table-card">
        <header class="ui-card__header"><div><p class="settings-eyebrow">Members &amp; access</p><h2>Application users</h2><p class="form-help">User identity, organization membership, and property access are managed separately from employee records.</p></div></header>
        <form method="GET" class="organization-filter-bar"><x-form.input name="search" label="Search members" :value="request('search')" placeholder="Name or email" /><x-form.select name="status" label="Status" :options="['all' => 'All statuses', 'active' => 'Active', 'inactive' => 'Inactive']" :value="request('status', 'all')" /><button class="ui-button ui-button--secondary" type="submit">Filter</button></form>
        <div class="table-scroll"><table class="data-table organization-members-table"><thead><tr><th>User</th><th>Email</th><th>Organization role</th><th>Property access</th><th>Status</th><th>Last updated</th><th class="table-actions">Actions</th></tr></thead><tbody>
            @forelse($members as $member)
                <tr><td><strong>{{ $member->user?->name ?: $member->user?->first_name.' '.$member->user?->last_name }}</strong><small class="table-muted">User account</small></td><td>{{ $member->user?->email ?: '—' }}</td><td>{{ $member->role?->label ?: 'No role' }} @if($member->is_owner)<x-ui.badge variant="brand">Owner</x-ui.badge>@endif</td><td>{{ $member->active_property_count }} {{ Str::plural('property', $member->active_property_count) }}</td><td><x-ui.badge :variant="$member->status === 'active' ? 'success' : 'neutral'">{{ ucfirst($member->status) }}</x-ui.badge></td><td>{{ $member->updated_at?->diffForHumans() ?: '—' }}</td><td class="table-actions"><a class="ui-button ui-button--secondary ui-button--small" href="{{ route('settings.members.edit', $member) }}">Edit access</a></td></tr>
            @empty
                <tr><td colspan="7" class="table-empty">No organization members found.</td></tr>
            @endforelse
        </tbody></table></div>
        @if($members->hasPages())<div class="table-pagination">{{ $members->links() }}</div>@endif
    </section>
</div>
@endsection
