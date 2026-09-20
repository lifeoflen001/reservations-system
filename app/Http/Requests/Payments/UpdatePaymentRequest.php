<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
            'method' => ['nullable', 'string', 'exists:payment_methods,code'],
            'reference' => ['nullable', 'string', 'max:100', Rule::unique('payments', 'reference')->ignore($this->route('payment'))],
            'transaction_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
