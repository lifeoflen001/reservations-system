<?php

namespace App\Http\Requests\Maintenance;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceTaskRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'issue' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'due_at' => ['nullable', 'date'],
            'cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
