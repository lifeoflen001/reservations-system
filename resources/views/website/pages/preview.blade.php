@extends('layouts.app')
@section('content')
<x-page-header title="Preview: {{ $page->name }}" subtitle="Draft preview is restricted to authorized website users."><a class="ui-button ui-button--secondary" href="{{ route('website.pages.edit', $page) }}">Back to editor</a></x-page-header>
@include('website.partials.nav')
<section class="ui-card website-preview"><header class="ui-card__header"><div><h2>{{ data_get($page->sections->firstWhere('section_key', 'hero')->draft_content, 'heading', $page->name) }}</h2><p>{{ data_get($page->sections->firstWhere('section_key', 'hero')->draft_content, 'description', 'Draft content preview') }}</p></div><x-ui.badge variant="warning">Draft preview</x-ui.badge></header>@foreach($page->sections as $section) @if($section->is_visible && $section->section_key !== 'hero')<article><span class="public-eyebrow">{{ data_get($section->draft_content, 'eyebrow') }}</span><h3>{{ data_get($section->draft_content, 'heading', str($section->section_key)->headline()) }}</h3><p>{{ data_get($section->draft_content, 'description') }}</p></article>@endif @endforeach</section>
@include('website.partials.close')
@endsection
