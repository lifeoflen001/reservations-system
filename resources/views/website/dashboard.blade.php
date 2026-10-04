@extends('layouts.app')
@section('content')
<x-page-header title="Website overview" subtitle="Manage the public Lodgix website without editing application source code."><div class="page-header__action-group"><a class="ui-button ui-button--secondary" href="{{ url('/') }}" target="_blank" rel="noopener"><x-ui.icon name="eye" size="16" /> Preview site</a><a class="ui-button ui-button--primary" href="{{ route('website.pages.index') }}"><x-ui.icon name="document" size="16" /> Manage pages</a></div></x-page-header>
@include('website.partials.nav')
<section class="kpi-grid website-kpis">
    <x-kpi-card label="Published pages" :value="$publishedPages" context="Live public pages" icon="document" tone="success" />
    <x-kpi-card label="Draft changes" :value="$draftChanges" context="Awaiting review" icon="edit" tone="warning" />
    <x-kpi-card label="Media assets" :value="$mediaAssets" context="Available to the site" icon="camera" tone="info" />
    <x-kpi-card label="New enquiries" :value="$newEnquiries" context="Need attention" icon="mail" tone="brand" />
    <x-kpi-card label="SEO issues" :value="$seoIssues" context="Pages to review" icon="search" tone="danger" />
</section>
<div class="website-dashboard-columns">
    <div class="website-dashboard-main">
        <section class="ui-card"><header class="ui-card__header"><div><h2>Quick actions</h2><p>Common website tasks.</p></div></header><div class="quick-actions-grid">
        @can('website.pages.manage')<a class="website-action-card" href="{{ route('website.pages.edit', ['websitePage' => $pages->firstWhere('key', 'home')]) }}"><span class="website-action-card__icon"><x-ui.icon name="edit" size="17" /></span><span><strong>Edit homepage</strong><small>Update the primary public page.</small></span><x-ui.icon name="chevron-right" size="15" /></a>@endcan
        @can('website.media.manage')<a class="website-action-card" href="{{ route('website.media') }}"><span class="website-action-card__icon"><x-ui.icon name="camera" size="17" /></span><span><strong>Upload media</strong><small>Add approved public images.</small></span><x-ui.icon name="chevron-right" size="15" /></a>@endcan
        @can('website.enquiries.view')<a class="website-action-card" href="{{ route('website.enquiries') }}"><span class="website-action-card__icon"><x-ui.icon name="mail" size="17" /></span><span><strong>Review enquiries</strong><small>Respond to new messages.</small></span><x-ui.icon name="chevron-right" size="15" /></a>@endcan
        @can('website.pricing.manage')<a class="website-action-card" href="{{ route('website.pricing') }}"><span class="website-action-card__icon"><x-ui.icon name="currency" size="17" /></span><span><strong>Edit pricing</strong><small>Maintain quote-based plans.</small></span><x-ui.icon name="chevron-right" size="15" /></a>@endcan
        @can('website.navigation.manage')<a class="website-action-card" href="{{ route('website.navigation') }}"><span class="website-action-card__icon"><x-ui.icon name="menu" size="17" /></span><span><strong>Manage navigation</strong><small>Control public menu labels.</small></span><x-ui.icon name="chevron-right" size="15" /></a>@endcan
        </div></section>
        <section class="ui-card website-recent-enquiries"><header class="ui-card__header"><div><h2>Recent enquiries</h2><p>PII is limited to this authorized workspace.</p></div>@can('website.enquiries.view')<a class="text-link" href="{{ route('website.enquiries') }}">View all <x-ui.icon name="arrow-right" size="13" /></a>@endcan</header>
    @if($recentEnquiries->isEmpty())
        <div class="website-empty-state"><x-ui.icon name="mail" size="22" /><strong>No new enquiries</strong><span>New contact form submissions will appear here.</span></div>
    @else
        <x-data.table caption="Recent website enquiries"><thead><tr><th>Name</th><th>Type</th><th>Received</th><th>Status</th></tr></thead><tbody>@foreach($recentEnquiries as $enquiry)<tr><td><a class="text-link" href="{{ route('website.enquiries.show', $enquiry) }}">{{ $enquiry->name }}</a></td><td>{{ str($enquiry->enquiry_type)->replace('_', ' ')->title() }}</td><td>{{ $enquiry->created_at?->format('Y-m-d H:i') }}</td><td><x-ui.badge variant="{{ $enquiry->status === 'new' ? 'warning' : 'info' }}">{{ ucfirst($enquiry->status) }}</x-ui.badge></td></tr>@endforeach</tbody></x-data.table>
        @endif
        </section>
    </div>
    <aside class="website-dashboard-side">
        <section class="ui-card"><header class="ui-card__header"><div><h2>Publishing status</h2><p>Current publishing state.</p></div><a class="text-link" href="{{ route('website.pages.index') }}">View all</a></header><div class="website-page-summary">
            @foreach($pages->take(5) as $page)<a href="{{ route('website.pages.edit', $page) }}"><span><strong>{{ $page->name }}</strong><small>/{{ $page->key === 'home' ? '' : $page->key }}</small></span><x-ui.badge :variant="$page->status === 'published' && ! $page->hasDraftChanges() ? 'success' : 'warning'">{{ $page->hasDraftChanges() ? 'Modified' : ucfirst($page->status) }}</x-ui.badge></a>@endforeach
        </div></section>
    </aside>
</div>
@include('website.partials.close')
@endsection
