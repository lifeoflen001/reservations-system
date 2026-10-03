@extends('layouts.public', ['title' => 'Newsletter — '.config('hotel.brand.product_name', 'Lodgix'), 'description' => 'Manage your Lodgix newsletter subscription.', 'robots' => 'noindex,follow'])

@section('content')
<section class="public-newsletter-result public-section">
    <x-public.container>
        <div class="public-newsletter-result__card" data-state="{{ $state }}" tabindex="-1">
            <span class="public-eyebrow">Newsletter</span>
            <h1>{{ $heading }}</h1>
            <p>{{ $message }}</p>
            <div class="public-newsletter-result__actions">
                <a class="public-button public-button--primary" href="{{ route('public.home') }}">Back to Lodgix</a>
                <a class="public-button public-button--secondary" href="{{ route('public.contact') }}">Contact us</a>
            </div>
        </div>
    </x-public.container>
</section>
@endsection
