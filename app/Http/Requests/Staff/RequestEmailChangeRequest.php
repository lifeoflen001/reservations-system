<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Services\EmailAddressPolicy;

class RequestEmailChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->getKey()),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! app(EmailAddressPolicy::class)->isDeliverable($value)) {
                        $fail('Please use a real email address that can receive mail.');
                    }
                },
            ],
        ];
    }
}
