@extends('layouts.platform')
@section('content')
<header class="platform-page-header"><div><h1>System health</h1><p>Safe operational checks. Optional services are labeled honestly when they cannot be verified.</p></div></header>
<div class="platform-grid platform-grid--three">@foreach($checks as $key => $check)<article class="platform-card"><header class="platform-card__header"><div><h2>{{ str_replace('_', ' ', ucfirst($key)) }}</h2><p>{{ $check['detail'] }}</p></div><span class="ui-badge ui-badge--{{ $check['status'] === 'healthy' ? 'success' : ($check['status'] === 'warning' ? 'warning' : 'danger') }}">{{ $check['label'] }}</span></header><div class="platform-card__body"><span class="form-help">No credentials, payloads or private connection details are displayed.</span></div></article>@endforeach</div>
@endsection
