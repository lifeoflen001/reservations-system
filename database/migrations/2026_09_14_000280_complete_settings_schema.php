<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->string('timezone', 64)->nullable()->after('country');
            $table->foreignId('updated_by')->nullable()->after('setup_completed_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('system_settings', function (Blueprint $table): void {
            $table->foreignId('updated_by')->nullable()->after('type')->constrained('users')->nullOnDelete();
        });

        Schema::table('reservation_sources', function (Blueprint $table): void {
            $table->string('code', 80)->nullable()->after('name');
            $table->text('description')->nullable()->after('code');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            $table->foreignId('created_by')->nullable()->after('sort_order')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->unique('code');
        });

        foreach (DB::table('reservation_sources')->select('id', 'name')->get() as $source) {
            DB::table('reservation_sources')->where('id', $source->id)->update([
                'code' => strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $source->name), '-')),
                'sort_order' => $source->id,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('reservation_sources', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['code', 'description', 'sort_order']);
        });
        Schema::table('system_settings', fn (Blueprint $table) => $table->dropConstrainedForeignId('updated_by'));
        Schema::table('properties', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn('timezone');
        });
    }
};
