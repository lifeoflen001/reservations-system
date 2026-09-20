@extends('layouts.app')

@section('content')
<x-page-header title="Search results" subtitle="Search reservations, guests, rooms, staff and operational records." />

<form class="search-page-form" action="{{ route('search') }}" method="GET" role="search">
    <div class="search-page-form__input"><x-ui.icon name="search" size="18" /><input type="search" name="q" value="{{ $term }}" placeholder="Search by name, code, room or reference..." aria-label="Search records" autofocus></div>
    <button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="search" size="16" /> Search</button>
</form>

@if ($term === '')
    <div class="ui-card search-empty"><x-ui.icon name="search" size="30" /><h2>Search the workspace</h2><p>Enter a name, reservation code, room number, invoice or any operational keyword to find it quickly.</p></div>
@elseif ($resultCount === 0)
    <div class="ui-card search-empty"><x-ui.icon name="search" size="30" /><h2>No results for “{{ $term }}”</h2><p>Try a guest name, reservation code, room number or a shorter keyword.</p></div>
@else
    <div class="search-summary"><strong>{{ $resultCount }} {{ Str::plural('result', $resultCount) }}</strong><span>Matching “{{ $term }}”</span></div>
    <div class="search-results-grid">
        @foreach ($groups as $group)
            <section class="ui-card search-results-card">
                <header class="ui-card__header"><div class="ui-card__heading"><x-ui.icon name="{{ $group['icon'] }}" size="18" /><h2>{{ $group['label'] }}</h2></div><span class="search-results-card__count">{{ count($group['items']) }}</span></header>
                <div class="search-results-card__body">
                    @foreach ($group['items'] as $item)
                        <a class="search-result" href="{{ $item['url'] }}"><span class="search-result__icon"><x-ui.icon name="{{ $item['icon'] ?? $group['icon'] }}" size="17" /></span><span class="search-result__copy"><strong>{{ $item['title'] }}</strong><small>{{ $item['subtitle'] }}</small></span><span class="search-result__meta">{{ $item['meta'] }}</span><x-ui.icon name="chevron-right" size="15" /></a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endif
@endsection
