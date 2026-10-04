<?php

use App\Services\SaasDefaultTenantBackfillService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(SaasDefaultTenantBackfillService::class)->run();
    }

    public function down(): void
    {
        // The backfill deliberately has no destructive rollback. Existing
        // users and property data must remain safe during migration review.
    }
};
