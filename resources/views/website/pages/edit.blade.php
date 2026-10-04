@extends('layouts.app')
@section('content')
@php
    $hasDraftChanges = $page->hasDraftChanges();
    $isPublished = $page->status === 'published' && filled($page->published_at);
    $pageState = $isPublished ? ($hasDraftChanges ? 'Modified' : 'Published') : ($hasDraftChanges ? 'Modified' : 'Draft');
    $latestRevision = $page->revisions->sortByDesc('version')->first();
@endphp
<x-page-header title="Edit {{ $page->name }}" subtitle="Build a draft, preview it privately, then publish when it is ready.">
    <div class="page-header__action-group">
        <a class="ui-button ui-button--secondary" href="{{ route('website.pages.index') }}"><x-ui.icon name="arrow-left" size="16" /> Pages</a>
        <a class="ui-button ui-button--secondary" href="{{ route($page->route_name) }}" target="_blank" rel="noopener"><x-ui.icon name="globe" size="16" /> View live</a>
        <a class="ui-button ui-button--secondary" href="{{ route('website.pages.preview', $page) }}"><x-ui.icon name="eye" size="16" /> Preview draft</a>
    </div>
</x-page-header>
@include('website.partials.nav')
<div class="website-editor-heading"><span class="website-page-kicker">{{ $page->key === 'home' ? 'Primary public page' : 'Public page' }}</span><div class="website-editor-heading__title"><x-ui.badge variant="{{ $pageState === 'Published' ? 'success' : ($pageState === 'Modified' ? 'warning' : 'neutral') }}">{{ $pageState }}</x-ui.badge></div></div>
<div class="editor-layout website-editor-layout">
    <main class="editor-layout__main">
        <section class="ui-card">
            <header class="ui-card__header"><div><h2>Page content</h2><p>Edit structured sections. Changes are saved privately as draft.</p></div><x-ui.badge variant="{{ $hasDraftChanges ? 'warning' : 'success' }}">{{ $hasDraftChanges ? 'Draft changes' : 'In sync' }}</x-ui.badge></header>
            <div class="website-section-list">
                @foreach($page->sections as $section)
                    <article class="website-section-editor">
                        <details class="website-section-details" @if($loop->first || $section->hasDraftChanges()) open @endif>
                            <summary class="website-section-editor__header">
                                <div class="website-section-editor__title"><span class="drag-handle" aria-hidden="true">⋮⋮</span><span class="website-section-editor__icon"><x-ui.icon name="document" size="15" /></span><div><strong>{{ str($section->section_key)->replace('_', ' ')->title() }}</strong><small>{{ str($section->section_type)->replace('_', ' ')->title() }}</small></div><x-ui.badge variant="{{ $section->hasDraftChanges() ? 'warning' : 'neutral' }}">{{ $section->hasDraftChanges() ? 'Draft changes' : 'Published' }}</x-ui.badge></div>
                                <span class="website-section-editor__chevron" aria-hidden="true"><x-ui.icon name="chevron-down" size="15" /></span>
                            </summary>
                            <div class="website-section-editor__body">
                                <div class="website-section-editor__tools">
                                    @if($loop->first)<span class="icon-button is-disabled" aria-hidden="true"><x-ui.icon name="arrow-up" size="15" /></span>@else<form method="POST" action="{{ route('website.pages.sections.move', [$page, $section, 'up']) }}">@csrf<button class="icon-button" type="submit" aria-label="Move section up"><x-ui.icon name="arrow-up" size="15" /></button></form>@endif
                                    @if($loop->last)<span class="icon-button is-disabled" aria-hidden="true"><x-ui.icon name="arrow-down" size="15" /></span>@else<form method="POST" action="{{ route('website.pages.sections.move', [$page, $section, 'down']) }}">@csrf<button class="icon-button" type="submit" aria-label="Move section down"><x-ui.icon name="arrow-down" size="15" /></button></form>@endif
                                </div>
                                <form method="POST" action="{{ route('website.pages.update', $page) }}" class="website-section-form">
                                    @csrf @method('PATCH')<input type="hidden" name="section_id" value="{{ $section->id }}">
                                    <div class="website-section-heading-fields"><x-form.input name="content[eyebrow]" label="Eyebrow" :value="data_get($section->draft_content, 'eyebrow')" /><x-form.input name="content[heading]" label="Heading" :value="data_get($section->draft_content, 'heading')" /></div>
                                    <x-form.textarea name="content[description]" label="Description" rows="3">{{ data_get($section->draft_content, 'description') }}</x-form.textarea>
                                    @if(data_get($section->draft_content, 'primary_cta_label') !== null)<div class="website-section-heading-fields"><x-form.input name="content[primary_cta_label]" label="Primary CTA label" :value="data_get($section->draft_content, 'primary_cta_label')" /><x-form.input name="content[primary_cta_url]" label="Primary CTA URL" :value="data_get($section->draft_content, 'primary_cta_url')" /></div>@endif
                                    <div class="website-section-editor__footer"><x-form.toggle name="content[visible]" label="Visible on public page" :checked="$section->is_visible" help="Hide without deleting the draft." /><button class="ui-button ui-button--secondary ui-button--small" type="submit"><x-ui.icon name="save" size="14" /> Save section draft</button></div>
                                </form>
                            </div>
                        </details>
                    </article>
                @endforeach
            </div>
        </section>
    </main>
    <aside class="editor-layout__aside website-publishing-panel">
        <section class="ui-card"><header class="ui-card__header"><div><h2>Publishing</h2><p>Control what is live on the public site.</p></div><x-ui.badge variant="{{ $pageState === 'Published' ? 'success' : ($pageState === 'Modified' ? 'warning' : 'neutral') }}">{{ $pageState }}</x-ui.badge></header><dl class="website-publishing-summary"><div><dt>Published version</dt><dd>{{ $isPublished ? 'v'.$page->version : '—' }}</dd></div><div><dt>Last published</dt><dd>{{ $page->published_at?->format('Y-m-d H:i') ?? 'Not yet published' }}</dd></div><div><dt>Updated by</dt><dd>{{ $page->draftEditor?->display_name ?? 'System' }}</dd></div></dl><div class="website-publishing-actions"><a class="ui-button ui-button--secondary" href="{{ route('website.pages.preview', $page) }}"><x-ui.icon name="eye" size="15" /> Preview draft</a>@can('website.pages.publish')<form method="POST" action="{{ route('website.pages.publish', $page) }}" data-submit-lock>@csrf<button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="check" size="15" /> Publish changes</button></form>@endcan</div></section>
        <section class="ui-card"><header class="ui-card__header"><div><h2>Page settings</h2><p>Search preview and metadata summary for this page.</p></div></header><div class="website-seo-preview"><span class="website-page-kicker">SEO preview</span><strong>{{ $page->seo_title ?: $page->name }}</strong><span>/{{ $page->key === 'home' ? '' : $page->key }}</span><p>{{ $page->seo_description ?: 'Add a concise description for search engines.' }}</p></div><a class="text-link" href="{{ route('website.seo') }}">Open SEO manager <x-ui.icon name="arrow-right" size="14" /></a></section>
        <section class="ui-card"><header class="ui-card__header"><div><h2>Revision history</h2><p>Recover an earlier published snapshot.</p></div></header><div class="website-revision-summary">@if($latestRevision)<strong>Version {{ $latestRevision->version }}</strong><span>{{ $latestRevision->created_at?->format('Y-m-d H:i') }} · {{ $latestRevision->creator?->display_name ?? 'System' }}</span>@else<span>No published revisions yet.</span>@endif</div><a class="ui-button ui-button--ghost" href="{{ route('website.pages.revisions', $page) }}"><x-ui.icon name="history" size="15" /> View revisions</a></section>
    </aside>
</div>
@include('website.partials.close')
@endsection
