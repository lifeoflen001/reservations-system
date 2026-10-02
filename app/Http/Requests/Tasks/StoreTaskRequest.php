<?php
namespace App\Http\Requests\Tasks;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\TenantValidation;
class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['title' => ['required','string','max:255'], 'description' => ['nullable','string','max:10000'], 'category' => ['nullable','string','max:80'], 'department_id' => ['nullable','integer', TenantValidation::propertyExists('departments')], 'room_id' => ['nullable','integer', TenantValidation::propertyExists('rooms')], 'reservation_id' => ['nullable','integer', TenantValidation::propertyExists('reservations')], 'client_id' => ['nullable','integer', TenantValidation::organizationExists('clients')], 'due_at' => ['nullable','date'], 'estimated_minutes' => ['nullable','integer','min:1','max:100000'], 'status' => ['required', Rule::enum(TaskStatus::class)], 'priority' => ['required', Rule::enum(TaskPriority::class)], 'assignee_ids' => ['nullable','array'], 'assignee_ids.*' => ['integer','exists:users,id'], 'tags' => ['nullable','array'], 'tags.*' => ['string','max:40'], 'tags_text' => ['nullable','string','max:1000'], 'subtasks' => ['nullable','array'], 'subtasks.*' => ['string','max:255']]; }
}
