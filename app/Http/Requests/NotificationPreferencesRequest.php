<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->hasPermission('notifications.manage') || $this->user()?->hasPermission('notifications.view'));
    }

    public function rules(): array
    {
        return ['channels' => ['sometimes', 'array'], 'channels.*' => ['string', 'in:in_app,email,whatsapp'], 'categories' => ['sometimes', 'array'], 'categories.*' => ['string', 'in:operational,financial,integrations']];
    }
}
