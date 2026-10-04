<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\EntitlementService;
use Illuminate\Console\Command;

class SaasEntitlements extends Command
{
    protected $signature = 'saas:entitlements {organization : Organization id, UUID or slug}';
    protected $description = 'Display the effective Lodgix plan, features, limits and usage for an organization.';

    public function handle(EntitlementService $entitlements): int
    {
        $value = (string) $this->argument('organization');
        $organization = Organization::query()->whereKey(is_numeric($value) ? (int) $value : 0)
            ->orWhere('uuid', $value)->orWhere('slug', $value)->first();
        if (! $organization) { $this->error('Organization not found.'); return self::FAILURE; }

        $snapshot = $entitlements->snapshot($organization);
        $this->line('Organization: '.$organization->name.' ('.$organization->uuid.')');
        $this->line('Plan: '.($snapshot['plan']?->name ?: 'Unassigned'));
        $this->line('Subscription: '.($snapshot['subscription']?->status ?: 'None'));
        $this->table(['Feature', 'Enabled'], collect($snapshot['features'])->map(fn ($enabled, $key) => [$key, $enabled ? 'yes' : 'no'])->values()->all());
        $this->table(['Limit', 'Value', 'Usage', 'Remaining'], collect($snapshot['limits'])->map(fn ($limit, $key) => [$key, $limit === null ? 'Unlimited' : $limit, $entitlements->usage($organization, $key), $entitlements->remaining($organization, $key) ?? 'Unlimited'])->values()->all());

        return self::SUCCESS;
    }
}
