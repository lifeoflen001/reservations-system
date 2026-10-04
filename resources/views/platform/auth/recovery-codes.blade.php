@extends('layouts.platform-auth')
@section('content')
<h1 id="platform-auth-title">Save your recovery codes</h1>
<p>Store these codes in a secure password manager. Each code works once. They will not be shown again after leaving this page.</p>
<div class="platform-recovery-codes" role="list">@foreach($codes as $code)<code role="listitem">{{ $code }}</code>@endforeach</div>
<a class="ui-button ui-button--primary" href="{{ route('platform.dashboard') }}">Continue to control plane</a>
@endsection
