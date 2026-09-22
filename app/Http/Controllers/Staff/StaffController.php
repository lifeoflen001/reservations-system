<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateRoleRequest;
use App\Models\Department;
use App\Models\Language;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\StaffService;
use App\Services\MiniDashboardMetricsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Services\SystemSettingsService;
use App\Services\HotelEmailService;
use App\Services\IntegrationSettingsService;
use App\Services\PropertySettingsService;
use Illuminate\View\View;
use LogicException;

class StaffController extends Controller
{
    public function __construct(private readonly StaffService $staffService, private readonly HotelEmailService $emails, private readonly IntegrationSettingsService $integrations) {}

    public function index(Request $request, MiniDashboardMetricsService $metricsService): View
    {
        Gate::authorize('viewAny', User::class);

        $sorts = [
            'name' => ['name', 'asc'], 'role' => ['role_id', 'asc'],
            'department' => ['department_id', 'asc'], 'last_login' => ['last_login_at', 'desc'],
            'created' => ['created_at', 'desc'],
        ];
        [$sortColumn, $sortDirection] = $sorts[(string) $request->input('sort', 'name')] ?? $sorts['name'];

        $staff = User::query()->with(['role', 'department', 'language'])
            ->withCount(['createdReservations', 'housekeepingTasks', 'maintenanceTasks'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.trim((string) $request->input('search')).'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('name', 'like', $term)->orWhere('first_name', 'like', $term)->orWhere('last_name', 'like', $term)
                        ->orWhere('username', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term);
                });
            })
            ->when($request->filled('role_id') && $request->input('role_id') !== 'all', fn ($q) => $q->where('role_id', $request->integer('role_id')))
            ->when($request->filled('department_id') && $request->input('department_id') !== 'all', fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when(in_array($request->input('status'), ['active', 'inactive'], true), fn ($q) => $q->where('is_active', $request->input('status') === 'active'))
            ->orderBy($sortColumn, $sortDirection)->orderBy('id')
            ->paginate(15)->withQueryString();

        $openStaff = $request->filled('staff')
            ? User::with(['role.permissions', 'department', 'language'])->find($request->integer('staff'))
            : null;
        $editStaff = $request->filled('edit') ? User::find($request->integer('edit')) : null;
        $resetStaff = $request->filled('reset') ? User::find($request->integer('reset')) : null;
        $openRole = $request->filled('role') ? Role::with('permissions')->find($request->integer('role')) : null;

        $permissions = Permission::query()->orderBy('name')->get()->groupBy(fn ($permission) => Str::before($permission->name, '.'));

        return view('staff.index', [
            'staff' => $staff,
            'roles' => Role::where('is_active', true)->orderBy('label')->get(),
            'allRoles' => Role::withCount('users')->withCount('permissions')->with('permissions')->orderBy('is_system', 'desc')->orderBy('label')->get(),
            'departments' => Department::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'allDepartments' => Department::withCount('users')->orderBy('sort_order')->orderBy('name')->get(),
            'languages' => Language::where('is_active', true)->orderBy('name')->get(),
            'permissions' => $permissions,
            'openNew' => $request->boolean('new'), 'openStaff' => $openStaff,
            'editStaff' => $editStaff, 'resetStaff' => $resetStaff, 'openRole' => $openRole,
            'kpis' => $metricsService->staff(),
        ]);
    }

    public function create(): RedirectResponse { Gate::authorize('create', User::class); return redirect()->route('staff.index', ['new' => 1]); }
    public function show(User $staff): RedirectResponse { Gate::authorize('view', $staff); return redirect()->route('staff.index', ['staff' => $staff->id]); }
    public function edit(User $staff): RedirectResponse { Gate::authorize('update', $staff); return redirect()->route('staff.index', ['edit' => $staff->id]); }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);
        try { $staff = $this->staffService->create($request->validated(), $request->user()); }
        catch (LogicException $exception) { return back()->withInput()->with('error', $exception->getMessage()); }
        $invitationQueued = false;
        if (filter_var($staff->email, FILTER_VALIDATE_EMAIL) && $this->integrations->isConfigured('email')) {
            $invitationQueued = $this->emails->queue('staff_invitation', $staff->email, [
                'user_name' => $staff->display_name,
                'username' => $staff->username,
                'property_name' => app(PropertySettingsService::class)->name(),
                'login_url' => route('login'),
                'invitation_title' => 'Staff invitation',
            ]) !== null;
        }

        $message = 'Staff account created.';
        if ($invitationQueued) {
            $message .= ' An invitation email has been queued for '.$staff->email.'.';
        } elseif (filter_var($staff->email, FILTER_VALIDATE_EMAIL)) {
            $message .= ' The invitation email was not sent because email delivery is not configured.';
        }

        return redirect()->route('staff.index', ['staff' => $staff->id])->with('success', $message);
    }

    public function update(StoreStaffRequest $request, User $staff): RedirectResponse
    {
        Gate::authorize('update', $staff);
        try { $staff = $this->staffService->update($staff, $request->validated(), $request->user()); }
        catch (LogicException $exception) { return back()->withInput()->with('error', $exception->getMessage()); }
        return redirect()->route('staff.index', ['staff' => $staff->id])->with('success', 'Staff account updated.');
    }

    public function destroy(Request $request, User $staff): RedirectResponse
    {
        Gate::authorize('disable', $staff);
        try { $this->staffService->disable($staff, $request->user()); }
        catch (LogicException $exception) { return back()->with('error', $exception->getMessage()); }
        return redirect()->route('staff.index')->with('success', 'Staff account deactivated.');
    }

    public function resetPassword(Request $request, User $staff): RedirectResponse
    {
        Gate::authorize('resetPassword', $staff);
        $data = $request->validate(['password' => ['required', 'confirmed', app(SystemSettingsService::class)->passwordRule()]]);
        $this->staffService->resetPassword($staff, $data['password'], $request->user());
        return redirect()->route('staff.index', ['staff' => $staff->id])->with('success', 'Password reset. The staff member must change it at next sign-in.');
    }

    public function roleUpdate(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        Gate::authorize('update', $role);
        abort_unless($request->user()->roleName() === 'super_administrator' || $request->user()->hasPermission('roles.manage'), 403);
        if ($role->name === 'super_administrator' && $request->user()->roleName() !== 'super_administrator') {
            return back()->with('error', 'Only a Super Administrator can edit the Super Administrator role.');
        }
        if ($role->name === 'super_administrator' && ($request->validated('is_active') ?? false) === false) {
            return back()->with('error', 'The Super Administrator role must remain active.');
        }
        $data = $request->validated();
        $role->update(['label' => $data['label'], 'description' => $data['description'] ?? null, 'is_active' => $data['is_active'] ?? false]);
        $role->permissions()->sync($data['permission_ids'] ?? []);
        return redirect()->route('staff.index', ['tab' => 'roles'])->with('success', 'Role permissions updated.');
    }

    public function departmentStore(Request $request): RedirectResponse
    {
        $this->authorizeDepartments($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:departments,name'], 'description' => ['nullable', 'string', 'max:1000'], 'sort_order' => ['nullable', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean']]);
        Department::create($data + ['is_active' => $data['is_active'] ?? true]);
        return redirect()->route('staff.index', ['tab' => 'departments'])->with('success', 'Department created.');
    }

    public function departmentUpdate(Request $request, Department $department): RedirectResponse
    {
        $this->authorizeDepartments($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($department)], 'description' => ['nullable', 'string', 'max:1000'], 'sort_order' => ['nullable', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean']]);
        $department->update($data);
        return back()->with('success', 'Department updated.');
    }

    public function departmentDestroy(Request $request, Department $department): RedirectResponse
    {
        $this->authorizeDepartments($request);
        if ($department->users()->exists()) return back()->with('error', 'Departments assigned to staff cannot be deleted. Deactivate it instead.');
        $department->delete();
        return back()->with('success', 'Department deleted.');
    }

    private function authorizeDepartments(Request $request): void
    {
        abort_unless($request->user()->roleName() === 'super_administrator' || $request->user()->hasPermission('departments.manage'), 403);
    }
}
