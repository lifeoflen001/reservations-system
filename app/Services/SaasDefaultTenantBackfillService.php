<?php

namespace App\Services;

use App\Services\Tenancy\OrganizationOwnershipService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SaasDefaultTenantBackfillService
{
    /** @return array<string, int|string|bool> */
    public function run(bool $dryRun = false): array
    {
        return DB::transaction(function () use ($dryRun): array {
            $report = [
                'organization_id' => 0,
                'organization_created' => false,
                'properties_linked' => 0,
                'uuids_assigned' => 0,
                'memberships_created' => 0,
                'property_access_created' => 0,
                'existing_records_skipped' => 0,
            ];

            $propertyRows = DB::table('properties')->orderBy('id')->get();
            $anchor = $propertyRows->first();

            // A schema migration on an empty installation should not create an
            // orphan organization. The normal seeder/setup flow calls this
            // service again after the first property and administrator exist.
            if (! $anchor && DB::table('users')->count() === 0) {
                return $report;
            }

            $organization = $anchor?->organization_id
                ? DB::table('organizations')->where('id', $anchor->organization_id)->first()
                : null;

            $organizationName = trim((string) ($anchor?->name ?: config('hotel.defaults.property_name', 'Lodgix Organization')));
            $organizationSlug = Str::slug($organizationName) ?: 'lodgix-organization';

            if (! $organization) {
                $organization = DB::table('organizations')->where('slug', $organizationSlug)->first();
            }

            if (! $organization && ! $dryRun) {
                $organizationSlug = $this->uniqueOrganizationSlug($organizationSlug);
                $currency = $anchor?->base_currency_id
                    ? DB::table('currencies')->where('id', $anchor->base_currency_id)->value('code')
                    : null;
                $organizationId = DB::table('organizations')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'name' => $organizationName,
                    'slug' => $organizationSlug,
                    'status' => 'active',
                    'country' => $anchor?->country,
                    'timezone' => $anchor?->timezone,
                    'default_currency' => $currency,
                    'subscription_status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $organization = DB::table('organizations')->where('id', $organizationId)->first();
                $report['organization_created'] = true;
            }

            if (! $organization) {
                $report['organization_id'] = 0;
                $report['properties_linked'] = $propertyRows->count();
                $report['memberships_created'] = DB::table('users')->count();

                return $report;
            }

            $report['organization_id'] = (int) $organization->id;

            if (! $dryRun) {
                foreach ($propertyRows as $property) {
                    $updates = [];
                    if (! $property->organization_id) {
                        $updates['organization_id'] = $organization->id;
                        $report['properties_linked']++;
                    } elseif ((int) $property->organization_id !== (int) $organization->id) {
                        continue;
                    }
                    if (! $property->uuid) {
                        $updates['uuid'] = (string) Str::uuid();
                        $report['uuids_assigned']++;
                    }
                    if (! $property->slug) {
                        $updates['slug'] = $this->uniquePropertySlug(Str::slug((string) $property->name) ?: 'property-'.$property->id, (int) $organization->id, (int) $property->id);
                    }
                    if (! $property->property_code) {
                        $updates['property_code'] = $this->uniquePropertyCode('PROP-'.$property->id, (int) $organization->id, (int) $property->id);
                    }
                    if ($updates !== []) {
                        $updates['updated_at'] = now();
                        DB::table('properties')->where('id', $property->id)->update($updates);
                    } else {
                        $report['existing_records_skipped']++;
                    }
                }
            } else {
                $report['properties_linked'] = $propertyRows->whereNull('organization_id')->count();
                $report['uuids_assigned'] = $propertyRows->whereNull('uuid')->count();
            }

            $properties = DB::table('properties')->where('organization_id', $organization->id)->orderBy('id')->get();
            foreach (DB::table('users')->orderBy('id')->get() as $user) {
                $status = (bool) ($user->is_active ?? true) ? 'active' : 'inactive';
                $membership = DB::table('organization_memberships')
                    ->where('organization_id', $organization->id)
                    ->where('user_id', $user->id)
                    ->first();

                if (! $membership && ! $dryRun) {
                    $membershipId = DB::table('organization_memberships')->insertGetId([
                        'organization_id' => $organization->id,
                        'user_id' => $user->id,
                        'role_id' => $user->role_id,
                        'status' => $status,
                        'joined_at' => $user->created_at ?? now(),
                        'invited_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $membership = DB::table('organization_memberships')->where('id', $membershipId)->first();
                    $report['memberships_created']++;
                } elseif ($membership) {
                    if (! $membership->role_id && $user->role_id && ! $dryRun) {
                        DB::table('organization_memberships')->where('id', $membership->id)->update(['role_id' => $user->role_id, 'updated_at' => now()]);
                    }
                    $report['existing_records_skipped']++;
                } elseif ($dryRun) {
                    $report['memberships_created']++;
                }

                if (! $membership || $dryRun) {
                    continue;
                }

                foreach ($properties as $property) {
                    $exists = DB::table('property_memberships')
                        ->where('membership_id', $membership->id)
                        ->where('property_id', $property->id)
                        ->exists();
                    if (! $exists) {
                        DB::table('property_memberships')->insert([
                            'membership_id' => $membership->id,
                            'property_id' => $property->id,
                            'access_level' => null,
                            'status' => $status,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $report['property_access_created']++;
                    } else {
                        $report['existing_records_skipped']++;
                    }
                }
            }

            if ($dryRun) {
                $report['property_access_created'] = DB::table('users')->count() * $properties->count();
            }

            // Ownership is introduced after the original tenant backfill
            // migration. Keep the legacy backfill safe before that column
            // exists, then guarantee a deterministic owner on every later
            // installation/backfill run.
            if (Schema::hasColumn('organization_memberships', 'is_owner')) {
                app(OrganizationOwnershipService::class)->backfill($dryRun);
            }

            return $report;
        });
    }

    /** @return array<string, int> */
    public function validate(): array
    {
        return [
            'properties_without_organization' => DB::table('properties')->whereNull('organization_id')->count(),
            'properties_without_uuid' => DB::table('properties')->whereNull('uuid')->count(),
            'memberships_without_role' => DB::table('organization_memberships')->whereNull('role_id')->count(),
            'cross_organization_property_access' => DB::table('property_memberships')
                ->join('organization_memberships', 'organization_memberships.id', '=', 'property_memberships.membership_id')
                ->join('properties', 'properties.id', '=', 'property_memberships.property_id')
                ->whereColumn('organization_memberships.organization_id', '!=', 'properties.organization_id')
                ->count(),
        ];
    }

    private function uniqueOrganizationSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;
        while (DB::table('organizations')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function uniquePropertySlug(string $base, int $organizationId, int $propertyId): string
    {
        $slug = $base;
        $suffix = 2;
        while (DB::table('properties')->where('organization_id', $organizationId)->where('slug', $slug)->where('id', '!=', $propertyId)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function uniquePropertyCode(string $base, int $organizationId, int $propertyId): string
    {
        $code = $base;
        $suffix = 2;
        while (DB::table('properties')->where('organization_id', $organizationId)->where('property_code', $code)->where('id', '!=', $propertyId)->exists()) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }
}
