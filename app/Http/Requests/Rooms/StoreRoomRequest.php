<?php

namespace App\Http\Requests\Rooms;

use App\Enums\HousekeepingStatus;
use App\Enums\RoomOperationalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $room = $this->route('room');
        return [
            'room_number' => ['required', 'string', 'max:50', Rule::unique('rooms', 'room_number')->ignore($room)],
            'floor_id' => ['nullable', 'integer', 'exists:floors,id'],
            'room_category_id' => ['required', 'integer', 'exists:room_categories,id'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'operational_status' => ['required', Rule::enum(RoomOperationalStatus::class)],
            'housekeeping_status' => ['required', Rule::enum(HousekeepingStatus::class)],
            'capacity' => ['required', 'integer', 'min:1', 'max:255'],
            'base_rate' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
