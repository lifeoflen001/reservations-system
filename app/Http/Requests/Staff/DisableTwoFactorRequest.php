<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DisableTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'code' => ['nullable', 'digits:6'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('code') && ! $this->filled('recovery_code')) {
                $validator->errors()->add('code', 'Enter an authenticator code or recovery code to disable two-factor authentication.');
            }

            if ($this->filled('code') && $this->filled('recovery_code')) {
                $validator->errors()->add('code', 'Enter only one verification code.');
            }
        });
    }
}
