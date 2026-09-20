<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        Permission::query()->where('name', 'like', 'plugins.%')->delete();
        $permissions = collect(config('hotel.permissions', []))->mapWithKeys(fn (string $name) => [
            $name => Permission::updateOrCreate(['name' => $name], ['label' => str($name)->replace('.', ' ')->headline()]),
        ]);

        $administrator = Role::updateOrCreate(['name' => 'administrator'], ['label' => 'Administrator', 'description' => 'Full operational administration.', 'is_system' => true, 'is_active' => true]);
        $permissionIds = $permissions->values()->map(fn (Permission $permission) => $permission->getKey())->all();
        $administrator->permissions()->sync($permissionIds);

        Role::updateOrCreate(['name' => 'super_administrator'], ['label' => 'Super Administrator', 'description' => 'Highest system access.', 'is_system' => true, 'is_active' => true])
            ->permissions()->sync($permissionIds);

        $templates = [
            'manager' => ['label' => 'Manager', 'permissions' => $permissions->keys()->reject(fn ($name) => str_starts_with($name, 'roles.') || $name === 'settings.manage')->all()],
            'front_desk' => ['label' => 'Front Desk / Reception', 'permissions' => ['dashboard.view', 'clients.view', 'clients.create', 'clients.update', 'reservations.view', 'reservations.create', 'reservations.update', 'reservations.checkin', 'reservations.checkout', 'room_planning.view', 'rooms.view', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.comment', 'payments.view', 'payments.create', 'invoices.view', 'invoices.print']],
            'housekeeper' => ['label' => 'Housekeeper', 'permissions' => ['dashboard.view', 'rooms.view', 'housekeeping.view', 'housekeeping.complete', 'tasks.view', 'tasks.comment', 'tasks.complete', 'tasks.track_time']],
            'maintenance' => ['label' => 'Maintenance', 'permissions' => ['dashboard.view', 'rooms.view', 'maintenance.view', 'maintenance.create', 'maintenance.update', 'maintenance.complete', 'tasks.view', 'tasks.comment', 'tasks.complete', 'tasks.track_time']],
            'finance' => ['label' => 'Finance / Accounts', 'permissions' => ['dashboard.view', 'clients.view', 'reservations.view', 'payments.view', 'payments.create', 'payments.update', 'payments.void', 'payments.print', 'payments.export', 'invoices.view', 'invoices.print', 'invoices.download', 'reports.view', 'reports.export']],
        ];
        foreach ($templates as $name => $template) {
            $role = Role::updateOrCreate(['name' => $name], ['label' => $template['label'], 'description' => 'Default HotelDesk role template.', 'is_system' => true, 'is_active' => true]);
            $role->permissions()->sync($permissions->only($template['permissions'])->pluck('id'));
        }
    }
}
