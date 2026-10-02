@extends('layouts.app')

@section('content')
<x-page-header title="Organization audit" subtitle="Immutable administration history for {{ $organization->name }}. Platform audit data is not shown here." />
<div class="organization-admin-layout">
    @include('settings.partials.organization-nav')
    <section class="ui-card organization-table-card">
        <header class="ui-card__header"><div><p class="settings-eyebrow">Audit history</p><h2>Administration changes</h2><p class="form-help">Access changes, organization edits, property changes, and tenant administration actions.</p></div></header>
        <form method="GET" class="organization-filter-bar organization-filter-bar--audit"><x-form.input name="from" type="date" label="From" :value="request('from')" /><x-form.input name="to" type="date" label="To" :value="request('to')" /><x-form.select name="user_id" label="User" :options="['' => 'All users'] + $members->mapWithKeys(fn ($member) => [$member->user_id => $member->user?->name ?: $member->user?->email])->all()" :value="request('user_id')" /><x-form.select name="property_id" label="Property" :options="['' => 'All properties'] + $properties->pluck('name', 'id')->all()" :value="request('property_id')" /><x-form.input name="action" label="Action" :value="request('action')" placeholder="property.created" /><button class="ui-button ui-button--secondary" type="submit">Filter</button></form>
        <div class="table-scroll"><table class="data-table organization-audit-table"><thead><tr><th>Date</th><th>Actor</th><th>Action</th><th>Target</th><th>Property</th><th>Summary</th></tr></thead><tbody>
            @forelse($logs as $log)
                <tr><td>{{ $log->created_at?->format('d M Y H:i') }}</td><td>{{ $log->actor?->name ?: $log->actor?->email ?: 'System' }}</td><td><code>{{ $log->action }}</code></td><td>{{ $log->targetUser?->name ?: ($log->target_type ? class_basename($log->target_type) : '—') }}</td><td>{{ $log->property?->name ?: 'Organization' }}</td><td>{{ collect($log->metadata ?? [])->map(fn ($value, $key) => $key.': '.(is_array($value) ? implode(', ', $value) : $value))->implode(' · ') ?: '—' }}</td></tr>
            @empty
                <tr><td colspan="6" class="table-empty">No organization administration events recorded yet.</td></tr>
            @endforelse
        </tbody></table></div>
        @if($logs->hasPages())<div class="table-pagination">{{ $logs->links() }}</div>@endif
    </section>
</div>
@endsection
