@extends('layouts.app')
@section('content')
<x-page-header title="Website pricing" subtitle="Manage quote-based public plans without requiring numeric prices." />
@include('website.partials.nav')

<section class="website-pricing-summary" aria-label="Pricing plans">
    @forelse($plans as $plan)
        <article class="ui-card website-pricing-summary__card">
            <header class="website-pricing-summary__header">
                <div>
                    <p class="website-page-kicker">{{ $plan->status === 'published' ? 'Published' : 'Draft' }}</p>
                    <h2>{{ $plan->name }}</h2>
                </div>
                <x-ui.badge variant="{{ $plan->is_highlighted ? 'brand' : 'neutral' }}">{{ $plan->is_highlighted ? 'Highlighted' : 'Standard' }}</x-ui.badge>
            </header>
            <div class="website-pricing-summary__body">
                <div><span class="website-summary-label">Price display</span><strong>{{ $plan->price_display }}</strong></div>
                <div><span class="website-summary-label">Features</span><strong>{{ $plan->features->where('included', true)->count() }} included</strong></div>
                <div><span class="website-summary-label">Availability</span><strong>{{ $plan->is_active ? 'Active' : 'Inactive' }}</strong></div>
            </div>
            <p class="website-pricing-summary__description">{{ $plan->short_description ?: 'No short description provided.' }}</p>
            <footer class="website-pricing-summary__footer">
                <span>{{ $plan->cta_label }}</span>
                <a class="ui-button ui-button--secondary ui-button--small" href="{{ route('website.pricing', ['edit' => $plan->id]) }}">Edit plan</a>
            </footer>
        </article>
    @empty
        <div class="empty-state website-empty-state"><strong>No pricing plans.</strong><span>Seed existing quote-based plans before publishing.</span></div>
    @endforelse
</section>

@if($selectedPlan)
    <section class="ui-card website-pricing-editor">
        <header class="ui-card__header">
            <div><p class="website-page-kicker">Editing plan</p><h2>{{ $selectedPlan->name }}</h2><p>Save changes privately before publishing them on the public site.</p></div>
            <a class="ui-button ui-button--secondary ui-button--small" href="{{ route('website.pricing') }}">Close editor</a>
        </header>
        <form method="POST" action="{{ route('website.pricing.update', $selectedPlan) }}" class="website-pricing-editor__form">
            @csrf @method('PATCH')
            <div class="website-pricing-editor__fields">
                <x-form.input name="name" label="Plan name" :value="$selectedPlan->name" />
                <x-form.input name="price_display" label="Price display" :value="$selectedPlan->price_display" />
                <x-form.input name="cta_label" label="CTA label" :value="$selectedPlan->cta_label" />
                <x-form.input name="cta_url" label="CTA URL" :value="$selectedPlan->cta_url" />
                <x-form.textarea name="short_description" label="Short description" rows="3">{{ $selectedPlan->short_description }}</x-form.textarea>
            </div>
            <aside class="website-pricing-editor__features">
                <span class="website-page-kicker">Included features</span>
                @forelse($selectedPlan->features as $feature)
                    <span class="website-pricing-feature-row"><x-ui.icon name="check" size="14" /> {{ $feature->feature }}</span>
                @empty
                    <span class="table-muted">No features configured.</span>
                @endforelse
            </aside>
            <div class="website-pricing-editor__footer">
                <label class="form-check"><input type="checkbox" name="is_highlighted" value="1" @checked($selectedPlan->is_highlighted)> Highlight plan</label>
                <label class="form-check"><input type="checkbox" name="is_active" value="1" @checked($selectedPlan->is_active)> Active</label>
                <button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="15" /> Save pricing draft</button>
            </div>
        </form>
    </section>
@endif
@include('website.partials.close')
@endsection
