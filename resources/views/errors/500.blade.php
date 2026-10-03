@extends('layouts.public', ['title' => 'Something went wrong — Lodgix', 'description' => 'Lodgix could not complete that request. Please try again.', 'robots' => 'noindex,follow'])

@section('content')
<section class="public-error-page">
    <x-public.container>
        <x-public.eyebrow>500 · Temporary problem</x-public.eyebrow>
        <h1>We couldn’t complete that request.</h1>
        <p>Please try again in a moment. If the problem continues, contact the Lodgix team.</p>
        <div class="public-final-cta__actions"><x-public.button :href="url('/')" variant="primary">Back to home</x-public.button><x-public.button :href="route('public.contact')" variant="secondary">Contact Lodgix</x-public.button></div>
    </x-public.container>
</section>
@endsection
