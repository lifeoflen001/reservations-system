<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['departments', 'floors', 'room_categories', 'room_types', 'amenities', 'rooms', 'reservations', 'room_blocks', 'payments', 'invoices', 'housekeeping_tasks', 'maintenance_tasks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('property_id')->nullable()->after('id')->constrained('properties')->nullOnDelete();
            });
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
            $table->index(['organization_id', 'email']);
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropUnique('departments_name_unique');
            $table->unique(['property_id', 'name'], 'departments_property_name_unique');
            $table->index(['property_id', 'is_active']);
        });

        Schema::table('floors', function (Blueprint $table): void {
            $table->unique(['property_id', 'name'], 'floors_property_name_unique');
            $table->index(['property_id', 'sort_order']);
        });

        Schema::table('room_categories', function (Blueprint $table): void {
            $table->dropUnique('room_categories_name_unique');
            $table->unique(['property_id', 'name'], 'room_categories_property_name_unique');
            $table->index(['property_id', 'is_active']);
        });

        Schema::table('room_types', function (Blueprint $table): void {
            $table->dropUnique('room_types_name_unique');
            $table->unique(['property_id', 'name'], 'room_types_property_name_unique');
            $table->index(['property_id', 'is_active']);
        });

        Schema::table('amenities', function (Blueprint $table): void {
            $table->dropUnique('amenities_name_unique');
            $table->unique(['property_id', 'name'], 'amenities_property_name_unique');
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropUnique('rooms_room_number_unique');
            $table->unique(['property_id', 'room_number'], 'rooms_property_room_number_unique');
            $table->index(['property_id', 'operational_status']);
            $table->index(['property_id', 'room_type_id']);
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->index(['property_id', 'status', 'check_in']);
            $table->index(['property_id', 'room_id', 'check_in', 'check_out']);
        });

        Schema::table('room_blocks', function (Blueprint $table): void {
            $table->index(['property_id', 'room_id', 'starts_at', 'ends_at']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_invoice_number_unique');
            $table->unique(['property_id', 'invoice_number'], 'payments_property_invoice_number_unique');
            $table->index(['property_id', 'transaction_date', 'status']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_invoice_number_unique');
            $table->unique(['property_id', 'invoice_number'], 'invoices_property_invoice_number_unique');
            $table->index(['property_id', 'issue_date', 'status']);
        });

        foreach (['housekeeping_tasks', 'maintenance_tasks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->index(['property_id', 'status', 'due_at']);
                $table->index(['property_id', 'room_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['housekeeping_tasks', 'maintenance_tasks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex(['property_id', 'status', 'due_at']);
                $table->dropIndex(['property_id', 'room_id']);
            });
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_property_invoice_number_unique');
            $table->unique('invoice_number');
            $table->dropIndex(['property_id', 'issue_date', 'status']);
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_property_invoice_number_unique');
            $table->unique('invoice_number');
            $table->dropIndex(['property_id', 'transaction_date', 'status']);
        });
        Schema::table('room_blocks', fn (Blueprint $table) => $table->dropIndex(['property_id', 'room_id', 'starts_at', 'ends_at']));
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropIndex(['property_id', 'status', 'check_in']);
            $table->dropIndex(['property_id', 'room_id', 'check_in', 'check_out']);
        });
        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropUnique('rooms_property_room_number_unique');
            $table->unique('room_number');
            $table->dropIndex(['property_id', 'operational_status']);
            $table->dropIndex(['property_id', 'room_type_id']);
        });
        Schema::table('amenities', function (Blueprint $table): void {
            $table->dropUnique('amenities_property_name_unique');
            $table->unique('name');
        });
        Schema::table('room_types', function (Blueprint $table): void {
            $table->dropUnique('room_types_property_name_unique');
            $table->unique('name');
            $table->dropIndex(['property_id', 'is_active']);
        });
        Schema::table('room_categories', function (Blueprint $table): void {
            $table->dropUnique('room_categories_property_name_unique');
            $table->unique('name');
            $table->dropIndex(['property_id', 'is_active']);
        });
        Schema::table('floors', function (Blueprint $table): void {
            $table->dropUnique('floors_property_name_unique');
            $table->dropIndex(['property_id', 'sort_order']);
        });
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropUnique('departments_property_name_unique');
            $table->unique('name');
            $table->dropIndex(['property_id', 'is_active']);
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'email']);
        });

        foreach (['departments', 'floors', 'room_categories', 'room_types', 'amenities', 'rooms', 'reservations', 'room_blocks', 'payments', 'invoices', 'housekeeping_tasks', 'maintenance_tasks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['property_id']);
                $table->dropColumn('property_id');
            });
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
