<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'reservation_id' => ['required', 'integer', 'exists:reservations,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'method' => ['required', 'string', 'exists:payment_methods,code'],
            'reference' => ['nullable', 'string', 'max:100', 'unique:payments,reference'],
            'transaction_date' => ['required', 'date'],
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
