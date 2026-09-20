@php($editing = (bool) $reservation)
@php($modalId = $editing ? 'edit-reservation-'.$reservation->id : 'new-reservation')
@php($action = $editing ? route('reservations.update', $reservation) : route('reservations.store'))
@php($defaultCheckIn = request('check_in') ? \Carbon\Carbon::parse(request('check_in'))->format('Y-m-d').'T'.substr($defaultCheckInTime, 0, 5).':00' : now()->format('Y-m-d').'T'.substr($defaultCheckInTime, 0, 5).':00')
@php($defaultCheckOut = request('check_out') ? \Carbon\Carbon::parse(request('check_out'))->format('Y-m-d').'T'.substr($defaultCheckOutTime, 0, 5).':00' : now()->addDay()->format('Y-m-d').'T'.substr($defaultCheckOutTime, 0, 5).':00')
<x-ui.modal :id="$modalId" :title="$editing ? 'Edit reservation' : 'New reservation'" size="large" :open="$open">
    <form method="POST" action="{{ $action }}" class="reservation-form" data-reservation-form data-draft-form data-draft-key="reservation-{{ $reservation?->id ?? 'new' }}" data-availability-url="{{ route('room-planning.available-rooms') }}" data-ignore-reservation-id="{{ $reservation?->id }}">
        @csrf @if($editing) @method('PUT') @endif
        <div class="reservation-form__grid">
            <x-form.select name="client_id" id="client_id_{{ $modalId }}" label="Client" :searchable="true" required><option value="">Select client</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected((string) old('client_id', $reservation?->client_id) === (string) $client->id)>{{ $client->full_name }} · {{ $client->email }}</option>@endforeach</x-form.select>
            <x-form.select name="room_id" id="room_id_{{ $modalId }}" label="Room" :searchable="true" required data-room-select help="Room options are refreshed for the selected stay dates."><option value="">Select room and dates first</option>@foreach($rooms as $room)<option value="{{ $room->id }}" data-rate="{{ (float) ($room->base_rate ?: $room->roomType?->base_rate ?? 0) }}" data-capacity="{{ $room->capacity }}" @selected((string) old('room_id', $reservation?->room_id ?? request('room_id')) === (string) $room->id)>{{ $room->room_number }} · {{ $room->roomType?->name ?? 'Room' }} · {{ $room->operational_status->label() }}</option>@endforeach</x-form.select>
            @error('room_id')<small class="form-error form-field--full">{{ $message }}</small>@enderror
            <small class="form-error form-field--full" data-availability-error role="alert" hidden></small>
            <x-form.input name="check_in" type="datetime-local" label="Check-in" :value="old('check_in', $reservation?->check_in?->format('Y-m-d\\TH:i') ?? $defaultCheckIn)" required data-reservation-check-in />
            <x-form.input name="check_out" type="datetime-local" label="Check-out" :value="old('check_out', $reservation?->check_out?->format('Y-m-d\\TH:i') ?? $defaultCheckOut)" required data-reservation-check-out />
            <x-form.input name="adults" type="number" label="Adults" :value="old('adults', $reservation?->adults ?? 1)" min="1" required />
            <x-form.input name="children" type="number" label="Children" :value="old('children', $reservation?->children ?? 0)" min="0" required />
            <x-form.select name="status" label="Status" required>@foreach($statuses as $status)@if($editing ? ! in_array($status, [\App\Enums\ReservationStatus::Cancelled, \App\Enums\ReservationStatus::NoShow], true) : in_array($status, [\App\Enums\ReservationStatus::Pending, \App\Enums\ReservationStatus::Confirmed], true))<option value="{{ $status->value }}" @selected(old('status', $reservation?->status?->value ?? 'confirmed') === $status->value)>{{ $status->label() }}</option>@endif @endforeach</x-form.select>
            <x-form.select name="reservation_source_id" label="Source"><option value="">Select source</option>@foreach($sources as $source)<option value="{{ $source->id }}" @selected((string) old('reservation_source_id', $reservation?->reservation_source_id) === (string) $source->id)>{{ $source->name }}</option>@endforeach</x-form.select>
            <x-form.input name="nightly_rate" type="number" step="0.01" label="Nightly rate" :value="old('nightly_rate', $reservation?->nightly_rate ?? '')" min="0" required data-nightly-rate />
            <x-form.input name="total_amount" type="text" label="Total amount" :value="old('total_amount', $reservation?->total_amount ?? '')" readonly data-total-amount />
            <x-form.textarea name="notes" label="Notes" class="form-field--full" :value="old('notes', $reservation?->notes)" rows="4" />
        </div>
        <div class="modal-form-footer"><button type="button" class="ui-button ui-button--secondary" data-modal-close>Cancel</button><button type="submit" class="ui-button ui-button--primary"><x-ui.icon name="save" size="16" /> Save</button></div>
    </form>
</x-ui.modal>
