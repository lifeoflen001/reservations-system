<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\EmailAddressPolicy;

class UpdateEmailIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('email_settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'], 'mailer' => ['required', 'in:smtp'],
            'host' => ['required', 'string', 'max:255'], 'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', 'in:tls,ssl,none'], 'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:500'], 'from_email' => ['required', 'email', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! app(EmailAddressPolicy::class)->isDeliverable($value)) {
                    $fail('Please use a real sender email address that can send mail.');
                }
            }],
            'from_name' => ['required', 'string', 'max:255'],
        ];
    }
}
