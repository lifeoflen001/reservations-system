<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['label' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:1000'], 'permission_ids' => ['array'], 'permission_ids.*' => ['integer', 'exists:permissions,id'], 'is_active' => ['sometimes', 'boolean']]; }
}
