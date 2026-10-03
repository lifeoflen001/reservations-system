@extends('layouts.app')

@section('content')
<x-page-header title="Newsletter subscribers" subtitle="Manage Lodgix product updates and hotel operations subscribers.">
    @can('newsletter.export')
        <a class="ui-button ui-button--secondary" href="{{ route('newsletter.export') }}"><x-ui.icon name="download" size="16" /> Export active CSV</a>
    @endcan
</x-page-header>

@if(session('success'))
    <x-feedback.alert type="success">{{ session('success') }}</x-feedback.alert>
@endif

<section class="dashboard-stat-grid newsletter-stat-grid">
    <article class="ui-card"><small>Total subscribers</small><strong>{{ number_format($total) }}</strong><span>All statuses</span></article>
    <article class="ui-card"><small>Active</small><strong>{{ number_format($counts[\App\Models\NewsletterSubscriber::STATUS_ACTIVE] ?? 0) }}</strong><span>Eligible for updates</span></article>
    <article class="ui-card"><small>Pending</small><strong>{{ number_format($counts[\App\Models\NewsletterSubscriber::STATUS_PENDING] ?? 0) }}</strong><span>Awaiting confirmation</span></article>
    <article class="ui-card"><small>Unsubscribed</small><strong>{{ number_format($counts[\App\Models\NewsletterSubscriber::STATUS_UNSUBSCRIBED] ?? 0) }}</strong><span>Retained for suppression</span></article>
</section>

<section class="ui-card">
    <form method="GET" action="{{ route('newsletter.index') }}" class="filter-toolbar">
        <label class="sr-only" for="newsletter-status">Filter by status</label>
        <select id="newsletter-status" name="status" class="form-control">
            <option value="">All statuses</option>
            @foreach(\App\Models\NewsletterSubscriber::STATUSES as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <button class="ui-button ui-button--secondary" type="submit">Filter</button>
    </form>
    <x-data.table caption="Newsletter subscribers">
        <thead><tr><th>Email</th><th>Status</th><th>Source</th><th>Subscribed</th><th>Confirmed</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse($subscribers as $subscriber)
                <tr>
                    <td><strong>{{ $subscriber->email }}</strong></td>
                    <td><x-ui.badge :variant="match($subscriber->status) { 'active' => 'success', 'pending' => 'warning', default => 'neutral' }">{{ ucfirst($subscriber->status) }}</x-ui.badge></td>
                    <td>{{ ucfirst($subscriber->source) }}</td>
                    <td>{{ $subscriber->subscribed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>{{ $subscriber->confirmed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>
                        <div class="table-actions">
                            @can('newsletter.manage')
                                @if($subscriber->status !== \App\Models\NewsletterSubscriber::STATUS_ACTIVE)
                                    <form method="POST" action="{{ route('newsletter.status', $subscriber) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="active"><button class="ui-button ui-button--small ui-button--secondary" type="submit">Activate</button></form>
                                @endif
                                @if($subscriber->status !== \App\Models\NewsletterSubscriber::STATUS_UNSUBSCRIBED)
                                    <form method="POST" action="{{ route('newsletter.status', $subscriber) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="unsubscribed"><button class="ui-button ui-button--small ui-button--secondary" type="submit">Unsubscribe</button></form>
                                @endif
                                <form method="POST" action="{{ route('newsletter.destroy', $subscriber) }}" data-confirm="Delete this subscriber record?">@csrf @method('DELETE')<button class="ui-button ui-button--small ui-button--danger" type="submit">Delete</button></form>
                            @else
                                <span class="table-muted">View only</span>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state"><strong>No newsletter subscribers.</strong><span>New footer subscriptions will appear here.</span></div></td></tr>
            @endforelse
        </tbody>
    </x-data.table>
    <x-data.pagination :paginator="$subscribers" />
</section>
@endsection
