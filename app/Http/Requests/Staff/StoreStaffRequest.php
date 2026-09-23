<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Services\SystemSettingsService;
use App\Services\EmailAddressPolicy;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $staff = $this->route('staff');
        return [
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff), function (string $attribute, mixed $value, \Closure $fail): void {
                if (filled($value) && ! app(EmailAddressPolicy::class)->isDeliverable($value)) {
                    $fail('Please use a real email address that can receive mail.');
                }
            }], 'phone' => ['nullable', 'string', 'max:40'],
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($staff)],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'], 'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'], 'is_active' => ['sometimes', 'boolean'], 'must_change_password' => ['sometimes', 'boolean'],
            'password' => [$staff ? 'nullable' : 'required', 'confirmed', app(SystemSettingsService::class)->passwordRule()],
        ];
    }
}
