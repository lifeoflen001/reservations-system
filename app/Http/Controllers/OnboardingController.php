<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Plan;
use App\Services\OrganizationProvisioningService;
use App\Services\OnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function start(Request $request, OnboardingService $onboarding): View
    {
        return view('onboarding.start', ['onboarding' => $onboarding->stateFor($request->user())]);
    }

    public function organization(): View
    {
        return view('onboarding.organization', ['timezones' => \DateTimeZone::listIdentifiers()]);
    }

    public function storeOrganization(Request $request, OrganizationProvisioningService $provisioning): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'country' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'in:'.implode(',', \DateTimeZone::listIdentifiers())],
            'currency' => ['required', 'string', 'max:10'],
            'billing_email' => ['nullable', 'email', 'max:254'],
        ]);
        $provisioning->createFor($request->user(), $data);
        return redirect()->route('onboarding.plan');
    }

    public function plan(Request $request, OnboardingService $onboarding): View|RedirectResponse
    {
        $state = $onboarding->stateFor($request->user());
        if (! $state) return redirect()->route('onboarding.organization');
        $plans = Plan::query()->with(['features', 'limits'])->where('status', 'active')->where('is_public', true)->where('is_onboarding_eligible', true)->where('is_system', false)->orderBy('sort_order')->orderBy('name')->get();
        return view('onboarding.plan', compact('state', 'plans'));
    }

    public function storePlan(Request $request, OnboardingService $onboarding): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'integer']]);
        $onboarding->selectPlan($request->user(), (int) $data['plan_id']);
        return redirect()->route('onboarding.property');
    }

    public function property(Request $request, OnboardingService $onboarding): View|RedirectResponse
    {
        $state = $onboarding->stateFor($request->user());
        if (! $state) return redirect()->route('onboarding.organization');
        if (! $state->plan_completed) return redirect()->route('onboarding.plan');
        return view('onboarding.property', ['state' => $state, 'timezones' => \DateTimeZone::listIdentifiers()]);
    }

    public function storeProperty(Request $request, OnboardingService $onboarding): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'property_code' => ['required', 'string', 'max:64', 'alpha_dash'],
            'country' => ['nullable', 'string', 'max:100'], 'timezone' => ['required', 'in:'.implode(',', \DateTimeZone::listIdentifiers())],
            'currency' => ['required', 'string', 'max:10'], 'email' => ['nullable', 'email', 'max:254'], 'phone' => ['nullable', 'string', 'max:50'],
        ]);
        $onboarding->createProperty($request->user(), $data);
        return redirect()->route('onboarding.hotel');
    }

    public function hotel(Request $request, OnboardingService $onboarding): View|RedirectResponse
    {
        $state = $onboarding->stateFor($request->user());
        if (! $state) return redirect()->route('onboarding.organization');
        if (! $state->property_completed) return redirect()->route('onboarding.property');
        return view('onboarding.hotel', compact('state'));
    }

    public function finish(Request $request, OnboardingService $onboarding): RedirectResponse
    {
        $onboarding->finish($request->user());
        return redirect()->route('dashboard')->with('success', 'Your Lodgix workspace is ready.');
    }
}
