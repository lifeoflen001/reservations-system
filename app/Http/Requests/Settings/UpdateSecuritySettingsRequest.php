<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSecuritySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('security.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'session_timeout' => ['required', 'integer', 'min:5', 'max:43200'],
            'password_min_length' => ['required', 'integer', 'min:6', 'max:128'],
            'password_require_mixed_case' => ['sometimes', 'boolean'],
            'password_require_numbers' => ['sometimes', 'boolean'],
            'password_require_symbols' => ['sometimes', 'boolean'],
        ];
    }
}
