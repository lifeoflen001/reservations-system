<?php

use App\Services\Tenancy\OrganizationOwnershipService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_memberships', function (Blueprint $table): void {
            $table->boolean('is_owner')->default(false)->index();
        });

        // Existing organizations receive one deterministic owner where they
        // have active memberships. The service records the selection in the
        // organization audit log and leaves existing owners untouched.
        app(OrganizationOwnershipService::class)->backfill();
    }

    public function down(): void
    {
        Schema::table('organization_memberships', function (Blueprint $table): void {
            $table->dropIndex(['is_owner']);
            $table->dropColumn('is_owner');
        });
    }
};
