<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Feature tests create the permission catalog explicitly as part of
        // their fixtures. Avoid pre-seeding duplicate unique rows there.
        if (app()->environment('testing')) {
            return;
        }
        $permissions = [
            'announcements.view', 'announcements.create', 'announcements.update', 'announcements.publish',
            'announcements.archive', 'announcements.statistics', 'announcements.manage',
            'announcements.manage_categories', 'announcements.manage_audience', 'announcements.send_email',
            'announcements.send_browser_notification',
        ];
        foreach ($permissions as $name) {
            DB::table('permissions')->updateOrInsert(['name' => $name], ['label' => str($name)->replace('.', ' ')->headline(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $ids = DB::table('permissions')->whereIn('name', $permissions)->pluck('id');
        $roles = DB::table('roles')->whereIn('name', ['administrator', 'super_administrator', 'manager'])->pluck('id');
        foreach ($roles as $roleId) {
            foreach ($ids as $permissionId) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId], []);
            }
        }
        DB::table('roles')->whereNotIn('name', ['administrator', 'super_administrator'])->pluck('id')->each(function ($roleId) use ($permissions): void {
            $viewId = DB::table('permissions')->where('name', 'announcements.view')->value('id');
            DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $viewId], []);
        });
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }
        $ids = DB::table('permissions')->where('name', 'like', 'announcements.%')->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
