@php($contactCms = app(\App\Services\PublicWebsiteContentService::class)->page('contact'))
@php($contactHero = $contactCms['sections']['hero'] ?? [])
@extends('layouts.public', [
    'title' => $contactCms['page']->seo_title ?? 'Contact Lodgix — Hotel Management System Enquiries',
    'description' => $contactCms['page']->seo_description ?? 'Contact Lodgix about pricing, implementation, integrations or hotel-management requirements for your independent hotel or lodge.',
    'canonical' => route('public.contact'),
])

@section('content')
<div class="public-page public-page--contact">
        <x-public.page-hero :eyebrow="$contactHero['eyebrow'] ?? 'Lodgix enquiries'" :heading="$contactHero['heading'] ?? 'Let’s talk about your hotel.'" :description="$contactHero['description'] ?? 'Tell us what you operate and what you want Lodgix to handle.'" layout="centered" class="public-page-hero--compact" />

    <x-public.section class="public-contact-section">
        <div class="public-contact-grid">
            <aside class="public-contact-aside">
                <x-public.eyebrow>Start a conversation</x-public.eyebrow>
                <h2>What would you like to explore?</h2>
                <p>Choose a topic to get started, or send us a note about your hotel.</p>
                <nav class="public-contact-topics" aria-label="Contact topics">
                    @foreach(['pricing' => 'Pricing', 'implementation' => 'Implementation', 'integrations' => 'Integrations', 'support' => 'Support'] as $topic => $label)
                        <a href="{{ route('public.contact', ['enquiry_type' => $topic]) }}#contact-form">{{ $label }} <x-ui.icon name="arrow-right" size="14" /></a>
                    @endforeach
                </nav>
                @if(filled($contactEmail))
                    <div class="public-contact-detail"><span>Email</span><a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></div>
                @endif
                <div class="public-contact-next"><strong>What happens next</strong>
                    <ol>
                        <li><span>1</span>Send your enquiry</li>
                        <li><span>2</span>Our team reviews your requirements</li>
                        <li><span>3</span>We continue the discussion using your contact details</li>
                    </ol>
                </div>
            </aside>

            <div class="public-contact-form-shell" id="contact-form">
                @if(session('success'))<div class="public-form-feedback public-form-feedback--success" role="status" aria-live="polite">{{ session('success') }}</div>@endif
                @if($errors->any())
                    <div class="public-form-feedback public-form-feedback--error" role="alert" aria-live="assertive" tabindex="-1">
                        <strong>Please review the highlighted fields.</strong>
                        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('public.contact.submit') }}" class="public-contact-form">
                    @csrf
                    <div class="public-contact-honeypot" aria-hidden="true"><label for="contact-website">Leave this field empty</label><input id="contact-website" type="text" name="website" tabindex="-1" autocomplete="off"></div>
                    <div class="public-contact-fields">
                        <div class="public-form-field">
                            <label for="contact-name">Full name <span aria-hidden="true">*</span></label>
                            <input id="contact-name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="120" required @if($errors->has('name')) aria-invalid="true" aria-describedby="contact-name-error" @endif>
                            @error('name')<small class="public-field-error" id="contact-name-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="public-form-field">
                            <label for="contact-company">Hotel / company name</label>
                            <input id="contact-company" name="company" type="text" value="{{ old('company') }}" autocomplete="organization" maxlength="160" @if($errors->has('company')) aria-invalid="true" aria-describedby="contact-company-error" @endif>
                            @error('company')<small class="public-field-error" id="contact-company-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="public-form-field">
                            <label for="contact-email">Email <span aria-hidden="true">*</span></label>
                            <input id="contact-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="254" required @if($errors->has('email')) aria-invalid="true" aria-describedby="contact-email-error" @endif>
                            @error('email')<small class="public-field-error" id="contact-email-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="public-form-field">
                            <label for="contact-phone">Phone <span>(optional)</span></label>
                            <input id="contact-phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" maxlength="40" @if($errors->has('phone')) aria-invalid="true" aria-describedby="contact-phone-error" @endif>
                            @error('phone')<small class="public-field-error" id="contact-phone-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="public-form-field">
                            <label for="contact-country">Country</label>
                            <input id="contact-country" name="country" type="text" value="{{ old('country') }}" autocomplete="country-name" maxlength="100" @if($errors->has('country')) aria-invalid="true" aria-describedby="contact-country-error" @endif>
                            @error('country')<small class="public-field-error" id="contact-country-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="public-form-field">
                            <label for="contact-hotel-size">Number of rooms <span>(optional)</span></label>
                            <input id="contact-hotel-size" name="hotel_size" type="number" value="{{ old('hotel_size') }}" min="1" max="100000" inputmode="numeric" @if($errors->has('hotel_size')) aria-invalid="true" aria-describedby="contact-hotel-size-error" @endif>
                            @error('hotel_size')<small class="public-field-error" id="contact-hotel-size-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="public-form-field public-form-field--wide">
                            <label for="contact-enquiry-type">Enquiry type <span aria-hidden="true">*</span></label>
                            <select id="contact-enquiry-type" name="enquiry_type" required @if($errors->has('enquiry_type')) aria-invalid="true" aria-describedby="contact-enquiry-type-error" @endif>
                                <option value="">Select an enquiry type</option>
                                @foreach(['general' => 'General Enquiry', 'pricing' => 'Pricing', 'implementation' => 'Implementation', 'integrations' => 'Integrations', 'support' => 'Support'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('enquiry_type', request('enquiry_type')) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('enquiry_type')<small class="public-field-error" id="contact-enquiry-type-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="public-form-field public-form-field--wide">
                            <label for="contact-message">Message <span aria-hidden="true">*</span></label>
                            <textarea id="contact-message" name="message" rows="5" maxlength="5000" required aria-describedby="contact-message-help @if($errors->has('message'))contact-message-error @endif" @if($errors->has('message')) aria-invalid="true" @endif>{{ old('message') }}</textarea>
                            <small id="contact-message-help">Please don’t include passwords, payment card details or other sensitive information.</small>
                            @error('message')<small class="public-field-error" id="contact-message-error">{{ $message }}</small>@enderror
                        </div>
                    </div>
                    <div class="public-contact-form__footer"><p>Submitting this form allows Lodgix to use these details to respond to your enquiry.</p><button class="public-button public-button--primary" type="submit">Send enquiry</button></div>
                </form>
            </div>
        </div>
    </x-public.section>
</div>
@endsection
