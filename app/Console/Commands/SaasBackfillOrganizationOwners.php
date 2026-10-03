<?php

namespace App\Console\Commands;

use App\Services\Tenancy\OrganizationOwnershipService;
use Illuminate\Console\Command;

class SaasBackfillOrganizationOwners extends Command
{
    protected $signature = 'saas:backfill-organization-owners {--dry-run : Report deterministic owner selections without changing data}';

    protected $description = 'Assign explicit organization owners using deterministic RBAC authority';

    public function handle(OrganizationOwnershipService $ownership): int
    {
        $report = $ownership->backfill((bool) $this->option('dry-run'));
        $this->info($this->option('dry-run') ? 'Organization owner backfill dry run completed.' : 'Organization owner backfill completed.');
        $this->table(['Check', 'Count'], [
            ['Organizations checked', $report['organizations_checked']],
            ['Owners already present', $report['owners_existing']],
            ['Owners granted', $report['owners_granted']],
        ]);

        foreach ($report['selections'] as $selection) {
            $this->line(sprintf(
                'Organization %s: membership %s / user %s / role %s / score %s (%s)',
                $selection['organization_id'],
                $selection['membership_id'],
                $selection['user_id'],
                $selection['role'],
                $selection['authority_score'],
                $selection['selection'],
            ));
        }

        return self::SUCCESS;
    }
}
