@extends('layouts.public', ['title' => 'Session expired — Lodgix', 'description' => 'Your Lodgix session expired. Please return and try again.', 'robots' => 'noindex,follow'])

@section('content')
<section class="public-error-page">
    <x-public.container>
        <x-public.eyebrow>419 · Session expired</x-public.eyebrow>
        <h1>Please return and try that again.</h1>
        <p>Your session expired before the request completed. No enquiry was submitted by this response.</p>
        <div class="public-final-cta__actions"><x-public.button :href="route('public.contact')" variant="primary">Return to contact</x-public.button><x-public.button :href="url('/')" variant="secondary">Back to home</x-public.button></div>
    </x-public.container>
</section>
@endsection
