@extends('layouts.platform')
@section('content')
<header class="platform-page-header"><div><span class="ui-badge ui-badge--warning">Read-only support workspace</span><h1 style="margin-top:10px">{{ ucfirst($module) }} detail</h1><p>{{ $session->organization?->name }} · {{ $session->property?->name }}</p></div><a class="ui-button ui-button--secondary" href="{{ route('platform.support.workspace.module', [$session, $module]) }}">Back to {{ ucfirst($module) }}</a></header>
@include('platform.support.workspace._nav')
<section class="platform-card"><header class="platform-card__header"><div><h2>{{ $module === 'reservations' ? $record->code : $record->full_name }}</h2><p>Support inspection only. No edit or mutation actions are available.</p></div></header><div class="platform-card__body"><dl class="platform-definition">@foreach($record->getAttributes() as $key => $value)@if(! in_array(strtolower($key), ['password','two_factor_secret','two_factor_recovery_codes','remember_token','metadata'], true))<dt>{{ str_replace('_',' ',ucfirst($key)) }}</dt><dd>{{ is_scalar($value) || $value === null ? ($value ?? '—') : 'Redacted' }}</dd>@endif @endforeach</dl></div></section>
@endsection
