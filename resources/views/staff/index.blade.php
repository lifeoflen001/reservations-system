@extends('layouts.app')

@section('content')
<x-page-header title="Staff" subtitle="Accounts, roles, departments and access.">
    <x-operational-data-transfer resource="staff" />
    @can('create', \App\Models\User::class)
        <a class="ui-button ui-button--primary" href="{{ route('staff.index', ['new' => 1]) }}"><x-ui.icon name="plus" size="16" /> New staff member</a>
    @endcan
</x-page-header>
@if ($errors->any())<x-feedback.alert type="danger" class="page-feedback">{{ $errors->first() }}</x-feedback.alert>@endif

<nav class="ui-tabs page-tabs" aria-label="Staff sections">
    <a class="ui-tab {{ !request('tab') || request('tab') === 'staff' ? 'is-active' : '' }}" href="{{ route('staff.index', ['tab' => 'staff']) }}">Staff</a>
    <a class="ui-tab {{ request('tab') === 'roles' ? 'is-active' : '' }}" href="{{ route('staff.index', ['tab' => 'roles']) }}">Roles & permissions</a>
    <a class="ui-tab {{ request('tab') === 'departments' ? 'is-active' : '' }}" href="{{ route('staff.index', ['tab' => 'departments']) }}">Departments</a>
</nav>

@if(!request('tab') || request('tab') === 'staff')
    <x-kpi-grid :items="$kpis" />
    <form class="filter-toolbar staff-filters" method="GET" action="{{ route('staff.index') }}">
        <input type="hidden" name="tab" value="staff">
        <x-form.input name="search" placeholder="Search staff..." aria-label="Search staff" />
        <x-form.select name="role_id" aria-label="Filter by role"><option value="all">All roles</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected((string)request('role_id') === (string)$role->id)>{{ $role->label }}</option>@endforeach</x-form.select>
        <x-form.select name="department_id" aria-label="Filter by department"><option value="all">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string)request('department_id') === (string)$department->id)>{{ $department->name }}</option>@endforeach</x-form.select>
        <x-form.select name="status" aria-label="Filter by status"><option value="">All statuses</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></x-form.select>
        <x-form.select name="sort" aria-label="Sort staff"><option value="name" @selected(request('sort', 'name') === 'name')>Name</option><option value="role" @selected(request('sort') === 'role')>Role</option><option value="department" @selected(request('sort') === 'department')>Department</option><option value="last_login" @selected(request('sort') === 'last_login')>Last login</option></x-form.select>
        <button class="ui-button ui-button--info" type="submit"><x-ui.icon name="filter" size="16" /> Filter</button>
        <a class="ui-button ui-button--secondary" href="{{ route('staff.index') }}">Reset</a>
    </form>

    <section class="ui-card">
        <x-data.table caption="Staff accounts">
            <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Department</th><th>Phone</th><th>Language</th><th>Last login</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($staff as $person)
                <tr>
                    <td><div class="identity-cell"><span class="avatar avatar--small">{{ $person->initials ?: 'U' }}</span><span><strong>{{ $person->display_name }}</strong><small>{{ $person->email ?: 'No email' }}</small></span></div></td>
                    <td>{{ $person->username ?: '—' }}</td>
                    <td><x-ui.badge variant="info">{{ $person->role?->label ?? 'No role' }}</x-ui.badge></td>
                    <td>{{ $person->department?->name ?? '—' }}</td>
                    <td>{{ $person->phone ?: '—' }}</td>
                    <td>{{ $person->language?->code ?? $person->language?->name ?? '—' }}</td>
                    <td>{{ $person->last_login_at?->format('m/d/Y, h:i A') ?? '—' }}</td>
                    <td><x-ui.badge :variant="$person->is_active ? 'success' : 'danger'">{{ $person->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></td>
                    <td><div class="row-actions">
                        @can('view', $person)<a class="icon-button" href="{{ route('staff.index', ['staff' => $person->id]) }}" aria-label="View staff" data-tooltip="View"><x-ui.icon name="eye" size="17" /></a>@endcan
                        @can('update', $person)<a class="icon-button" href="{{ route('staff.index', ['edit' => $person->id]) }}" aria-label="Edit staff" data-tooltip="Edit"><x-ui.icon name="edit" size="17" /></a>@endcan
                        @can('resetPassword', $person)<a class="icon-button" href="{{ route('staff.index', ['reset' => $person->id]) }}" aria-label="Reset password" data-tooltip="Reset password"><x-ui.icon name="key" size="17" /></a>@endcan
                        @can('disable', $person) @if($person->is_active && !$person->is(auth()->user()))<form method="POST" action="{{ route('staff.disable', $person) }}" data-confirm="Deactivate this staff account?">@csrf<button class="icon-button icon-button--danger" type="submit" aria-label="Deactivate staff" data-tooltip="Deactivate"><x-ui.icon name="trash" size="17" /></button></form>@endif @endcan
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="empty-state"><x-ui.icon name="users" size="28" /><strong>No staff accounts found.</strong><span>Create an account to give an employee access to HotelDesk.</span></div></td></tr>
            @endforelse
            </tbody>
        </x-data.table>
        <x-data.pagination :paginator="$staff" />
    </section>
@elseif(request('tab') === 'roles')
    <section class="ui-card roles-permissions-card"><header class="table-card__header roles-permissions-card__header"><div class="roles-permissions-card__title"><span class="roles-permissions-card__icon"><x-ui.icon name="shield" size="18" /></span><div><h2>Roles & permissions</h2><p>Control access through reusable role templates.</p></div></div><span class="roles-permissions-card__meta">{{ $allRoles->count() }} {{ \Illuminate\Support\Str::plural('role', $allRoles->count()) }}</span></header>
        <x-data.table caption="Roles" class="roles-permissions-table">
            <thead><tr><th>Role</th><th>Description</th><th class="table-number">Users</th><th class="table-number">Permissions</th><th>Type</th><th class="table-actions">Actions</th></tr></thead>
            <tbody>@forelse($allRoles as $role)<tr><td><span class="table-primary-text">{{ $role->label }}</span><span class="table-secondary-text">{{ $role->name }}</span></td><td>{{ $role->description ?: '—' }}</td><td class="table-number">{{ $role->users_count }}</td><td class="table-number">{{ $role->permissions_count ?? $role->permissions->count() }}</td><td><x-ui.badge :variant="$role->is_system ? 'info' : 'neutral'">{{ $role->is_system ? 'System' : 'Custom' }}</x-ui.badge></td><td class="table-actions">@can('update', $role)<div class="row-actions"><a class="icon-button" href="{{ route('staff.index', ['tab' => 'roles', 'role' => $role->id]) }}" aria-label="Edit role" data-tooltip="Edit"><x-ui.icon name="edit" size="17" /></a></div>@endcan</td></tr>@empty<tr><td colspan="6">No roles found.</td></tr>@endforelse</tbody>
        </x-data.table>
    </section>
@else
    <section class="ui-card"><header class="table-card__header"><div><h2>Departments</h2><p>Organize staff and guide operational assignment.</p></div>@if(auth()->user()->role?->name === 'super_administrator' || auth()->user()->hasPermission('departments.manage'))<a class="ui-button ui-button--primary" href="{{ route('staff.index', ['tab' => 'departments', 'new_department' => 1]) }}"><x-ui.icon name="plus" size="16" /> New department</a>@endif</header>
        <x-data.table caption="Departments"><thead><tr><th>Name</th><th>Description</th><th class="table-number">Staff</th><th>Status</th><th class="table-actions">Actions</th></tr></thead><tbody>@forelse($allDepartments as $department)<tr><td><span class="table-primary-text">{{ $department->name }}</span></td><td>{{ $department->description ?: '—' }}</td><td class="table-number">{{ $department->users_count }}</td><td><x-ui.badge :variant="$department->is_active ? 'success' : 'danger'">{{ $department->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></td><td class="table-actions">@if(auth()->user()->role?->name === 'super_administrator' || auth()->user()->hasPermission('departments.manage'))<div class="row-actions"><a class="icon-button" href="{{ route('staff.index', ['tab' => 'departments', 'edit_department' => $department->id]) }}" aria-label="Edit department" data-tooltip="Edit"><x-ui.icon name="edit" size="17" /></a>@if(!$department->users_count)<form class="inline-form" method="POST" action="{{ route('staff.departments.destroy', $department) }}" data-confirm="Delete this department?">@csrf @method('DELETE')<button class="icon-button icon-button--danger" type="submit" aria-label="Delete department" data-tooltip="Delete"><x-ui.icon name="trash" size="17" /></button></form>@endif</div>@endif</td></tr>@empty<tr><td colspan="5">No departments found.</td></tr>@endforelse</tbody></x-data.table>
    </section>
@endif

@if($openNew || $editStaff)
    @php($person = $editStaff)
    <x-ui.modal id="staff-form" :title="$person ? 'Edit staff member' : 'New staff member'" size="large" open="true">
        <form method="POST" action="{{ $person ? route('staff.update', $person) : route('staff.store') }}">
            @csrf @if($person) @method('PUT') @endif
            <div class="reservation-form__grid">
                <x-form.input name="first_name" label="First name" :value="$person?->first_name" required />
                <x-form.input name="last_name" label="Last name" :value="$person?->last_name" required />
                <x-form.input name="email" type="email" label="Email" :value="$person?->email" />
                <x-form.input name="phone" label="Phone" :value="$person?->phone" />
                <x-form.input name="username" label="Username" :value="$person?->username" required />
                <x-form.select name="language_id" label="Language"><option value="">Select language</option>@foreach($languages as $language)<option value="{{ $language->id }}" @selected((string)old('language_id', $person?->language_id) === (string)$language->id)>{{ $language->name }} ({{ $language->code }})</option>@endforeach</x-form.select>
                <x-form.select name="department_id" label="Department"><option value="">No department</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string)old('department_id', $person?->department_id) === (string)$department->id)>{{ $department->name }}</option>@endforeach</x-form.select>
                <x-form.select name="role_id" label="Role"><option value="">No role</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected((string)old('role_id', $person?->role_id) === (string)$role->id)>{{ $role->label }}</option>@endforeach</x-form.select>
                <x-form.input name="password" type="password" label="Password" :required="!$person" help="Minimum 8 characters. Leave blank to keep the current password." />
                <x-form.input name="password_confirmation" type="password" label="Confirm password" :required="!$person" />
                <div class="form-field form-field--full checkbox-field"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $person?->is_active ?? true))> Active</label><label><input type="checkbox" name="must_change_password" value="1" @checked(old('must_change_password', $person?->must_change_password ?? true))> Require password change at next sign-in</label></div>
            </div>
            <div class="modal-form-footer"><a class="ui-button ui-button--secondary" href="{{ route('staff.index') }}">Cancel</a><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> Save</button></div>
        </form>
    </x-ui.modal>
@endif

@if($openStaff)
    <x-ui.modal id="staff-details" title="Staff details" size="large" open="true"><div class="detail-hero"><span class="avatar avatar--large">{{ $openStaff->initials ?: 'U' }}</span><div><h2>{{ $openStaff->display_name }}</h2><p>{{ $openStaff->email ?: 'No email' }} · {{ $openStaff->role?->label ?? 'No role' }}</p></div><x-ui.badge :variant="$openStaff->is_active ? 'success' : 'danger'">{{ $openStaff->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></div><div class="detail-grid"><div class="detail-section"><h3>Account</h3><dl><dt>Username</dt><dd>{{ $openStaff->username ?: '—' }}</dd><dt>Department</dt><dd>{{ $openStaff->department?->name ?: '—' }}</dd><dt>Language</dt><dd>{{ $openStaff->language?->name ?: '—' }}</dd><dt>Last login</dt><dd>{{ $openStaff->last_login_at?->format('m/d/Y, h:i A') ?: 'Never' }}</dd></dl></div><div class="detail-section"><h3>Operational activity</h3><dl><dt>Reservations created</dt><dd>{{ $openStaff->created_reservations_count ?? $openStaff->createdReservations()->count() }}</dd><dt>Housekeeping tasks</dt><dd>{{ $openStaff->housekeeping_tasks_count ?? $openStaff->housekeepingTasks()->count() }}</dd><dt>Maintenance tasks</dt><dd>{{ $openStaff->maintenance_tasks_count ?? $openStaff->maintenanceTasks()->count() }}</dd><dt>Must change password</dt><dd>{{ $openStaff->must_change_password ? 'Yes' : 'No' }}</dd></dl></div></div><div class="modal-form-footer"><a class="ui-button ui-button--secondary" href="{{ route('staff.index') }}">Close</a>@can('update', $openStaff)<a class="ui-button ui-button--primary" href="{{ route('staff.index', ['edit' => $openStaff->id]) }}"><x-ui.icon name="edit" size="16" /> Edit</a>@endcan</div></x-ui.modal>
@endif

@if($resetStaff)
    <x-ui.modal id="staff-reset" title="Reset staff password" open="true"><p>Set a temporary password for <strong>{{ $resetStaff->display_name }}</strong>. They will be required to change it at next sign-in.</p><form method="POST" action="{{ route('staff.reset-password', $resetStaff) }}">@csrf<x-form.input name="password" type="password" label="Temporary password" required /><x-form.input name="password_confirmation" type="password" label="Confirm temporary password" required /><div class="modal-form-footer"><a class="ui-button ui-button--secondary" href="{{ route('staff.index') }}">Cancel</a><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="key" size="16" /> Reset password</button></div></form></x-ui.modal>
@endif

@if($openRole)
    <x-ui.modal id="role-form" title="Edit role permissions" size="large" open="true"><form method="POST" action="{{ route('staff.roles.update', $openRole) }}">@csrf @method('PUT')<div class="reservation-form__grid"><x-form.input name="label" label="Role name" :value="$openRole->label" required /><x-form.input name="description" label="Description" :value="$openRole->description" class="form-field--full" /><div class="form-field form-field--full checkbox-field"><label><input type="checkbox" name="is_active" value="1" @checked($openRole->is_active)> Active</label></div></div><h3 class="subsection-title">Permissions</h3><div class="permission-grid">@foreach($permissions as $module => $modulePermissions)<fieldset class="permission-group"><legend>{{ \Illuminate\Support\Str::of($module)->replace('_', ' ')->title() }}</legend>@foreach($modulePermissions as $permission)<label class="permission-option"><input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked($openRole->permissions->contains('id', $permission->id))><span>{{ $permission->label }}</span></label>@endforeach</fieldset>@endforeach</div><div class="modal-form-footer"><a class="ui-button ui-button--secondary" href="{{ route('staff.index', ['tab' => 'roles']) }}">Cancel</a><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> Save role</button></div></form></x-ui.modal>
@endif

@if(request('new_department') || request('edit_department'))
    @php($department = request('edit_department') ? $allDepartments->firstWhere('id', request()->integer('edit_department')) : null)
    <x-ui.modal id="department-form" :title="$department ? 'Edit department' : 'New department'" open="true"><form method="POST" action="{{ $department ? route('staff.departments.update', $department) : route('staff.departments.store') }}">@csrf @if($department) @method('PUT') @endif<x-form.input name="name" label="Name" :value="$department?->name" required /><x-form.input name="sort_order" type="number" label="Display order" :value="$department?->sort_order" /><x-form.textarea name="description" label="Description" rows="3" :value="$department?->description" /><div class="form-field checkbox-field"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $department?->is_active ?? true))> Active</label></div><div class="modal-form-footer"><a class="ui-button ui-button--secondary" href="{{ route('staff.index', ['tab' => 'departments']) }}">Cancel</a><button class="ui-button ui-button--primary" type="submit"><x-ui.icon name="save" size="16" /> Save department</button></div></form></x-ui.modal>
@endif
@endsection
