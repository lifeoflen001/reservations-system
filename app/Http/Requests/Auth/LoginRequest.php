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

        if (! $user || ! $user->is_active || ! Hash::check($this->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'identity' => __('These credentials do not match our records.'),
            ]);
        }

        return $user;
    }
}
