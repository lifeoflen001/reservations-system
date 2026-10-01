@extends('layouts.app')
@section('content')
<x-page-header title="Website media" subtitle="Manage approved public images and accessibility metadata.">
    <button class="ui-button ui-button--primary" type="button" data-modal-open="website-media-upload"><x-ui.icon name="upload" size="16" /> Upload media</button>
</x-page-header>
@include('website.partials.nav')

<section class="ui-card website-media-library">
    <header class="ui-card__header website-media-library__header">
        <div><h2>Media library</h2><p>Only safe image formats are accepted. Archived files remain recoverable.</p></div>
        <div class="website-media-library__tools">
            <span class="table-muted">{{ $media->total() }} assets</span>
            <div class="website-media-view-switch" role="group" aria-label="Media display mode">
                <a class="{{ $view === 'grid' ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['view' => 'grid']) }}" aria-label="Show media as squares" aria-pressed="{{ $view === 'grid' ? 'true' : 'false' }}"><x-ui.icon name="grid" size="15" /> Squares</a>
                <a class="{{ $view === 'list' ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['view' => 'list']) }}" aria-label="Show media as a list" aria-pressed="{{ $view === 'list' ? 'true' : 'false' }}"><x-ui.icon name="menu" size="15" /> List</a>
            </div>
        </div>
    </header>
    <form method="GET" class="filter-toolbar website-media-filter">
        <input type="hidden" name="view" value="{{ $view }}">
        <input class="form-control" type="search" name="search" value="{{ request('search') }}" placeholder="Search files" aria-label="Search media">
        <select class="form-control" name="category" aria-label="Filter by category"><option value="">All categories</option>@foreach(['hero','screenshot','logo','icon','general'] as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ ucfirst($category) }}</option>@endforeach</select>
        <button class="ui-button ui-button--secondary" type="submit"><x-ui.icon name="filter" size="15" /> Filter</button>
    </form>

    @if($view === 'grid')
        <div class="website-media-grid">
            @forelse($media as $asset)
                <article class="website-media-card">
                    <div class="website-media-card__image">
                        <img src="{{ $asset->url() }}" alt="{{ $asset->alt_text ?: $asset->original_filename }}" loading="lazy" decoding="async" onerror="this.hidden=true; this.nextElementSibling.hidden=false;">
                        <div class="website-media-card__image-fallback" hidden><x-ui.icon name="image" size="24" /><span>Preview unavailable</span></div>
                    </div>
                    <div class="website-media-card__details">
                        <strong title="{{ $asset->original_filename }}">{{ $asset->original_filename }}</strong>
                        <small>{{ ucfirst($asset->category) }} · {{ $asset->width ?: '?' }}×{{ $asset->height ?: '?' }} · {{ number_format($asset->file_size / 1024, 1) }} KB</small>
                        @if($asset->alt_text)<small>{{ $asset->alt_text }}</small>@else<x-ui.badge variant="warning">Alt text needed</x-ui.badge>@endif
                    </div>
                    <form method="POST" action="{{ route('website.media.archive', $asset) }}" data-confirm="Archive this media asset?"><div class="website-media-card__actions">@csrf @method('DELETE')<a class="text-button" href="{{ $asset->url() }}" target="_blank" rel="noopener">View image</a><button class="text-button text-button--danger" type="submit">Archive</button></div></form>
                </article>
            @empty
                <div class="website-empty-state"><x-ui.icon name="camera" size="22" /><strong>No media assets.</strong><span>Upload a public image to begin.</span></div>
            @endforelse
        </div>
    @else
        <div class="website-media-list">
            @forelse($media as $asset)
                <div class="website-media-list__row">
                    <div class="website-media-list__thumb">
                        <img src="{{ $asset->url() }}" alt="{{ $asset->alt_text ?: $asset->original_filename }}" loading="lazy" decoding="async" onerror="this.hidden=true; this.nextElementSibling.hidden=false;">
                        <div class="website-media-card__image-fallback" hidden><x-ui.icon name="image" size="19" /></div>
                    </div>
                    <div class="website-media-list__name"><strong title="{{ $asset->original_filename }}">{{ $asset->original_filename }}</strong><small>{{ $asset->alt_text ?: 'Alt text needed' }}</small></div>
                    <span>{{ ucfirst($asset->category) }}</span>
                    <span>{{ $asset->width ?: '?' }}×{{ $asset->height ?: '?' }}</span>
                    <span>{{ number_format($asset->file_size / 1024, 1) }} KB</span>
                    <div class="website-media-list__actions"><a class="text-button" href="{{ $asset->url() }}" target="_blank" rel="noopener">View</a><form method="POST" action="{{ route('website.media.archive', $asset) }}" data-confirm="Archive this media asset?">@csrf @method('DELETE')<button class="text-button text-button--danger" type="submit">Archive</button></form></div>
                </div>
            @empty
                <div class="website-empty-state"><x-ui.icon name="camera" size="22" /><strong>No media assets.</strong><span>Upload a public image to begin.</span></div>
            @endforelse
        </div>
    @endif
    <x-data.pagination :paginator="$media" />
</section>

<x-ui.modal id="website-media-upload" title="Upload media" size="medium" :open="$errors->any()">
    <form method="POST" action="{{ route('website.media.upload') }}" enctype="multipart/form-data" class="settings-form-grid website-media-upload-form" data-website-media-upload>
        @csrf
        <x-form.select name="category" label="Category" required><option value="general">General</option><option value="hero">Hero</option><option value="screenshot">Screenshot</option><option value="logo">Logo</option><option value="icon">Icon</option></x-form.select>
        <x-form.input name="alt_text" label="Alt text" />
        <x-form.textarea name="caption" label="Caption" rows="3"></x-form.textarea>
        <label class="website-dropzone" data-website-media-dropzone>
            <span class="website-dropzone__preview" data-website-media-preview hidden><img alt="Selected image preview"></span>
            <span class="website-dropzone__prompt"><x-ui.icon name="upload" size="22" /><strong>Choose an image file</strong><small>JPG, PNG or WebP up to 10 MB</small><em data-website-media-file>Nothing selected</em></span>
            <input type="file" name="media" accept="image/jpeg,image/png,image/webp" required>
        </label>
        <div class="website-upload-progress" data-website-upload-progress hidden aria-live="polite">
            <div class="website-upload-progress__heading"><strong data-website-upload-status>Ready to upload</strong><span data-website-upload-percent>0%</span></div>
            <div class="website-upload-progress__track" role="progressbar" aria-label="Media upload progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span data-website-upload-bar></span></div>
        </div>
        <div class="modal-form-footer"><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="upload" size="15" /> Upload media</button></div>
    </form>
</x-ui.modal>
@include('website.partials.close')
@endsection
