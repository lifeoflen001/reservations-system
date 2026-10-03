@extends('layouts.platform-auth')
@section('content')
<h1 id="platform-auth-title">Verify Platform Administration</h1>
<p>Enter the six-digit code from your authenticator app. A recovery code can be used once if your authenticator is unavailable.</p>
<form class="platform-login__form" method="POST" action="{{ route('platform.2fa.challenge.verify') }}">
    @csrf
    <div class="form-field"><label class="form-label" for="code">Authenticator code</label><input class="form-control" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" autofocus></div>
    <button class="ui-button ui-button--primary" type="submit">Verify and continue</button>
</form>
<form class="platform-login__form" method="POST" action="{{ route('platform.2fa.challenge.verify') }}">
    @csrf
    <div class="form-field"><label class="form-label" for="recovery_code">Recovery code</label><input class="form-control" id="recovery_code" name="recovery_code" autocomplete="off"></div>
    <button class="ui-button ui-button--secondary" type="submit">Use recovery code</button>
</form>
<p class="form-help"><a class="text-link" href="{{ route('platform.login') }}">Return to Platform sign in</a></p>
@endsection
