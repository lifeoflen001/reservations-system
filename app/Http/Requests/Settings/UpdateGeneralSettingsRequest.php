<?php

namespace App\Http\Requests\Settings;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'], 'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:120'], 'country' => ['nullable', 'string', 'max:120'],
            'timezone' => ['required', Rule::in(\DateTimeZone::listIdentifiers())],
            'default_language' => ['required', Rule::exists(Language::class, 'code')->where('is_active', true)],
            'theme' => ['required', Rule::in(['light', 'dark', 'system'])],
            'check_in_time' => ['required', 'date_format:H:i'], 'check_out_time' => ['required', 'date_format:H:i'],
        ];
    }
}
