@extends('layouts.platform')
@section('content')
<header class="platform-page-header"><div><span class="ui-badge ui-badge--warning">Read-only support workspace</span><h1 style="margin-top:10px">Search results</h1><p>Only records belonging to {{ $session->property?->name ?: 'the organization metadata scope' }} are searched.</p></div></header>
@include('platform.support.workspace._nav')
<section class="platform-card"><header class="platform-card__header"><div><h2>Search: {{ $term ?: 'Enter a term' }}</h2><p>Cross-organization operational search is unavailable in support mode.</p></div></header><div class="platform-card__body">@foreach(['reservations'=>'Reservations','clients'=>'Clients','rooms'=>'Rooms','tasks'=>'Tasks'] as $key => $label)<h3>{{ $label }}</h3><ul>@forelse($results[$key] as $row)<li>{{ $row->code ?? $row->full_name ?? $row->room_number ?? $row->title }}</li>@empty<li>No matching {{ strtolower($label) }}.</li>@endforelse</ul>@endforeach</div></section>
@endsection
