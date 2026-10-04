<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Property;
use App\Models\PropertyMembership;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\MembershipAccessService;
use App\Services\UsageLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PropertyManagementController extends Controller
{
    public function index(TenantContext $context): View
    {
        $organization = $context->requireOrganization();
        $properties = Property::query()
            ->where('organization_id', $organization->getKey())
            ->with('baseCurrency')
            ->withCount(['memberships as user_count' => fn ($query) => $query->where('status', 'active')])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return view('settings.properties', [
            'organization' => $organization,
            'properties' => $properties,
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'canCreate' => auth()->user()->hasPermission('properties.create'),
        ]);
    }

    public function store(Request $request, TenantContext $context, MembershipAccessService $access, UsageLimitService $limits): RedirectResponse
    {
        $organization = $context->requireOrganization();
        $data = $this->validated($request);
        $property = DB::transaction(function () use ($data, $organization, $request, $access, $limits): Property {
            $organization = Organization::query()->lockForUpdate()->findOrFail($organization->getKey());
            $limits->assertCanConsume($organization, 'properties');
            $property = Property::create($this->normalized($data) + [
                'organization_id' => $organization->getKey(),
                'status' => 'active',
                'default_language' => 'en',
            ]);

            $membership = OrganizationMembership::query()->firstOrCreate(
                ['organization_id' => $organization->getKey(), 'user_id' => $request->user()->getKey()],
                ['role_id' => $request->user()->role_id, 'status' => 'active', 'joined_at' => now()],
            );
            PropertyMembership::query()->firstOrCreate(
                ['membership_id' => $membership->getKey(), 'property_id' => $property->getKey()],
                ['status' => 'active', 'access_level' => 'owner'],
            );
            $access->audit($organization, $request->user(), 'property.created', null, $property, ['property_code' => $property->property_code]);

            return $property;
        });

        $context->setOrganization($organization);

        return redirect()->route('settings.properties.index')->with('success', "Property {$property->name} created with no operational data.");
    }

    public function edit(Property $property, TenantContext $context): View
    {
        $this->assertCurrentOrganization($property, $context);

        return view('settings.property-edit', [
            'organization' => $context->requireOrganization(),
            'property' => $property,
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request, Property $property, TenantContext $context, MembershipAccessService $access): RedirectResponse
    {
        $this->assertCurrentOrganization($property, $context);
        $data = $this->validated($request, $property);
        $before = $property->only(['name', 'property_code', 'status', 'timezone', 'base_currency_id']);
        $property->update($this->normalized($data) + ['status' => $data['status'] ?? $property->status]);
        $access->audit($context->requireOrganization(), $request->user(), 'property.updated', null, $property, [
            'changed_fields' => collect($property->only(array_keys($before)))->filter(fn ($value, $key) => (string) ($before[$key] ?? '') !== (string) ($value ?? ''))->keys()->values()->all(),
            'status_before' => $before['status'], 'status_after' => $property->status,
        ]);

        if ((int) $context->propertyId() === (int) $property->getKey() && $property->status !== 'active') {
            $context->clearTenantSensitiveSessionState();
            $context->setOrganization($context->requireOrganization());
        }

        return redirect()->route('settings.properties.index')->with('success', 'Property settings updated.');
    }

    public function access(Property $property, TenantContext $context, MembershipAccessService $access): View
    {
        $this->assertCurrentOrganization($property, $context);
        $organization = $context->requireOrganization();
        $members = $access->membersForProperty($property)->get();
        $allMembers = $access->members($organization)->get();
        $selectedMemberIds = PropertyMembership::query()->where('property_id', $property->getKey())->where('status', 'active')->pluck('membership_id')->map(fn ($id): int => (int) $id)->all();

        return view('settings.property-access', compact('organization', 'property', 'members', 'allMembers', 'selectedMemberIds'));
    }

    public function updateAccess(Request $request, Property $property, TenantContext $context, MembershipAccessService $access): RedirectResponse
    {
        $this->assertCurrentOrganization($property, $context);
        $data = $request->validate(['membership_ids' => ['array'], 'membership_ids.*' => ['integer']]);
        $access->syncPropertyAccess($property, $request->user(), $data['membership_ids'] ?? []);

        return redirect()->route('settings.properties.access', $property)->with('success', 'Property access updated.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Property $property = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'property_code' => ['required', 'string', 'max:64', Rule::unique('properties', 'property_code')->where(fn ($query) => $query->where('organization_id', app(TenantContext::class)->organizationId()))->ignore($property)],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'timezone'],
            'base_currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function normalized(array $data): array
    {
        $data['property_code'] = strtoupper(trim((string) $data['property_code']));
        $data['slug'] = trim(Str::slug((string) $data['name']).'-'.Str::slug((string) $data['property_code']), '-');
        unset($data['status']);

        return $data;
    }

    private function assertCurrentOrganization(Property $property, TenantContext $context): void
    {
        abort_unless((int) $property->organization_id === (int) $context->requireOrganization()->getKey(), 404);
    }
}
