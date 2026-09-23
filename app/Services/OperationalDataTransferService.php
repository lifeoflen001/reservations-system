<?php

namespace App\Services;

use App\Enums\HousekeepingStatus;
use App\Enums\RoomOperationalStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Department;
use App\Models\Floor;
use App\Models\HousekeepingTask;
use App\Models\Language;
use App\Models\MaintenanceTask;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomType;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalDataTransferService
{
    public function __construct(
        private readonly RoomService $rooms,
        private readonly ClientService $clients,
        private readonly StaffService $staff,
        private readonly TaskService $tasks,
        private readonly MaintenanceTaskService $maintenance,
        private readonly HousekeepingService $housekeeping,
    ) {}

    public function resources(): array
    {
        return [
            'rooms' => ['label' => 'Rooms', 'model' => Room::class, 'view' => 'rooms.view', 'import' => ['rooms.create', 'rooms.manage'], 'headers' => ['room_number', 'floor', 'category', 'room_type', 'operational_status', 'housekeeping_status', 'base_rate', 'capacity', 'notes', 'is_active']],
            'clients' => ['label' => 'Clients', 'model' => Client::class, 'view' => 'clients.view', 'import' => ['clients.create', 'clients.manage'], 'headers' => ['first_name', 'middle_name', 'last_name', 'email', 'phone', 'alternate_phone', 'country', 'city', 'postal_code', 'nationality', 'date_of_birth', 'gender', 'document_type', 'document_number', 'document_expiry', 'address', 'notes', 'is_active']],
            'staff' => ['label' => 'Staff', 'model' => User::class, 'view' => 'staff.view', 'import' => ['staff.create', 'staff.manage'], 'headers' => ['username', 'first_name', 'last_name', 'email', 'phone', 'department', 'role', 'language', 'is_active']],
            'tasks' => ['label' => 'Tasks', 'model' => Task::class, 'view' => 'tasks.view', 'import' => ['tasks.create', 'tasks.manage'], 'headers' => ['task_number', 'title', 'description', 'category', 'status', 'priority', 'department', 'room', 'reservation_code', 'client_email', 'assignees', 'due_at', 'estimated_minutes', 'actual_minutes', 'archived']],
            'maintenance' => ['label' => 'Maintenance', 'model' => MaintenanceTask::class, 'view' => 'maintenance.view', 'import' => ['maintenance.create', 'maintenance.manage'], 'headers' => ['id', 'room', 'assignee', 'issue', 'description', 'priority', 'starts_at', 'ends_at', 'due_at', 'cost', 'status', 'notes', 'completed_at']],
            'housekeeping' => ['label' => 'Housekeeping', 'model' => HousekeepingTask::class, 'view' => 'housekeeping.view', 'import' => ['housekeeping.create', 'housekeeping.manage'], 'headers' => ['id', 'room', 'assignee', 'task_type', 'priority', 'due_at', 'status', 'notes', 'completed_at']],
        ];
    }

    public function definition(string $resource): array
    {
        return $this->resources()[$resource] ?? throw ValidationException::withMessages(['resource' => 'That data type is not available for transfer.']);
    }

    public function canExport(User $user, string $resource): bool
    {
        return $user->hasPermission($this->definition($resource)['view']);
    }

    public function canImport(User $user, string $resource): bool
    {
        return collect($this->definition($resource)['import'])->contains(fn (string $permission) => $user->hasPermission($permission));
    }

    public function export(string $resource, User $actor): array
    {
        $definition = $this->definition($resource);
        $sensitive = $resource === 'clients' && $actor->hasPermission('clients.view_sensitive');
        $headers = collect($definition['headers'])->when($resource === 'clients' && ! $sensitive, fn ($headers) => $headers->reject(fn (string $header) => in_array($header, ['document_type', 'document_number', 'document_expiry'], true)))->values()->all();

        $rows = match ($resource) {
            'rooms' => $this->roomRows($headers),
            'clients' => $this->clientRows($headers, $sensitive),
            'staff' => $this->staffRows($headers),
            'tasks' => $this->taskRows($headers, $actor),
            'maintenance' => $this->maintenanceRows($headers),
            'housekeeping' => $this->housekeepingRows($headers),
        };

        return ['title' => $definition['label'], 'headers' => $headers, 'rows' => $rows, 'filename' => Str::slug($definition['label']).'-'.now()->format('Y-m-d').'.'];
    }

    public function csv(string $resource, User $actor, bool $template = false): StreamedResponse
    {
        $data = $this->export($resource, $actor);
        $headers = $data['headers'];
        $rows = $template ? [] : $data['rows'];
        $filename = $template ? Str::slug($data['title']).'-template.csv' : $data['filename'].'csv';

        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            foreach ($rows as $row) fputcsv($handle, array_map(fn (string $header) => $row[$header] ?? '', $headers));
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(string $resource, UploadedFile $file, User $actor): int
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'csv') throw ValidationException::withMessages(['file' => 'Please upload a CSV file.']);
        if (($file->getSize() ?: 0) > 10 * 1024 * 1024) throw ValidationException::withMessages(['file' => 'CSV files must be 10 MB or smaller.']);

        $handle = fopen($file->getRealPath(), 'rb');
        $header = fgetcsv($handle);
        if (! is_array($header) || count($header) === 0) throw ValidationException::withMessages(['file' => 'The CSV file must contain a header row.']);
        $header = array_map(fn ($value) => $this->normalizeHeader((string) $value), $header);
        $definition = $this->definition($resource);
        if ($resource === 'clients' && ! $actor->hasPermission('clients.view_sensitive') && array_intersect(['document_type', 'document_number', 'document_expiry'], $header)) {
            throw ValidationException::withMessages(['file' => 'Identification fields require the sensitive client-data permission.']);
        }
        $required = match ($resource) {
            'rooms' => ['room_number', 'category', 'room_type'],
            'clients' => ['first_name', 'last_name'],
            'staff' => ['username', 'first_name', 'last_name'],
            'tasks' => ['title', 'status', 'priority'],
            'maintenance' => ['room', 'issue', 'priority', 'cost', 'status'],
            'housekeeping' => ['room', 'task_type', 'priority', 'status'],
        };
        foreach ($required as $column) if (! in_array($column, $header, true)) throw ValidationException::withMessages(['file' => "The CSV is missing the required column: {$column}."]);

        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (count($rows) >= 5000) throw ValidationException::withMessages(['file' => 'CSV imports are limited to 5,000 rows at a time.']);
            if (count($values) === 1 && trim((string) $values[0]) === '') continue;
            $values = array_pad($values, count($header), null);
            $rows[] = ['line' => count($rows) + 2, 'data' => array_combine($header, array_map(fn ($value) => trim((string) $value), array_slice($values, 0, count($header))))];
        }
        fclose($handle);
        if ($rows === []) throw ValidationException::withMessages(['file' => 'The CSV contains no data rows.']);

        $errors = [];
        DB::transaction(function () use ($resource, $rows, $actor, &$errors): void {
            foreach ($rows as $row) {
                try { $this->importRow($resource, $row['data'], $actor); }
                catch (\Throwable $exception) { $errors[] = 'Row '.$row['line'].': '.$exception->getMessage(); }
            }
            if ($errors !== []) throw ValidationException::withMessages(['file' => implode(' ', array_slice($errors, 0, 10)).(count($errors) > 10 ? ' Additional rows were rejected.' : '')]);
        });

        return count($rows);
    }

    private function importRow(string $resource, array $row, User $actor): void
    {
        match ($resource) {
            'rooms' => $this->importRoom($row, $actor),
            'clients' => $this->importClient($row, $actor),
            'staff' => $this->importStaff($row, $actor),
            'tasks' => $this->importTask($row, $actor),
            'maintenance' => $this->importMaintenance($row, $actor),
            'housekeeping' => $this->importHousekeeping($row, $actor),
        };
    }

    private function importRoom(array $row, User $actor): void
    {
        $category = RoomCategory::where('name', $row['category'])->firstOrFail();
        $type = RoomType::where('name', $row['room_type'])->firstOrFail();
        $attributes = [
            'room_number' => $this->required($row, 'room_number'),
            'floor_id' => $row['floor'] === '' ? null : $this->relationId(Floor::class, $row['floor'], 'name'),
            'room_category_id' => $category->id,
            'room_type_id' => $type->id,
            'operational_status' => $this->enumValue($row['operational_status'] ?: RoomOperationalStatus::Available->value, RoomOperationalStatus::class),
            'housekeeping_status' => $this->enumValue($row['housekeeping_status'] ?: HousekeepingStatus::Clean->value, HousekeepingStatus::class),
            'base_rate' => $row['base_rate'] === '' ? 0 : $row['base_rate'],
            'capacity' => $row['capacity'] === '' ? 1 : $row['capacity'],
            'notes' => $row['notes'] ?: null,
            'is_active' => $this->boolean($row['is_active'], true),
        ];
        $existing = Room::where('room_number', $attributes['room_number'])->first();
        if ($existing) $this->rooms->update($existing, $attributes, $actor->id); else $this->rooms->create($attributes, $actor->id);
    }

    private function importClient(array $row, User $actor): void
    {
        $attributes = collect($row)->only(['first_name', 'middle_name', 'last_name', 'email', 'phone', 'alternate_phone', 'country', 'city', 'postal_code', 'nationality', 'date_of_birth', 'gender', 'document_type', 'document_number', 'document_expiry', 'address', 'notes'])->map(fn ($value) => $value === '' ? null : $value)->all();
        $attributes['first_name'] = $this->required($row, 'first_name');
        $attributes['last_name'] = $this->required($row, 'last_name');
        $attributes['is_active'] = $this->boolean($row['is_active'], true);
        $existing = filled($attributes['email'] ?? null) ? Client::where('email', $attributes['email'])->first() : null;
        if ($existing) $this->clients->update($existing, $attributes, $actor->id); else $this->clients->create($attributes, $actor->id);
    }

    private function importStaff(array $row, User $actor): void
    {
        $role = $row['role'] ? Role::where('name', $row['role'])->orWhere('label', $row['role'])->first() : null;
        $department = $row['department'] ? Department::where('name', $row['department'])->first() : null;
        $language = $row['language'] ? Language::where('code', $row['language'])->orWhere('name', $row['language'])->first() : null;
        $username = $this->required($row, 'username');
        $existing = User::where('username', $username)->first();
        $attributes = ['username' => $username, 'first_name' => $this->required($row, 'first_name'), 'last_name' => $this->required($row, 'last_name'), 'email' => $row['email'] ?: null, 'phone' => $row['phone'] ?: null, 'department_id' => $department?->id, 'role_id' => $role?->id, 'language_id' => $language?->id, 'is_active' => $this->boolean($row['is_active'], true), 'updated_by' => $actor->id];
        $this->staff->assertRoleAssignment($attributes['role_id'], $actor);
        if ($existing) { $existing->update($attributes); return; }
        $attributes['name'] = trim($attributes['first_name'].' '.$attributes['last_name']);
        $attributes['password'] = Hash::make(Str::random(40));
        $attributes['must_change_password'] = true;
        $attributes['created_by'] = $actor->id;
        User::create($attributes);
    }

    private function importTask(array $row, User $actor): void
    {
        $attributes = ['title' => $this->required($row, 'title'), 'description' => $row['description'] ?: null, 'category' => $row['category'] ?: null, 'status' => $this->enumValue($this->required($row, 'status'), TaskStatus::class), 'priority' => $this->enumValue($this->required($row, 'priority'), TaskPriority::class), 'department_id' => $this->relationId(Department::class, $row['department'], 'name'), 'room_id' => $this->relationId(Room::class, $row['room'], 'room_number'), 'reservation_id' => $this->relationId(\App\Models\Reservation::class, $row['reservation_code'], 'code'), 'client_id' => $this->relationId(Client::class, $row['client_email'], 'email'), 'due_at' => $row['due_at'] ?: null, 'estimated_minutes' => $row['estimated_minutes'] ?: null, 'actual_minutes' => $row['actual_minutes'] ?: null, 'assignee_ids' => $this->staffIds($row['assignees'])];
        $existing = $row['task_number'] ? Task::where('task_number', $row['task_number'])->first() : null;
        if ($existing) $this->tasks->update($existing, $attributes, $actor->id); else $this->tasks->create($attributes, $actor->id);
    }

    private function importMaintenance(array $row, User $actor): void
    {
        $attributes = ['room_id' => $this->relationId(Room::class, $this->required($row, 'room'), 'room_number'), 'assignee_id' => $this->staffId($row['assignee']), 'issue' => $this->required($row, 'issue'), 'description' => $row['description'] ?: null, 'priority' => $this->enumValue($this->required($row, 'priority'), TaskPriority::class), 'starts_at' => $row['starts_at'] ?: null, 'ends_at' => $row['ends_at'] ?: null, 'due_at' => $row['due_at'] ?: null, 'cost' => $this->required($row, 'cost'), 'status' => $this->enumValue($this->required($row, 'status'), TaskStatus::class), 'notes' => $row['notes'] ?: null];
        $existing = $row['id'] ? MaintenanceTask::find($row['id']) : null;
        if ($existing) $this->maintenance->update($existing, $attributes, $actor->id); else $this->maintenance->create($attributes, $actor->id);
    }

    private function importHousekeeping(array $row, User $actor): void
    {
        $attributes = ['room_id' => $this->relationId(Room::class, $this->required($row, 'room'), 'room_number'), 'assignee_id' => $this->staffId($row['assignee']), 'task_type' => $this->required($row, 'task_type'), 'priority' => $this->enumValue($this->required($row, 'priority'), TaskPriority::class), 'due_at' => $row['due_at'] ?: null, 'status' => $this->enumValue($this->required($row, 'status'), TaskStatus::class), 'notes' => $row['notes'] ?: null];
        $existing = $row['id'] ? HousekeepingTask::find($row['id']) : null;
        if ($existing) $this->housekeeping->update($existing, $attributes, $actor->id); else $this->housekeeping->create($attributes, $actor->id);
    }

    private function roomRows(array $headers): array { return Room::with(['floor', 'category', 'roomType'])->orderBy('room_number')->get()->map(fn (Room $room) => ['room_number' => $room->room_number, 'floor' => $room->floor?->name, 'category' => $room->category?->name, 'room_type' => $room->roomType?->name, 'operational_status' => $room->operational_status?->value, 'housekeeping_status' => $room->housekeeping_status?->value, 'base_rate' => $room->base_rate, 'capacity' => $room->capacity, 'notes' => $room->notes, 'is_active' => $room->is_active ? '1' : '0'])->all(); }
    private function clientRows(array $headers, bool $sensitive): array { return Client::orderBy('last_name')->orderBy('first_name')->get()->map(function (Client $client) use ($sensitive) { $row = ['first_name' => $client->first_name, 'middle_name' => $client->middle_name, 'last_name' => $client->last_name, 'email' => $client->email, 'phone' => $client->phone, 'alternate_phone' => $client->alternate_phone, 'country' => $client->country, 'city' => $client->city, 'postal_code' => $client->postal_code, 'nationality' => $client->nationality, 'date_of_birth' => $client->date_of_birth?->toDateString(), 'gender' => $client->gender, 'document_type' => $client->document_type, 'document_number' => $client->document_number, 'document_expiry' => $client->document_expiry?->toDateString(), 'address' => $client->address, 'notes' => $client->notes, 'is_active' => $client->is_active ? '1' : '0']; if (! $sensitive) foreach (['document_type', 'document_number', 'document_expiry'] as $field) unset($row[$field]); return $row; })->all(); }
    private function staffRows(array $headers): array { return User::with(['department', 'role', 'language'])->orderBy('name')->get()->map(fn (User $user) => ['username' => $user->username, 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->email, 'phone' => $user->phone, 'department' => $user->department?->name, 'role' => $user->role?->name, 'language' => $user->language?->code ?? $user->language?->name, 'is_active' => $user->is_active ? '1' : '0', 'last_login_at' => $user->last_login_at?->toDateTimeString()])->all(); }
    private function taskRows(array $headers, User $actor): array { $query = Task::with(['department', 'room', 'reservation', 'client', 'assignees'])->active(); if (! $actor->hasPermission('tasks.view_all_departments') && ! $actor->hasPermission('tasks.manage')) $query->where(fn ($q) => $q->where('created_by', $actor->id)->orWhereHas('assignees', fn ($assignee) => $assignee->whereKey($actor->id))->orWhereNull('department_id')->orWhere('department_id', $actor->department_id)); return $query->orderBy('id')->get()->map(fn (Task $task) => ['task_number' => $task->task_number, 'title' => $task->title, 'description' => $task->description, 'category' => $task->category, 'status' => $task->status?->value, 'priority' => $task->priority?->value, 'department' => $task->department?->name, 'room' => $task->room?->room_number, 'reservation_code' => $task->reservation?->code, 'client_email' => $task->client?->email, 'assignees' => $task->assignees->pluck('username')->filter()->join('|'), 'due_at' => $task->due_at?->toDateTimeString(), 'estimated_minutes' => $task->estimated_minutes, 'actual_minutes' => $task->actual_minutes, 'archived' => $task->archived_at ? '1' : '0'])->all(); }
    private function maintenanceRows(array $headers): array { return MaintenanceTask::with(['room', 'assignee'])->orderBy('id')->get()->map(fn (MaintenanceTask $task) => ['id' => $task->id, 'room' => $task->room?->room_number, 'assignee' => $task->assignee?->username ?? $task->assignee?->email, 'issue' => $task->issue, 'description' => $task->description, 'priority' => $task->priority?->value, 'starts_at' => $task->starts_at?->toDateTimeString(), 'ends_at' => $task->ends_at?->toDateTimeString(), 'due_at' => $task->due_at?->toDateTimeString(), 'cost' => $task->cost, 'status' => $task->status?->value, 'notes' => $task->notes, 'completed_at' => $task->completed_at?->toDateTimeString()])->all(); }
    private function housekeepingRows(array $headers): array { return HousekeepingTask::with(['room', 'assignee'])->orderBy('id')->get()->map(fn (HousekeepingTask $task) => ['id' => $task->id, 'room' => $task->room?->room_number, 'assignee' => $task->assignee?->username ?? $task->assignee?->email, 'task_type' => $task->task_type, 'priority' => $task->priority?->value, 'due_at' => $task->due_at?->toDateTimeString(), 'status' => $task->status?->value, 'notes' => $task->notes, 'completed_at' => $task->completed_at?->toDateTimeString()])->all(); }

    private function normalizeHeader(string $header): string { return Str::snake(trim($header)); }
    private function required(array $row, string $key): string { if (trim((string) ($row[$key] ?? '')) === '') throw new \InvalidArgumentException("{$key} is required."); return trim((string) $row[$key]); }
    private function boolean(?string $value, bool $default = false): bool { return $value === '' || $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? ((int) $value === 1); }
    private function enumValue(string $value, string $enum): string { $case = $enum::tryFrom(strtolower(trim($value))); if (! $case) throw new \InvalidArgumentException("Invalid {$enum} value: {$value}."); return $case->value; }
    private function relationId(string $model, ?string $value, string $column): ?int { if (! $value) return null; $id = $model::where($column, $value)->value('id'); if (! $id) throw new \InvalidArgumentException("Could not find {$column} '{$value}'."); return (int) $id; }
    private function staffId(?string $value): ?int { if (! $value) return null; return $this->relationId(User::class, $value, str_contains($value, '@') ? 'email' : 'username'); }
    private function staffIds(?string $value): array { return collect(explode('|', (string) $value))->map(fn ($item) => trim($item))->filter()->map(fn ($item) => $this->staffId($item))->filter()->values()->all(); }
}
