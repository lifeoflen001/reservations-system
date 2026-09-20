<?php

namespace App\Http\Requests\Reservations;

use App\Enums\ReservationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'reservation_source_id' => ['nullable', 'integer', 'exists:reservation_sources,id'],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:255'],
            'children' => ['required', 'integer', 'min:0', 'max:255'],
            'status' => ['required', Rule::enum(ReservationStatus::class)],
            'nightly_rate' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
