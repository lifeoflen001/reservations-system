@php
    $status = isset($status) ? (int) $status : (isset($exception) && method_exists($exception, 'getStatusCode') ? (int) $exception->getStatusCode() : 500);
    $messages = [
        401 => ['eyebrow' => '401', 'title' => 'Sign in required.', 'copy' => 'You need to sign in before you can access this part of Lodgix.'],
        403 => ['eyebrow' => '403', 'title' => 'Access restricted.', 'copy' => 'Your account does not have permission to open this page.'],
        404 => ['eyebrow' => '404', 'title' => 'Page not found.', 'copy' => 'The page you are looking for does not exist. Check the address and try again.'],
        419 => ['eyebrow' => '419', 'title' => 'Session expired.', 'copy' => 'Your session has expired. Return to a fresh page and try again.'],
        429 => ['eyebrow' => '429', 'title' => 'A short pause.', 'copy' => 'Lodgix is receiving too many requests right now. Please wait a moment and try again.'],
        500 => ['eyebrow' => '500', 'title' => 'Something went wrong.', 'copy' => 'Lodgix could not complete that request. Please try again or return to the workspace.'],
        503 => ['eyebrow' => '503', 'title' => 'We will be right back.', 'copy' => 'Lodgix is temporarily unavailable while we finish a system update.'],
    ];
    $message = $messages[$status] ?? ['eyebrow' => (string) $status, 'title' => 'Something went wrong.', 'copy' => 'We could not complete that request. Please try again or return home.'];
    $brandWordmark = 'assets/images/landing/lodgix-wordmark.webp';
    $brandMark = 'assets/branding/lodgix-mark.png';
    $hasWordmark = file_exists(public_path($brandWordmark));
    $hasMark = file_exists(public_path($brandMark));
    $homeUrl = url('/');
    $dashboardUrl = \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : $homeUrl;
    $loginUrl = \Illuminate\Support\Facades\Route::has('login') ? route('login') : $homeUrl;
    $isAuthenticated = auth()->check();
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d1014">
    <title>{{ $message['eyebrow'] }} · Lodgix</title>
    <link rel="icon" href="{{ asset($brandMark) }}" type="image/png">
    <style>
        :root {
            --error-bg: #0d1014;
            --error-surface: #151a20;
            --error-border: #2b333e;
            --error-text: #f7f8fa;
            --error-muted: #aab4c2;
            --error-subtle: #7d8795;
            --error-orange: #e67e2f;
            --error-orange-dark: #cf6d25;
            --error-orange-soft: rgb(230 126 47 / 12%);
        }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; margin: 0; }
        body {
            overflow-x: hidden;
            color: var(--error-text);
            background: var(--error-bg);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.5;
        }
        a { color: inherit; text-decoration: none; }
        a:focus-visible { outline: 2px solid #8fc4f2; outline-offset: 4px; }
        .error-page { position: relative; display: grid; min-height: 100vh; grid-template-columns: minmax(420px, .84fr) minmax(0, 1.16fr); isolation: isolate; background: var(--error-bg); }
        .error-page__content { position: relative; z-index: 2; display: flex; min-width: 0; flex-direction: column; justify-content: space-between; padding: clamp(28px, 5vw, 72px) clamp(24px, 6vw, 104px); }
        .error-page__header { display: flex; align-items: center; }
        .error-page__brand { display: inline-flex; width: min(190px, 58vw); align-items: center; }
        .error-page__brand img { display: block; width: 100%; height: auto; }
        .error-page__brand-fallback { color: var(--error-text); font-size: 22px; font-weight: 800; letter-spacing: -.04em; }
        .error-page__brand-fallback span { color: var(--error-orange); }
        .error-page__copy { width: min(100%, 610px); margin-block: auto; padding-block: clamp(68px, 11vh, 150px); }
        .error-page__eyebrow { display: inline-flex; align-items: center; gap: 13px; margin-bottom: 24px; color: var(--error-orange); font-size: 12px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; }
        .error-page__eyebrow::before { width: 35px; height: 1px; background: var(--error-orange); content: ""; }
        .error-page h1 { max-width: 650px; margin: 0 0 22px; color: var(--error-text); font-size: clamp(42px, 5.3vw, 78px); font-weight: 760; letter-spacing: -.055em; line-height: .98; }
        .error-page__description { max-width: 530px; margin: 0; color: var(--error-muted); font-size: clamp(15px, 1.3vw, 18px); line-height: 1.7; }
        .error-page__actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 34px; }
        .error-page__button { display: inline-flex; min-height: 48px; align-items: center; justify-content: center; gap: 10px; border: 1px solid transparent; border-radius: 3px; padding: 12px 19px; font-size: 14px; font-weight: 760; transition: background .15s ease, border-color .15s ease, transform .15s ease; }
        .error-page__button:hover { transform: translateY(-1px); }
        .error-page__button--primary { background: var(--error-orange); color: #fff; }
        .error-page__button--primary:hover { background: var(--error-orange-dark); }
        .error-page__button--secondary { border-color: var(--error-border); background: rgb(255 255 255 / 3%); color: var(--error-muted); }
        .error-page__button--secondary:hover { border-color: var(--error-orange); color: var(--error-text); }
        .error-page__footer { display: flex; align-items: center; justify-content: space-between; gap: 18px; color: var(--error-subtle); font-size: 12px; }
        .error-page__footer strong { color: var(--error-muted); font-weight: 700; }
        .error-page__visual { position: relative; min-width: 0; overflow: hidden; background: #171b20; }
        .error-page__visual::before { position: absolute; z-index: 1; inset: 0; background: linear-gradient(100deg, #0d1014 0%, rgb(13 16 20 / 72%) 18%, rgb(13 16 20 / 20%) 75%, rgb(13 16 20 / 45%) 100%), linear-gradient(180deg, rgb(13 16 20 / 14%), rgb(13 16 20 / 75%)); content: ""; }
        .error-page__visual::after { position: absolute; z-index: 2; inset: 0; background: radial-gradient(circle at 72% 38%, rgb(230 126 47 / 18%), transparent 32%), linear-gradient(135deg, transparent 0 45%, rgb(230 126 47 / 8%) 45.1% 45.4%, transparent 45.5%); content: ""; pointer-events: none; }
        .error-page__visual-image { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: 62% center; filter: saturate(.65) brightness(.62); opacity: .86; }
        .error-page__visual-mark { position: absolute; z-index: 3; top: clamp(30px, 7vw, 100px); right: clamp(24px, 7vw, 120px); width: clamp(90px, 12vw, 180px); opacity: .14; filter: grayscale(1) brightness(2.8); }
        .error-page__visual-caption { position: absolute; z-index: 3; right: clamp(24px, 5vw, 72px); bottom: clamp(28px, 5vw, 64px); left: clamp(24px, 5vw, 72px); display: flex; align-items: center; justify-content: space-between; border-top: 1px solid rgb(255 255 255 / 16%); padding-top: 14px; color: rgb(255 255 255 / 62%); font-size: 11px; letter-spacing: .08em; text-transform: uppercase; }
        .error-page__visual-caption span:last-child { color: var(--error-orange); }
        @media (max-width: 900px) {
            .error-page { display: block; min-height: 100svh; }
            .error-page__visual { position: absolute; z-index: 0; inset: 0; min-height: 100%; }
            .error-page__visual::before { background: linear-gradient(90deg, rgb(13 16 20 / 96%) 0%, rgb(13 16 20 / 82%) 55%, rgb(13 16 20 / 48%) 100%), linear-gradient(180deg, rgb(13 16 20 / 20%), rgb(13 16 20 / 85%)); }
            .error-page__content { min-height: 100svh; padding: 28px 24px; }
            .error-page__copy { padding-block: 72px 86px; }
            .error-page__visual-caption { display: none; }
        }
        @media (max-width: 480px) {
            .error-page__brand { width: min(160px, 62vw); }
            .error-page h1 { font-size: clamp(40px, 13vw, 58px); }
            .error-page__actions { align-items: stretch; flex-direction: column; }
            .error-page__button { width: 100%; }
            .error-page__footer { align-items: flex-start; flex-direction: column; }
        }
        @media (prefers-reduced-motion: reduce) {
            .error-page__button { transition: none; }
            .error-page__button:hover { transform: none; }
        }
    </style>
</head>
<body>
    <div class="error-page">
        <section class="error-page__content" aria-labelledby="error-title">
            <header class="error-page__header">
                <a class="error-page__brand" href="{{ $homeUrl }}" aria-label="Lodgix home">
                    @if ($hasWordmark)
                        <img src="{{ asset($brandWordmark) }}" alt="Lodgix">
                    @else
                        <span class="error-page__brand-fallback">Lod<span>gix</span></span>
                    @endif
                </a>
            </header>

            <div class="error-page__copy">
                <div class="error-page__eyebrow">{{ $message['eyebrow'] }}</div>
                <h1 id="error-title">{{ $message['title'] }}</h1>
                <p class="error-page__description">{{ $message['copy'] }}</p>
                <div class="error-page__actions">
                    <a class="error-page__button error-page__button--primary" href="{{ $homeUrl }}">Return home <span aria-hidden="true">↗</span></a>
                    @if ($isAuthenticated)
                        <a class="error-page__button error-page__button--secondary" href="{{ $dashboardUrl }}">Open dashboard</a>
                    @elseif ($status === 401)
                        <a class="error-page__button error-page__button--secondary" href="{{ $loginUrl }}">Sign in</a>
                    @endif
                </div>
            </div>

            <footer class="error-page__footer">
                <span><strong>Lodgix</strong> · Hotel management system</span>
                <span aria-label="Error status">Status {{ $status }}</span>
            </footer>
        </section>

        <aside class="error-page__visual" aria-hidden="true">
            <img class="error-page__visual-image" src="{{ asset('assets/images/landing/lodgix-dashboard-light.webp') }}" alt="">
            @if ($hasMark)<img class="error-page__visual-mark" src="{{ asset($brandMark) }}" alt="">@endif
            <div class="error-page__visual-caption"><span>Connected hotel operations</span><span>Lodgix</span></div>
        </aside>
    </div>
</body>
</html>
