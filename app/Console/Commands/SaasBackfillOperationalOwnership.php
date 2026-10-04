<?php

namespace App\Console\Commands;

use App\Services\OperationalTenantBackfillService;
use Illuminate\Console\Command;

class SaasBackfillOperationalOwnership extends Command
{
    protected $signature = 'saas:backfill-operational-ownership {--dry-run : Inspect ownership without writing changes}';

    protected $description = 'Backfill and reconcile organization/property ownership for operational records';

    public function handle(OperationalTenantBackfillService $backfill): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $report = $backfill->run($dryRun);

        $this->line($dryRun ? 'Operational ownership dry-run' : 'Operational ownership backfill');
        $this->line('Organization: '.($report['organization_id'] ?: 'not resolved'));
        $this->line('Default property: '.($report['default_property_id'] ?: 'not resolved'));

        foreach ($report['tables'] as $table => $stats) {
            $this->line(sprintf(
                '%s: rows=%d backfilled=%d existing=%d null=%d orphan=%d',
                $table,
                $stats['before_count'],
                $stats['backfilled'],
                $stats['existing_owned'],
                $stats['null_ownership'],
                $stats['orphan_ownership'],
            ));
        }

        if ($report['anomalies'] !== []) {
            $this->error('Ownership anomalies found; affected rows were not silently repaired.');
            foreach (array_slice($report['anomalies'], 0, 25) as $anomaly) {
                $this->line(json_encode($anomaly, JSON_UNESCAPED_SLASHES));
            }

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry-run completed with no anomalies.' : 'Operational ownership backfill completed with no anomalies.');

        return self::SUCCESS;
    }
}
