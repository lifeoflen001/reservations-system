<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'theme' => ['nullable', 'string', 'in:system,light,dark'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'in:in_app,email,whatsapp'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'in:operational,financial,integrations'],
        ];
    }
}
