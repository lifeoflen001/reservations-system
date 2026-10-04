@php
    $publicBrand = config('hotel.brand');
    $markPath = (string) ($publicBrand['mark'] ?? 'assets/branding/lodgix-mark.png');
    $markAvailable = file_exists(public_path(ltrim($markPath, '/')));
    $websiteContent = app(\App\Services\PublicWebsiteContentService::class);
    $footerDescription = $websiteContent->setting('footer_description', $publicBrand['product_name'].' connects reservations, rooms, hotel operations, staff, POS, finance and reporting in one workspace.');
    $copyright = $websiteContent->setting('copyright', '© '.now()->year.' '.$publicBrand['product_name']);
@endphp

<footer class="public-footer">
    <x-public.container>
        <section class="public-footer__newsletter" aria-labelledby="public-newsletter-title">
            <div class="public-footer__newsletter-copy">
                <span class="public-eyebrow public-eyebrow--dark">Lodgix updates</span>
                <h2 id="public-newsletter-title">Useful ideas for running an independent hotel.</h2>
                <p>Occasional product news and practical hotel operations notes. No noise.</p>
            </div>
            <form method="POST" action="{{ route('newsletter.subscribe') }}" class="public-newsletter-form" data-newsletter-form>
                @csrf
                <div class="public-contact-honeypot" aria-hidden="true">
                    <label for="newsletter-website">Website</label>
                    <input id="newsletter-website" type="text" name="website" tabindex="-1" autocomplete="off">
                </div>
                <div class="public-newsletter-form__row">
                    <label class="public-sr-only" for="newsletter-email">Work email address</label>
                    <input id="newsletter-email" name="email" type="email" autocomplete="email" inputmode="email" placeholder="Work email address" value="{{ old('email') }}" aria-describedby="newsletter-note @error('email', 'newsletter') newsletter-email-error @enderror" required>
                    <button class="public-button public-button--primary" type="submit" data-newsletter-submit>Subscribe</button>
                </div>
                @error('email', 'newsletter')<p class="public-newsletter-message public-newsletter-message--error" id="newsletter-email-error" role="alert">{{ $message }}</p>@enderror
                @if(session('newsletter_status'))
                    @php($newsletterMessage = match(session('newsletter_status')) { 'pending' => 'Check your inbox to confirm your subscription.', 'already' => 'You are already subscribed to Lodgix updates.', default => 'You’re subscribed. Thanks for joining us.' })
                    <p class="public-newsletter-message" id="newsletter-feedback" role="status" tabindex="-1">{{ $newsletterMessage }}</p>
                @endif
                <p class="public-newsletter-form__note" id="newsletter-note">Subscribe with your work email. You can unsubscribe at any time.</p>
            </form>
        </section>
        <div class="public-footer__grid">
            <div class="public-footer__identity">
                <a class="public-brand public-brand--footer" href="{{ url('/') }}" aria-label="{{ $publicBrand['product_name'] }} home">
                    <span class="public-brand__mark">@if($markAvailable)<img src="{{ asset($markPath) }}" alt="">@else<x-ui.icon name="building" size="20" />@endif</span>
                    <span class="public-brand__text">{{ $publicBrand['product_name'] }}</span>
                </a>
                <p>{{ $footerDescription }}</p>
            </div>
            <div>
                <h2>Product</h2>
                <a href="{{ route('public.product') }}">Product</a>
                <a href="{{ route('public.pricing') }}">Pricing</a>
            </div>
            <div>
                <h2>Solutions</h2>
                <a href="{{ route('public.operations') }}">Operations</a>
                <a href="{{ route('public.pos') }}">POS</a>
                <a href="{{ route('public.finance') }}">Finance</a>
                <a href="{{ route('public.security') }}">Security</a>
                <a href="{{ route('public.integrations') }}">Integrations</a>
            </div>
            <div>
                <h2>Company</h2>
                <a href="{{ route('public.contact') }}">Contact</a>
                <a href="{{ auth()->check() ? route('dashboard') : route('login') }}">{{ auth()->check() ? 'Open Dashboard' : 'Sign In' }}</a>
                @guest<a href="{{ route('register') }}">Get Started</a>@endguest
            </div>
        </div>
        <div class="public-footer__bottom">
            <span>{{ $copyright }}</span>
        </div>
    </x-public.container>
</footer>
