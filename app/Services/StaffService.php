<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

class StaffService
{
    public function create(array $data, User $actor): User
    {
        $this->assertRoleAssignment($data['role_id'] ?? null, $actor);
        return DB::transaction(function () use ($data, $actor) {
            $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));
            $payload = collect($data)->except(['password', 'password_confirmation'])->all();
            $payload['name'] = $name; $payload['password'] = Hash::make($data['password']); $payload['created_by'] = $actor->id; $payload['updated_by'] = $actor->id;
            return User::create($payload);
        });
    }

    public function update(User $staff, array $data, User $actor): User
    {
        if ($staff->is($actor) && array_key_exists('is_active', $data) && ! $data['is_active']) throw new LogicException('You cannot deactivate your own account.');
        if ($this->isSuperAdministrator($staff) && (array_key_exists('is_active', $data) && ! $data['is_active'] || (array_key_exists('role_id', $data) && (int) $data['role_id'] !== (int) $staff->role_id))) $this->assertNotFinalSuperAdministrator($staff);
        if (array_key_exists('role_id', $data)) $this->assertRoleAssignment($data['role_id'], $actor);
        return DB::transaction(function () use ($staff, $data, $actor) {
            $staff = User::query()->lockForUpdate()->findOrFail($staff->id);
            $payload = collect($data)->except(['password', 'password_confirmation'])->all();
            $payload['name'] = trim(($data['first_name'] ?? $staff->first_name ?? '').' '.($data['last_name'] ?? $staff->last_name ?? ''));
            $payload['updated_by'] = $actor->id;
            $staff->update($payload);
            return $staff->refresh();
        });
    }

    public function disable(User $staff, User $actor): User
    {
        if ($staff->is($actor)) throw new LogicException('You cannot deactivate your own account.');
        if ($this->isSuperAdministrator($staff)) $this->assertNotFinalSuperAdministrator($staff);
        $staff->update(['is_active' => false, 'updated_by' => $actor->id]);
        return $staff->refresh();
    }

    public function resetPassword(User $staff, string $password, User $actor): User
    {
        $staff->update(['password' => Hash::make($password), 'must_change_password' => true, 'updated_by' => $actor->id]);
        return $staff->refresh();
    }

    public function assertRoleAssignment(?int $roleId, User $actor): void
    {
        if (! $roleId || $actor->role?->name === 'super_administrator') return;
        $role = Role::find($roleId);
        if ($actor->hasPermission('roles.manage') && $role?->name !== 'super_administrator') return;
        if ($role?->name === 'super_administrator' || $role?->is_system) throw new LogicException('You are not authorized to assign this system role.');
        throw new LogicException('You are not authorized to change staff roles.');
    }

    private function isSuperAdministrator(User $user): bool { return $user->role?->name === 'super_administrator'; }
    private function assertNotFinalSuperAdministrator(User $user): void
    {
        if (User::where('role_id', $user->role_id)->where('is_active', true)->count() <= 1) throw new LogicException('The final Super Administrator cannot be disabled or demoted.');
    }
}
