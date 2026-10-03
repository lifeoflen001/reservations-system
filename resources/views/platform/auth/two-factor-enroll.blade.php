@extends('layouts.platform-auth')
@section('content')
<h1 id="platform-auth-title">Set up two-factor authentication</h1>
<p>Two-factor authentication is mandatory for Platform Administration. Scan this QR code with your authenticator app, then confirm the six-digit code.</p>
<div class="platform-2fa-setup-grid">
    <div class="platform-2fa-qr" aria-label="Authenticator QR code">{!! $administrator->twoFactorQrCodeSvg() !!}</div>
    <div>
        <p class="form-help">Account: <strong>{{ $administrator->email }}</strong></p>
        <div class="platform-2fa-secret"><span>Manual setup key</span><code>{{ \Laravel\Fortify\Fortify::currentEncrypter()->decrypt($administrator->two_factor_secret) }}</code></div>
        <form class="platform-login__form" method="POST" action="{{ route('platform.2fa.enroll.confirm') }}">
            @csrf
            <div class="form-field"><label class="form-label" for="code">Authenticator code</label><input class="form-control" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus></div>
            <button class="ui-button ui-button--primary" type="submit">Confirm and continue</button>
        </form>
    </div>
</div>
<p class="form-help">There is no skip option. Recovery codes will be shown once after confirmation.</p>
@endsection
