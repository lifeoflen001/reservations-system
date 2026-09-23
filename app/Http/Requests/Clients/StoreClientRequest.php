<?php

namespace App\Http\Requests\Clients;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'], 'middle_name' => ['nullable', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:40'], 'alternate_phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:100'], 'city' => ['nullable', 'string', 'max:100'], 'postal_code' => ['nullable', 'string', 'max:30'], 'nationality' => ['nullable', 'string', 'size:2', 'uppercase'],
            'date_of_birth' => ['nullable', 'date', 'before:today'], 'gender' => ['nullable', 'string', 'max:30'],
            'document_type' => ['nullable', Rule::in(['passport', 'national_id', 'drivers_license', 'other'])], 'document_number' => ['nullable', 'string', 'max:100'], 'document_expiry' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:5000'], 'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
