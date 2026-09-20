<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:min_width=160,min_height=160,max_width=4000,max_height=4000', 'max:5120'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }
}
