<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreWebhookEndpointRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('events'))) {
            $this->merge(['events' => array_values(array_filter(array_map('trim', explode(',', $this->input('events')))))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('webhooks.manage') ?? false;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'url' => ['required', 'url:http,https', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
            $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
            if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local')) {
                $fail('Webhook URLs cannot target local hosts.');
            }
        }], 'events' => ['required', 'array', 'min:1'], 'events.*' => ['string', 'in:reservation.created,reservation.updated,reservation.confirmed,reservation.cancelled,guest.checked_in,guest.checked_out,payment.received,payment.voided,room.status_changed,housekeeping.completed,maintenance.completed'], 'signing_secret' => ['nullable', 'string', 'min:16', 'max:255']];
    }
}
