<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:min_width=160,min_height=160,max_width=4000,max_height=4000', 'max:5120'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }
}
