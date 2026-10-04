<?php

namespace App\Console\Commands;

use App\Services\SaasDefaultTenantBackfillService;
use Illuminate\Console\Command;

class SaasBackfillDefaultTenant extends Command
{
    protected $signature = 'saas:backfill-default-tenant {--dry-run : Report the intended work without changing data} {--validate : Report remaining foundation validation issues}';

    protected $description = 'Safely create or reuse the default organization and backfill existing users and properties';

    public function handle(SaasDefaultTenantBackfillService $backfill): int
    {
        if ($this->option('validate')) {
            $issues = $backfill->validate();
            $this->table(['Check', 'Count'], collect($issues)->map(fn (int $count, string $check): array => [$check, $count])->all());

            return collect($issues)->sum() === 0 ? self::SUCCESS : self::FAILURE;
        }

        $report = $backfill->run((bool) $this->option('dry-run'));
        $this->info($this->option('dry-run') ? 'Default tenant backfill dry run completed.' : 'Default tenant backfill completed.');
        foreach ($report as $key => $value) {
            $this->line($key.': '.(is_bool($value) ? ($value ? 'yes' : 'no') : $value));
        }

        return self::SUCCESS;
    }
}
