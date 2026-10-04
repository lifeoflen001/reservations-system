<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'organization.view', 'organization.update', 'members.view', 'members.manage', 'audit.view',
        ];

        foreach ($permissions as $name) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['label' => str($name)->replace('.', ' ')->headline(), 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('name', $permissions)->pluck('id');
        $roleIds = DB::table('roles')->whereIn('name', ['administrator', 'super_administrator'])->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ], []);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', [
            'organization.view', 'organization.update', 'members.view', 'members.manage', 'audit.view',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
