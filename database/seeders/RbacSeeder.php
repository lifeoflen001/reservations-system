<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FinanceReferenceSeeder::class);
        $permissions = collect(config('hotel.permissions', []))->mapWithKeys(fn (string $name) => [
            $name => Permission::firstOrCreate(['name' => $name], ['label' => str($name)->replace('.', ' ')->headline()]),
        ]);

        $administrator = Role::firstOrCreate(['name' => 'administrator'], ['label' => 'Administrator', 'description' => 'Full operational administration.', 'is_system' => true, 'is_active' => true]);
        $permissionIds = $permissions->values()->map(fn (Permission $permission) => $permission->getKey())->all();
        $administrator->permissions()->syncWithoutDetaching($permissionIds);

        Role::firstOrCreate(['name' => 'super_administrator'], ['label' => 'Super Administrator', 'description' => 'Highest system access.', 'is_system' => true, 'is_active' => true])
            ->permissions()->syncWithoutDetaching($permissionIds);

        $templates = [
            'manager' => ['label' => 'Manager', 'permissions' => $permissions->keys()->reject(fn ($name) => str_starts_with($name, 'roles.') || $name === 'settings.manage')->all()],
            'front_desk' => ['label' => 'Front Office', 'permissions' => ['dashboard.view', 'clients.view', 'clients.create', 'clients.update', 'reservations.view', 'reservations.create', 'reservations.update', 'reservations.checkin', 'reservations.checkout', 'room_planning.view', 'rooms.view', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.comment', 'payments.view', 'payments.create', 'invoices.view', 'invoices.print', 'pos.access', 'pos.sell', 'pos.view_orders', 'pos.charge_room', 'pos.products.view', 'pos.shifts.open', 'pos.shifts.close', 'pos.receipts.view']],
            'housekeeper' => ['label' => 'Operations', 'permissions' => ['dashboard.view', 'rooms.view', 'housekeeping.view', 'housekeeping.complete', 'maintenance.view', 'maintenance.create', 'maintenance.update', 'maintenance.complete', 'tasks.view', 'tasks.comment', 'tasks.complete', 'tasks.track_time']],
            'accountant' => ['label' => 'Accountant', 'permissions' => ['dashboard.view', 'clients.view', 'reservations.view', 'payments.view', 'payments.create', 'payments.update', 'payments.void', 'payments.refund', 'payments.print', 'payments.export', 'invoices.view', 'invoices.print', 'invoices.download', 'reports.view', 'reports.export', 'finance.view', 'finance.accounts.view', 'finance.payments.view', 'finance.payments.create', 'finance.expenses.view', 'finance.expenses.create', 'finance.expenses.submit', 'finance.expenses.pay', 'finance.transfers.create', 'finance.petty_cash.manage', 'finance.reports.view', 'finance.reports.export']],
            'finance_manager' => ['label' => 'Finance Manager', 'permissions' => ['dashboard.view', 'clients.view', 'reservations.view', 'payments.view', 'payments.create', 'payments.update', 'payments.void', 'payments.refund', 'payments.print', 'payments.export', 'invoices.view', 'invoices.print', 'invoices.download', 'reports.view', 'reports.export', 'finance.view', 'finance.accounts.view', 'finance.accounts.manage', 'finance.payments.view', 'finance.payments.create', 'finance.expenses.view', 'finance.expenses.create', 'finance.expenses.submit', 'finance.expenses.approve', 'finance.expenses.reject', 'finance.expenses.pay', 'finance.expenses.reverse', 'finance.transfers.create', 'finance.transfers.approve', 'finance.petty_cash.manage', 'finance.bank.manage', 'finance.reconcile', 'finance.reports.view', 'finance.reports.export']],
            'finance' => ['label' => 'Finance / Accounts', 'permissions' => ['dashboard.view', 'clients.view', 'reservations.view', 'payments.view', 'payments.create', 'payments.update', 'payments.void', 'payments.refund', 'payments.print', 'payments.export', 'invoices.view', 'invoices.print', 'invoices.download', 'reports.view', 'reports.export', 'finance.view', 'finance.accounts.view', 'finance.accounts.manage', 'finance.payments.view', 'finance.payments.create', 'finance.expenses.view', 'finance.expenses.create', 'finance.expenses.submit', 'finance.expenses.approve', 'finance.expenses.reject', 'finance.expenses.pay', 'finance.expenses.reverse', 'finance.transfers.create', 'finance.transfers.approve', 'finance.petty_cash.manage', 'finance.reconcile', 'finance.reports.view', 'finance.reports.export']],
        ];
        foreach ($templates as $name => $template) {
            $role = Role::firstOrCreate(['name' => $name], ['label' => $template['label'], 'description' => 'Default HotelDesk role template.', 'is_system' => true, 'is_active' => true]);
            $role->permissions()->syncWithoutDetaching($permissions->only($template['permissions'])->pluck('id'));
        }

        // Rename only untouched application defaults. Custom administrator
        // labels remain unchanged.
        Role::query()->where('name', 'front_desk')->where('is_system', true)->whereIn('label', ['Front Desk / Reception', 'Front Office'])->update(['label' => 'Front Office']);
        Role::query()->where('name', 'housekeeper')->where('is_system', true)->whereIn('label', ['Housekeeper', 'Operations'])->update(['label' => 'Operations']);

        // Do not delete or reassign users on populated installations. Unused
        // application-owned legacy roles are hidden from new assignments.
        Role::query()->where('is_system', true)->whereIn('name', ['maintenance', 'cashier'])->whereDoesntHave('users')->update(['is_active' => false]);

        $this->retireApplicationPermissions();
    }

    private function retireApplicationPermissions(): void
    {
        $retiredNames = collect(config('hotel.retired_permissions', []))
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->values();
        if ($retiredNames->isEmpty()) return;

        $retired = Permission::query()->whereIn('name', $retiredNames)->get();
        if ($retired->isEmpty()) return;

        // Custom roles may intentionally retain a legacy permission until an
        // administrator removes it explicitly.
        Role::query()->where('is_system', true)->get()->each(
            fn (Role $role) => $role->permissions()->detach($retired->modelKeys())
        );

        Permission::query()->whereIn('id', $retired->modelKeys())->doesntHave('roles')->delete();
    }
}
