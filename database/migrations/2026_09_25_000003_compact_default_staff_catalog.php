<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('departments')
            ->where('name', 'Administration')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('users')->whereColumn('users.department_id', 'departments.id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('tasks')->whereColumn('tasks.department_id', 'departments.id'))
            ->update(['is_active' => false, 'updated_at' => now()]);

        DB::table('roles')
            ->whereIn('name', ['maintenance', 'cashier'])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('users')->whereColumn('users.role_id', 'roles.id'))
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Do not reactivate legacy defaults after an administrator has reviewed
        // and compacted the staff catalog.
    }
};
