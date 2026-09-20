<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->session()->has('login.id');
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'digits:6'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
        ];
    }
}
