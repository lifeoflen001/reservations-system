@extends('layouts.app')
@section('content')
<x-page-header title="Pages" subtitle="Manage public page content, metadata and publishing safely.">
    <div class="page-header__action-group">
        <a class="ui-button ui-button--secondary" href="{{ url('/') }}" target="_blank" rel="noopener"><x-ui.icon name="eye" size="16" /> Preview site</a>
    </div>
</x-page-header>
@include('website.partials.nav')
<section class="ui-card website-table-card">
    <header class="ui-card__header"><div><h2>Managed public pages</h2><p>Drafts stay private until an authorized user publishes them.</p></div><span class="table-muted">{{ $pages->count() }} pages</span></header>
    <div class="website-pages-table">
    <x-data.table caption="Managed public pages">
        <thead><tr><th>Page</th><th>Route</th><th>Status</th><th>Last updated</th><th>Updated by</th><th>SEO</th><th class="table-actions-heading">Actions</th></tr></thead>
        <tbody>
            @foreach($pages as $page)
                <tr>
                    <td><strong>{{ $page->name }}</strong><small class="table-muted">{{ $page->route_name }}</small></td>
                    <td><code>/{{ $page->key === 'home' ? '' : $page->key }}</code></td>
                    <td><x-ui.badge variant="{{ $page->hasDraftChanges() ? 'warning' : ($page->status === 'published' ? 'success' : 'neutral') }}">{{ $page->hasDraftChanges() ? 'Modified' : ucfirst($page->status) }}</x-ui.badge></td>
                    <td>{{ $page->updated_at?->format('Y-m-d H:i') }}</td>
                    <td>{{ $page->draftEditor?->display_name ?? '—' }}</td>
                    <td><x-ui.badge variant="{{ $page->seo_title && $page->seo_description ? 'success' : 'warning' }}">{{ $page->seo_title && $page->seo_description ? 'Ready' : 'Needs review' }}</x-ui.badge></td>
                    <td class="website-pages-actions-cell">
                        <div class="website-row-actions">
                            <a class="ui-button ui-button--secondary ui-button--small website-page-edit-link" href="{{ route('website.pages.edit', $page) }}"><x-ui.icon name="edit" size="14" /> Edit</a>
                            <div class="dropdown" data-dropdown>
                                <button type="button" class="icon-button" data-dropdown-toggle aria-expanded="false" aria-label="More actions for {{ $page->name }}"><x-ui.icon name="more-horizontal" size="17" /></button>
                                <div class="dropdown__menu website-row-menu" data-dropdown-menu hidden>
                                    <a class="dropdown__item website-row-menu__edit-mobile" href="{{ route('website.pages.edit', $page) }}"><x-ui.icon name="edit" size="15" /> Edit</a>
                                    <a class="dropdown__item" href="{{ route('website.pages.preview', $page) }}"><x-ui.icon name="eye" size="15" /> Preview draft</a>
                                    <a class="dropdown__item" href="{{ route($page->route_name) }}" target="_blank" rel="noopener"><x-ui.icon name="globe" size="15" /> View live</a>
                                    <a class="dropdown__item" href="{{ route('website.pages.revisions', $page) }}"><x-ui.icon name="history" size="15" /> Revision history</a>
                                    @can('website.pages.publish')
                                        @if($page->hasDraftChanges())
                                            <form method="POST" action="{{ route('website.pages.publish', $page) }}">@csrf<button class="dropdown__item" type="submit"><x-ui.icon name="check" size="15" /> Publish</button></form>
                                        @endif
                                    @endcan
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-data.table>
    </div>
</section>
@include('website.partials.close')
@endsection
