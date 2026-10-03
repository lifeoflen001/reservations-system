<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanLimit;
use App\Models\PlatformAdministrator;
use App\Models\PlatformAuditLog;
use App\Models\PlatformSupportSession;
use App\Models\Property;
use App\Models\Subscription;
use App\Services\Platform\PlatformAuditService;
use App\Services\Platform\PlatformHealthService;
use App\Services\Platform\PlatformOrganizationService;
use App\Services\Platform\PlatformPropertyService;
use App\Services\Platform\PlatformSubscriptionService;
use App\Services\Platform\SupportAccessService;
use App\Services\EntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlatformController extends Controller
{
    public function dashboard(PlatformHealthService $health): View
    {
        return view('platform.dashboard', [
            'metrics' => [
                'organizations' => Organization::count(),
                'active_organizations' => Organization::where('status', 'active')->count(),
                'suspended_organizations' => Organization::where('status', 'suspended')->count(),
                'properties' => Property::count(),
                'customer_users' => \App\Models\User::count(),
                'subscriptions' => Subscription::count(),
                'trialing_subscriptions' => Subscription::where('status', 'trialing')->count(),
                'active_subscriptions' => Subscription::where('status', 'active')->count(),
            ],
            'organizations' => Organization::query()->withCount('properties')->latest('created_at')->limit(6)->get(),
            'properties' => Property::query()->with('organization:id,name')->latest('created_at')->limit(6)->get(),
            'activity' => PlatformAuditLog::query()->with('administrator:id,name')->latest()->limit(8)->get(),
            'health' => $health->snapshot(),
        ]);
    }

    public function organizations(Request $request, PlatformOrganizationService $service): View
    {
        return view('platform.organizations.index', [
            'organizations' => $service->paginate($request->only(['search', 'status', 'subscription_status'])),
            'filters' => $request->only(['search', 'status', 'subscription_status']),
        ]);
    }

    public function organization(Organization $organization, EntitlementService $entitlements): View
    {
        return view('platform.organizations.show', [
            'organization' => $organization->load([
                'properties' => fn ($query) => $query->withCount('memberships')->orderBy('name'),
                'memberships' => fn ($query) => $query->where('status', 'active')->where('is_owner', true)->with('user:id,name,email'),
                'subscriptions.plan',
            ]),
            'supportSessions' => $organization->supportSessions()->with(['administrator:id,name', 'property:id,name'])->latest()->limit(10)->get(),
            'auditLogs' => PlatformAuditLog::query()->where('organization_id', $organization->getKey())->with('administrator:id,name')->latest()->limit(10)->get(),
            'properties' => $organization->properties()->orderBy('name')->get(),
            'entitlements' => $entitlements->snapshot($organization),
            'plans' => Plan::query()->where('status', 'active')->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function assignPlan(Request $request, Organization $organization, PlatformAuditService $audit, EntitlementService $entitlements): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        $plan = Plan::query()->where('status', 'active')->findOrFail($data['plan_id']);
        $subscription = $organization->subscriptions()->latest('id')->with('plan')->first();
        $before = $subscription?->plan?->code;
        if (! $subscription) {
            $subscription = Subscription::create([
                'organization_id' => $organization->getKey(), 'plan_id' => $plan->getKey(),
                'status' => in_array($organization->subscription_status, Subscription::STATUSES, true) ? $organization->subscription_status : 'active',
                'starts_at' => now(),
            ]);
        } else {
            $subscription->update(['plan_id' => $plan->getKey()]);
        }
        $entitlements->forgetForOrganization($organization->getKey());
        $audit->record($this->administrator(), 'platform.subscription_plan_assigned', $subscription, [
            'organization_id' => $organization->getKey(), 'previous_plan' => $before,
            'new_plan' => $plan->code, 'reason' => $data['reason'],
        ], $organization->getKey(), null, $request);

        return back()->with('success', 'Organization plan assignment updated.');
    }

    public function properties(Request $request, PlatformPropertyService $service): View
    {
        return view('platform.properties.index', [
            'properties' => $service->paginate($request->only(['search', 'status', 'organization_id'])),
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'status', 'organization_id']),
        ]);
    }

    public function property(Property $property): View
    {
        return view('platform.properties.show', [
            'property' => $property->load(['organization:id,name,uuid,status', 'memberships.membership.user:id,name,email']),
            'auditLogs' => PlatformAuditLog::query()->where('property_id', $property->getKey())->with('administrator:id,name')->latest()->limit(10)->get(),
        ]);
    }

    public function subscriptions(Request $request, PlatformSubscriptionService $service): View
    {
        return view('platform.subscriptions.index', [
            'subscriptions' => $service->paginate($request->only(['status', 'organization_id'])),
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['status', 'organization_id']),
        ]);
    }

    public function subscription(Subscription $subscription): View
    {
        return view('platform.subscriptions.show', ['subscription' => $subscription->load(['organization', 'plan'])]);
    }

    public function plans(Request $request): View
    {
        $plans = Plan::query()->withCount(['subscriptions', 'features', 'limits'])->orderBy('sort_order')->orderBy('name')->paginate(20)->withQueryString();
        return view('platform.plans.index', compact('plans'));
    }

    public function createPlan(): View
    {
        return view('platform.plans.form', [
            'plan' => new Plan(['status' => 'active', 'is_public' => false]),
            'features' => Feature::query()->where('status', 'active')->orderBy('category')->orderBy('name')->get(),
            'limits' => ['properties' => 'Properties', 'users' => 'Active users', 'rooms' => 'Active rooms'],
            'selectedFeatures' => [],
            'selectedLimits' => [],
        ]);
    }

    public function storePlan(Request $request, PlatformAuditService $audit): RedirectResponse
    {
        $data = $this->validatedPlan($request);
        $plan = DB::transaction(function () use ($data): Plan {
            $plan = Plan::create([
                'uuid' => (string) Str::uuid(), 'code' => Str::lower(trim($data['code'])),
                'slug' => Str::slug($data['code']), 'name' => trim($data['name']),
                'description' => $data['description'] ?? null, 'status' => $data['status'],
                'is_public' => request()->boolean('is_public'), 'is_system' => false,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);
            $this->syncPlanConfiguration($plan, $data);
            return $plan;
        });
        $audit->record($this->administrator(), 'platform.plan_created', $plan, ['plan_code' => $plan->code], null, null, $request);
        return redirect()->route('platform.plans.edit', $plan)->with('success', 'Plan created.');
    }

    public function plan(Plan $plan, EntitlementService $entitlements): View
    {
        $plan->load(['features', 'limits'])->loadCount('subscriptions');
        return view('platform.plans.show', compact('plan'));
    }

    public function editPlan(Plan $plan): View
    {
        $plan->load(['features', 'limits']);
        return view('platform.plans.form', [
            'plan' => $plan,
            'features' => Feature::query()->where('status', 'active')->orderBy('category')->orderBy('name')->get(),
            'limits' => ['properties' => 'Properties', 'users' => 'Active users', 'rooms' => 'Active rooms'],
            'selectedFeatures' => $plan->features->where('pivot.enabled', true)->pluck('id')->all(),
            'selectedLimits' => $plan->limits->pluck('value', 'key')->all(),
        ]);
    }

    public function updatePlan(Request $request, Plan $plan, PlatformAuditService $audit): RedirectResponse
    {
        $data = $this->validatedPlan($request, $plan);
        $before = [
            'name' => $plan->name, 'status' => $plan->status,
            'features' => $plan->features()->wherePivot('enabled', true)->pluck('features.key')->all(),
            'limits' => $plan->limits()->pluck('value', 'key')->all(),
        ];
        DB::transaction(function () use ($data, $plan): void {
            $plan->update([
                'code' => Str::lower(trim($data['code'])), 'slug' => Str::slug($data['code']),
                'name' => trim($data['name']), 'description' => $data['description'] ?? null,
                'status' => $data['status'], 'is_public' => request()->boolean('is_public'),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);
            $this->syncPlanConfiguration($plan, $data);
        });
        $plan->refresh()->load(['features', 'limits']);
        $audit->record($this->administrator(), 'platform.plan_updated', $plan, [
            'before' => $before,
            'after' => ['name' => $plan->name, 'status' => $plan->status, 'features' => $plan->features->where('pivot.enabled', true)->pluck('key')->all(), 'limits' => $plan->limits->pluck('value', 'key')->all()],
        ], null, null, $request);
        return back()->with('success', 'Plan configuration saved.');
    }

    public function features(): View
    {
        $features = Feature::query()->withCount('plans')->orderBy('category')->orderBy('name')->paginate(30);
        return view('platform.features.index', compact('features'));
    }

    /** @return array<string, mixed> */
    private function validatedPlan(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:plans,code,'.($plan?->id ?: 'NULL')],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,inactive'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'features' => ['array'], 'features.*' => ['integer', 'exists:features,id'],
            'limits' => ['array'], 'limits.properties' => ['nullable', 'integer', 'min:0'], 'limits.users' => ['nullable', 'integer', 'min:0'], 'limits.rooms' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function syncPlanConfiguration(Plan $plan, array $data): void
    {
        $featureIds = collect($data['features'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values();
        PlanFeature::query()->where('plan_id', $plan->getKey())->delete();
        foreach ($featureIds as $featureId) PlanFeature::create(['plan_id' => $plan->getKey(), 'feature_id' => $featureId, 'enabled' => true]);

        PlanLimit::query()->where('plan_id', $plan->getKey())->delete();
        foreach (($data['limits'] ?? []) as $key => $value) {
            if ($value === null || $value === '') continue;
            PlanLimit::create(['plan_id' => $plan->getKey(), 'key' => $key, 'value' => (int) $value]);
        }
    }

    public function support(Request $request): View
    {
        return view('platform.support.index', [
            'sessions' => PlatformSupportSession::query()->with(['administrator:id,name', 'organization:id,name', 'property:id,name'])->latest()->paginate(20)->withQueryString(),
            'organizations' => Organization::query()->where('status', 'active')->with(['properties' => fn ($query) => $query->where('status', 'active')->orderBy('name')])->orderBy('name')->get(),
            'current' => app(SupportAccessService::class)->current($this->administrator(), $request),
        ]);
    }

    public function startSupport(Request $request, SupportAccessService $service): RedirectResponse
    {
        $data = $request->validate(['organization_id' => ['required', 'integer', 'exists:organizations,id'], 'property_id' => ['nullable', 'integer', 'exists:properties,id'], 'reason' => ['required', 'string', 'min:10', 'max:1000'], 'duration' => ['required', 'integer', 'min:5', 'max:60']]);
        $organization = Organization::findOrFail($data['organization_id']);
        $property = ! empty($data['property_id']) ? Property::findOrFail($data['property_id']) : null;
        $session = $service->start($this->administrator(), $organization, $property, $data['reason'], (int) $data['duration'], $request);

        return redirect()->route('platform.support.workspace', $session)->with('success', 'Read-only support mode started and recorded.');
    }

    public function supportWorkspace(PlatformSupportSession $platformSupportSession, SupportAccessService $service, Request $request): View
    {
        $service->assertOwnedActive($this->administrator(), $platformSupportSession);
        return view('platform.support.workspace', ['session' => $platformSupportSession->load(['organization', 'property'])]);
    }

    public function enterSupport(PlatformSupportSession $platformSupportSession, SupportAccessService $service, Request $request): RedirectResponse
    {
        $service->enter($this->administrator(), $platformSupportSession, $request);
        return redirect()->route('platform.support.workspace', $platformSupportSession);
    }

    public function endSupport(Request $request, SupportAccessService $service): RedirectResponse
    {
        $service->end($this->administrator(), $service->current($this->administrator(), $request), $request);
        return redirect()->route('platform.support')->with('success', 'Support mode ended.');
    }

    public function health(PlatformHealthService $service): View
    {
        return view('platform.health', ['checks' => $service->snapshot()]);
    }

    public function audit(Request $request): View
    {
        $logs = PlatformAuditLog::query()->with(['administrator:id,name', 'organization:id,name', 'property:id,name'])
            ->when($request->filled('action'), fn ($query) => $query->where('action', 'like', '%'.str_replace('%', '', $request->string('action')).'%'))
            ->when($request->filled('administrator_id'), fn ($query) => $query->where('platform_administrator_id', $request->integer('administrator_id')))
            ->when($request->filled('organization_id'), fn ($query) => $query->where('organization_id', $request->integer('organization_id')))
            ->latest()->paginate(25)->withQueryString();
        return view('platform.audit.index', ['logs' => $logs, 'administrators' => PlatformAdministrator::orderBy('name')->get(['id', 'name']), 'organizations' => Organization::orderBy('name')->get(['id', 'name']), 'filters' => $request->only(['action', 'administrator_id', 'organization_id'])]);
    }

    public function administrators(): View
    {
        return view('platform.administrators.index', ['administrators' => PlatformAdministrator::query()->withCount('supportSessions')->latest()->paginate(20)]);
    }

    public function storeAdministrator(Request $request, PlatformAuditService $audit): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'email' => ['required', 'email', 'max:254', 'unique:platform_administrators,email'], 'password' => ['required', 'string', 'min:12', 'confirmed']]);
        $administrator = PlatformAdministrator::create(['uuid' => (string) Str::uuid(), 'name' => trim($data['name']), 'email' => Str::lower(trim($data['email'])), 'password' => Hash::make($data['password']), 'role' => 'platform_admin', 'permissions' => PlatformAdministrator::DEFAULT_PERMISSIONS, 'status' => PlatformAdministrator::ACTIVE]);
        $audit->record($this->administrator(), 'platform.administrator_created', $administrator, ['email_domain' => Str::after($administrator->email, '@')], null, null, $request);
        return back()->with('success', 'Platform Administrator created.');
    }

    public function updateAdministratorStatus(Request $request, PlatformAdministrator $platformAdministrator, PlatformAuditService $audit): RedirectResponse
    {
        abort_if($platformAdministrator->is($this->administrator()), 422, 'You cannot disable your own Platform Administrator account.');
        $status = $request->validate(['status' => ['required', 'in:active,disabled']])['status'];
        if ($status === 'disabled' && PlatformAdministrator::where('status', 'active')->whereKeyNot($platformAdministrator->getKey())->doesntExist()) abort(422, 'The last active Platform Administrator cannot be disabled.');
        $platformAdministrator->forceFill(['status' => $status])->save();
        $audit->record($this->administrator(), 'platform.administrator_status_changed', $platformAdministrator, ['status' => $status], null, null, $request);
        return back()->with('success', 'Platform Administrator status updated.');
    }

    public function profile(): View
    {
        return view('platform.profile', ['administrator' => $this->administrator()]);
    }

    public function updatePassword(Request $request, PlatformAuditService $audit): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password:platform'], 'password' => ['required', 'string', 'min:12', 'confirmed']]);
        $administrator = $this->administrator();
        $administrator->forceFill(['password' => Hash::make($data['password'])])->save();
        $audit->record($administrator, 'platform.password_changed', $administrator, [], null, null, $request);
        return back()->with('success', 'Password updated.');
    }

    private function administrator(): PlatformAdministrator
    {
        return Auth::guard('platform')->user();
    }
}
