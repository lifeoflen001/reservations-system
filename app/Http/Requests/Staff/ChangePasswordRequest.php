<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\SystemSettingsService;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', app(SystemSettingsService::class)->passwordRule()]]; }
}
