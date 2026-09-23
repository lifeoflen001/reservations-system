<?php

namespace App\Http\Requests\Setup;

use App\Enums\OperatingMode;
use App\Services\SystemSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Services\EmailAddressPolicy;

class StoreSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:200'], 'email' => ['required', 'email', 'max:255', 'unique:users,email', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! app(EmailAddressPolicy::class)->isDeliverable($value)) {
                    $fail('Please use a real email address that can receive mail.');
                }
            }],
            'username' => ['required', 'alpha_dash', 'max:100', 'unique:users,username'],
            'password' => ['required', 'confirmed', app(SystemSettingsService::class)->passwordRule()],
            'operating_mode' => ['required', Rule::enum(OperatingMode::class)],
            'property_name' => ['required', 'string', 'max:255'], 'property_email' => ['nullable', 'email', 'max:255'],
            'property_phone' => ['nullable', 'string', 'max:50'], 'property_address' => ['nullable', 'string', 'max:1000'],
            'property_city' => ['nullable', 'string', 'max:120'], 'property_country' => ['nullable', 'string', 'max:120'],
            'timezone' => ['required', Rule::in(\DateTimeZone::listIdentifiers())],
            'currency' => ['required', 'string', 'exists:currencies,code'], 'language' => ['required', 'string', 'exists:languages,code'],
        ];
    }
}
