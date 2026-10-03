<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\PlatformAdministrator;
use App\Models\Organization;
use App\Models\User;
use App\Services\EntitlementService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_and_entitlements_are_separate_and_plan_changes_are_cached_safely(): void
    {
        $this->seed(DatabaseSeeder::class);
        $organization = User::firstOrFail()->organizations()->firstOrFail();
        $finance = Feature::where('key', 'finance')->firstOrFail();
        $limited = Plan::create([
            'uuid' => fake()->uuid(), 'code' => 'test-core', 'slug' => 'test-core',
            'name' => 'Test Core', 'status' => 'active', 'is_public' => false,
        ]);
        PlanFeature::create(['plan_id' => $limited->id, 'feature_id' => $finance->id, 'enabled' => true]);
        PlanLimit::create(['plan_id' => $limited->id, 'key' => 'properties', 'value' => 1]);
        $organization->subscriptions()->latest('id')->firstOrFail()->update(['plan_id' => $limited->id, 'status' => 'active']);

        $service = app(EntitlementService::class);
        $this->assertTrue($service->hasFeature($organization->fresh(), 'finance'));
        $this->assertFalse($service->hasFeature($organization->fresh(), 'pos'));
        $this->assertSame(1, $service->limit($organization->fresh(), 'properties'));
        $this->assertFalse($service->canConsume($organization->fresh(), 'properties'));

        $organization->subscriptions()->latest('id')->firstOrFail()->update(['plan_id' => Plan::where('code', 'legacy_full_access')->value('id')]);
        $this->assertTrue($service->hasFeature($organization->fresh(), 'pos'));
    }

    public function test_customer_subscription_view_is_read_only(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('username', 'admin')->firstOrFail();
        $this->actingAs($admin)->get(route('settings.subscription'))
            ->assertOk()
            ->assertSee('Subscription')
            ->assertSee('Legacy Full Access')
            ->assertDontSee('Save plan');
    }

    public function test_platform_plan_assignment_is_protected_and_audited(): void
    {
        $this->seed(DatabaseSeeder::class);
        $organization = Organization::firstOrFail();
        $plan = Plan::create([
            'uuid' => fake()->uuid(), 'code' => 'test-assigned', 'slug' => 'test-assigned',
            'name' => 'Test Assigned', 'status' => 'active', 'is_public' => false,
        ]);
        $administrator = PlatformAdministrator::factory()->create();

        $this->actingAs($administrator, 'platform')
            ->patch(route('platform.organizations.subscription.plan', $organization), ['plan_id' => $plan->id, 'reason' => 'Controlled entitlement acceptance test'])
            ->assertRedirect();

        $this->assertDatabaseHas('subscriptions', ['organization_id' => $organization->id, 'plan_id' => $plan->id]);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'platform.subscription_plan_assigned', 'organization_id' => $organization->id]);
    }
}
