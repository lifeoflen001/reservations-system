@extends('layouts.public', ['title' => 'Page not found — Lodgix', 'description' => 'The Lodgix page you requested could not be found.', 'robots' => 'noindex,follow'])

@section('content')
<section class="public-error-page">
    <x-public.container>
        <x-public.eyebrow>404 · Page not found</x-public.eyebrow>
        <h1>That page has moved or doesn’t exist.</h1>
        <p>Use the main navigation to continue exploring Lodgix for independent hotels and lodges.</p>
        <div class="public-final-cta__actions"><x-public.button :href="url('/')" variant="primary">Back to home</x-public.button><x-public.button :href="route('public.contact')" variant="secondary">Contact Lodgix</x-public.button></div>
    </x-public.container>
</section>
@endsection
