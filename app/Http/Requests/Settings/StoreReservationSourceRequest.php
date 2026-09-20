<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('reservation_sources.manage') ?? false;
    }

    public function rules(): array
    {
        $source = $this->route('source');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('reservation_sources', 'name')->ignore($source)],
            'code' => ['required', 'alpha_dash', 'max:80', Rule::unique('reservation_sources', 'code')->ignore($source)],
            'description' => ['nullable', 'string', 'max:1000'], 'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
