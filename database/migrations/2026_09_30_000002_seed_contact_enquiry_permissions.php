<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $permissionIds = [];
        foreach (['contact_enquiries.view', 'contact_enquiries.manage'] as $name) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['label' => str($name)->replace('.', ' ')->headline(), 'created_at' => now(), 'updated_at' => now()]
            );
            $permissionIds[] = DB::table('permissions')->where('name', $name)->value('id');
        }

        $roleIds = DB::table('roles')->whereIn('name', ['administrator', 'super_administrator'])->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId], []);
            }
        }
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $permissionIds = DB::table('permissions')->whereIn('name', ['contact_enquiries.view', 'contact_enquiries.manage'])->pluck('id');
        $adminRoleIds = DB::table('roles')->whereIn('name', ['administrator', 'super_administrator'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->whereIn('role_id', $adminRoleIds)->delete();
        $unassignedIds = DB::table('permissions')->whereIn('id', $permissionIds)->whereNotIn('id', function ($query): void {
            $query->select('permission_id')->from('permission_role');
        })->pluck('id');
        DB::table('permissions')->whereIn('id', $unassignedIds)->delete();
    }
};
