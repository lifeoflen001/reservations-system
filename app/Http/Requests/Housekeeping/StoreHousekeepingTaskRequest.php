<?php

namespace App\Http\Requests\Housekeeping;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHousekeepingTaskRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'task_type' => ['required', Rule::in(['cleaning', 'inspection', 'linen_change', 'turndown', 'deep_cleaning'])],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'due_at' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
