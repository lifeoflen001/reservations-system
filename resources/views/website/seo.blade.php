@extends('layouts.app')
@section('content')
<x-page-header title="Website SEO" subtitle="Review public metadata page by page and keep search previews consistent." />
@include('website.partials.nav')

@php
    $readyPages = $pages->filter(fn ($page) => filled($page->seo_title) && filled($page->seo_description))->count();
    $indexablePages = $pages->where('robots_index', true)->count();
    $openGraphPages = $pages->filter(fn ($page) => filled($page->og_title) || filled($page->og_description) || $page->ogMedia)->count();
@endphp

<section class="kpi-grid website-kpis website-seo-kpis" aria-label="SEO summary">
    <x-kpi-card label="Public pages" :value="$pages->count()" context="Available to review" icon="document" tone="info" />
    <x-kpi-card label="Metadata ready" :value="$readyPages" context="Title and description" icon="check" tone="success" />
    <x-kpi-card label="Needs attention" :value="$pages->count() - $readyPages" context="Pages to complete" icon="alert" tone="warning" />
    <x-kpi-card label="Indexable" :value="$indexablePages" context="Allowed in search" icon="search" tone="brand" />
</section>

@if($selectedPage)
    <section class="ui-card website-seo-editor">
        <header class="ui-card__header">
            <div>
                <p class="website-page-kicker">Editing metadata</p>
                <h2>{{ $selectedPage->name }}</h2>
                <p><code>/{{ $selectedPage->key === 'home' ? '' : $selectedPage->key }}</code></p>
            </div>
            <a class="ui-button ui-button--secondary ui-button--small" href="{{ route('website.seo') }}"><x-ui.icon name="arrow-left" size="14" /> Back to SEO list</a>
        </header>
        <form method="POST" action="{{ route('website.pages.update', $selectedPage) }}" class="website-seo-editor__form">
            @csrf @method('PATCH')
            <div class="website-seo-editor__fields">
                <x-form.input name="seo_title" label="SEO title" :value="$selectedPage->seo_title" maxlength="255" />
                <x-form.input name="og_title" label="Open Graph title" :value="$selectedPage->og_title" maxlength="255" />
                <x-form.textarea name="seo_description" label="Meta description" rows="3" maxlength="320">{{ $selectedPage->seo_description }}</x-form.textarea>
                <x-form.textarea name="og_description" label="Open Graph description" rows="3" maxlength="320">{{ $selectedPage->og_description }}</x-form.textarea>
            </div>
            <div class="website-seo-editor__footer">
                <div class="website-seo-editor__toggles">
                    <x-form.toggle name="robots_index" label="Allow indexing" :checked="$selectedPage->robots_index" />
                    <x-form.toggle name="robots_follow" label="Allow link following" :checked="$selectedPage->robots_follow" />
                </div>
                <button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="15" /> Save metadata</button>
            </div>
        </form>
        <div class="website-seo-preview website-seo-preview--editor">
            <span class="website-page-kicker">Search preview</span>
            <strong>{{ $selectedPage->seo_title ?: $selectedPage->name }}</strong>
            <span>{{ url('/'.($selectedPage->key === 'home' ? '' : $selectedPage->key)) }}</span>
            <p>{{ $selectedPage->seo_description ?: 'Add a concise description for search engines.' }}</p>
        </div>
    </section>
@endif

<section class="ui-card website-seo-list-card">
    <header class="ui-card__header">
        <div><h2>Public page metadata</h2><p>Choose a page to edit its title, descriptions and indexing controls.</p></div>
        <span class="table-muted">{{ $openGraphPages }} with social metadata</span>
    </header>
    <x-data.table caption="Public page SEO metadata">
        <thead><tr><th>Page</th><th>Route</th><th>SEO title</th><th>Description</th><th>Social metadata</th><th>Indexing</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        @foreach($pages as $page)
            @php($metadataReady = filled($page->seo_title) && filled($page->seo_description))
            <tr>
                <td><strong>{{ $page->name }}</strong></td>
                <td><code>/{{ $page->key === 'home' ? '' : $page->key }}</code></td>
                <td><span class="website-table-truncate">{{ $page->seo_title ?: 'Not set' }}</span></td>
                <td><span class="website-table-truncate">{{ $page->seo_description ?: 'Not set' }}</span></td>
                <td>{{ ($page->og_title || $page->og_description || $page->ogMedia) ? 'Configured' : 'Not set' }}</td>
                <td>{{ $page->robots_index ? 'Index' : 'No index' }} · {{ $page->robots_follow ? 'Follow' : 'No follow' }}</td>
                <td><x-ui.badge :variant="$metadataReady ? 'success' : 'warning'">{{ $metadataReady ? 'Ready' : 'Review' }}</x-ui.badge></td>
                <td><a class="ui-button ui-button--secondary ui-button--small" href="{{ route('website.seo', ['edit' => $page->id]) }}">Edit SEO</a></td>
            </tr>
        @endforeach
        </tbody>
    </x-data.table>
</section>
@include('website.partials.close')
@endsection
