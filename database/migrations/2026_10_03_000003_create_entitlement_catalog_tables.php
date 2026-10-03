<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('name');
            $table->string('slug', 120)->nullable()->unique()->after('code');
            $table->boolean('is_public')->default(false)->index()->after('status');
            $table->boolean('is_system')->default(false)->index()->after('is_public');
            $table->unsignedInteger('sort_order')->default(0)->index()->after('is_system');
        });

        Schema::create('features', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('category', 80)->index();
            $table->string('type', 24)->default('boolean');
            $table->string('status', 24)->default('active')->index();
            $table->boolean('is_system')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('plan_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('features')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'feature_id']);
        });

        Schema::create('plan_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('key', 100);
            $table->unsignedBigInteger('value')->nullable();
            $table->string('unit', 40)->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'key']);
        });

        $now = now();
        $features = [
            ['key' => 'reservations', 'name' => 'Reservations', 'category' => 'Core'],
            ['key' => 'room_planning', 'name' => 'Room Planning', 'category' => 'Core'],
            ['key' => 'clients', 'name' => 'Clients', 'category' => 'Core'],
            ['key' => 'rooms', 'name' => 'Rooms', 'category' => 'Core'],
            ['key' => 'housekeeping', 'name' => 'Housekeeping', 'category' => 'Operations'],
            ['key' => 'maintenance', 'name' => 'Maintenance', 'category' => 'Operations'],
            ['key' => 'tasks', 'name' => 'Tasks', 'category' => 'Operations'],
            ['key' => 'announcements', 'name' => 'Announcements', 'category' => 'Operations'],
            ['key' => 'pos', 'name' => 'POS', 'category' => 'Sales / POS'],
            ['key' => 'finance', 'name' => 'Finance', 'category' => 'Finance'],
            ['key' => 'reports', 'name' => 'Reports', 'category' => 'Reporting'],
            ['key' => 'integrations', 'name' => 'Integrations', 'category' => 'Integrations'],
            ['key' => 'api_access', 'name' => 'API Access', 'category' => 'Platform / Advanced'],
            ['key' => 'multi_property', 'name' => 'Multi-property', 'category' => 'Platform / Advanced'],
        ];

        foreach ($features as $feature) {
            DB::table('features')->updateOrInsert(
                ['key' => $feature['key']],
                [
                    'name' => $feature['name'],
                    'description' => $feature['name'].' workspace access.',
                    'category' => $feature['category'],
                    'type' => 'boolean',
                    'status' => 'active',
                    'is_system' => true,
                    'updated_at' => $now,
                    'created_at' => DB::table('features')->where('key', $feature['key'])->value('created_at') ?: $now,
                ],
            );
        }

        $legacyPlanId = DB::table('plans')->where('code', 'legacy_full_access')->value('id');
        if (! $legacyPlanId) {
            $legacyPlanId = DB::table('plans')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'code' => 'legacy_full_access',
                'slug' => 'legacy-full-access',
                'name' => 'Legacy Full Access',
                'description' => 'Internal compatibility entitlement for organizations created before SAAS-08.',
                'status' => 'active',
                'is_public' => false,
                'is_system' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('features')->where('status', 'active')->pluck('id') as $featureId) {
            DB::table('plan_features')->updateOrInsert(
                ['plan_id' => $legacyPlanId, 'feature_id' => $featureId],
                ['enabled' => true, 'configuration' => null, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        DB::table('subscriptions')->whereNull('plan_id')->update(['plan_id' => $legacyPlanId, 'updated_at' => $now]);

        DB::table('organizations')->whereNotExists(function ($query): void {
            $query->selectRaw('1')->from('subscriptions')->whereColumn('subscriptions.organization_id', 'organizations.id');
        })->orderBy('id')->eachById(function ($organization) use ($legacyPlanId, $now): void {
            DB::table('subscriptions')->insert([
                'organization_id' => $organization->id,
                'plan_id' => $legacyPlanId,
                'status' => in_array($organization->subscription_status, ['trialing', 'active', 'past_due', 'grace_period', 'suspended', 'cancelled'], true)
                    ? $organization->subscription_status
                    : 'active',
                'trial_ends_at' => $organization->trial_ends_at,
                'starts_at' => $organization->created_at ?: $now,
                'ends_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_limits');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('features');
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn(['description', 'slug', 'is_public', 'is_system', 'sort_order']);
        });
    }
};
