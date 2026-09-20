<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreApiTokenRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasPermission('api_tokens.manage') ?? false; }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'abilities' => ['required', 'array', 'min:1'], 'abilities.*' => ['string', 'in:reservations:read,reservations:write,rooms:read,availability:read,clients:read,payments:read']];
    }
}
