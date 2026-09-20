<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('whatsapp.manage') ?? false;
    }

    public function rules(): array
    {
        return ['enabled' => ['sometimes', 'boolean'], 'provider' => ['required', 'string', 'max:100'], 'phone_number_id' => ['nullable', 'string', 'max:255'], 'api_base_url' => ['nullable', 'url:http,https', 'max:500'], 'access_token' => ['nullable', 'string', 'max:1000']];
    }
}
