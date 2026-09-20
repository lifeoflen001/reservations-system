<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identity' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function authenticate(): User
    {
        $user = User::query()
            ->where('email', $this->string('identity')->toString())
            ->orWhere('username', $this->string('identity')->toString())
            ->first();

        error_log('LOGIN_DIAG '.json_encode([
            'identity_type' => str_contains($this->string('identity')->toString(), '@') ? 'email' : 'username',
            'user_found' => (bool) $user,
            'user_active' => (bool) $user?->is_active,
            'password_match' => (bool) ($user && Hash::check($this->string('password')->toString(), $user->password)),
        ]));

        if (! $user || ! $user->is_active || ! Hash::check($this->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'identity' => __('These credentials do not match our records.'),
            ]);
        }

        return $user;
    }
}
