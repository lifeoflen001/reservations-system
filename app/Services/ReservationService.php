<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Events\ReservationConfirmed;
use App\Events\ReservationCreated;
use App\Events\ReservationUpdated;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public function __construct(
        private readonly RoomAvailabilityService $availability,
        private readonly ReservationCodeGenerator $codes,
        private readonly ReservationStateMachine $stateMachine,
        private readonly RoomStatusService $roomStatus,
    ) {}

    public function create(array $attributes, ?int $actorId = null): Reservation
    {
        $reservation = DB::transaction(function () use ($attributes, $actorId) {
            $checkIn = Carbon::parse($attributes['check_in']);
            $checkOut = Carbon::parse($attributes['check_out']);
            $room = Room::query()->withoutGlobalScope('tenant-ownership')->lockForUpdate()->findOrFail($attributes['room_id']);
            $ownership = app(TenantOwnershipConsistencyService::class);
            $ownership->assertCurrentProperty($room->property_id, 'The selected room belongs to another property.');
            $client = Client::query()->withoutGlobalScope('tenant-ownership')->findOrFail($attributes['client_id']);
            $ownership->assertCurrentOrganization($client->organization_id, 'The selected guest belongs to another organization.');
            if ($room->property?->organization_id !== null && $client->organization_id !== null && (int) $room->property->organization_id !== (int) $client->organization_id) {
                throw new \InvalidArgumentException('The selected guest and room belong to different organizations.');
            }
            $this->availability->assertCapacity($room, (int) $attributes['adults'], (int) ($attributes['children'] ?? 0));
            $this->availability->assertAvailable($room, $checkIn, $checkOut);
            $rate = (float) $attributes['nightly_rate'];
            $nights = $checkIn->copy()->startOfDay()->diffInDays($checkOut->copy()->startOfDay());
            $status = ReservationStatus::from($attributes['status'] ?? ReservationStatus::Pending->value);
            if (! in_array($status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
                throw new \InvalidArgumentException('New reservations may only be pending or confirmed.');
            }
            $reservation = Reservation::create([
                'code' => $this->codes->generate(),
                'client_id' => $attributes['client_id'],
                'room_id' => $room->id,
                'reservation_source_id' => ($attributes['reservation_source_id'] ?? null) ?: null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'adults' => (int) $attributes['adults'],
                'children' => (int) ($attributes['children'] ?? 0),
                'nightly_rate' => $rate,
                'total_amount' => $nights * $rate,
                'status' => $status,
                'notes' => $attributes['notes'] ?? null,
            ]);
            return [$reservation, $room, $status];
        });
        [$reservation, $room, $status] = $reservation;
        $this->roomStatus->sync($room);
        event(new ReservationCreated($reservation));
        if ($status === ReservationStatus::Confirmed) event(new ReservationConfirmed($reservation));
        return $reservation->refresh();
    }

    public function update(Reservation $existing, array $attributes, ?int $actorId = null): Reservation
    {
        [$reservation, $room, $status, $statusChanged] = DB::transaction(function () use ($existing, $attributes, $actorId) {
            $reservation = Reservation::query()->withoutGlobalScope('tenant-ownership')->lockForUpdate()->findOrFail($existing->id);
            $checkIn = Carbon::parse($attributes['check_in']);
            $checkOut = Carbon::parse($attributes['check_out']);
            $roomIds = collect([$reservation->room_id, (int) $attributes['room_id']])->unique()->sort()->values();
            $rooms = $roomIds->mapWithKeys(fn (int $id) => [$id => Room::query()->withoutGlobalScope('tenant-ownership')->lockForUpdate()->findOrFail($id)]);
            $room = $rooms[(int) $attributes['room_id']];
            $ownership = app(TenantOwnershipConsistencyService::class);
            $ownership->assertSameProperty($reservation->property_id, $room->property_id, 'The reservation and room must belong to the same property.');
            $ownership->assertCurrentProperty($reservation->property_id, 'The reservation belongs to another property.');
            $client = Client::query()->withoutGlobalScope('tenant-ownership')->findOrFail($attributes['client_id']);
            $ownership->assertCurrentOrganization($client->organization_id, 'The selected guest belongs to another organization.');
            if ($room->property?->organization_id !== null && $client->organization_id !== null && (int) $room->property->organization_id !== (int) $client->organization_id) {
                throw new \InvalidArgumentException('The selected guest and room belong to different organizations.');
            }
            $this->availability->assertCapacity($room, (int) $attributes['adults'], (int) ($attributes['children'] ?? 0));
            $this->availability->assertAvailable($room, $checkIn, $checkOut, $reservation->id);
            $nights = $checkIn->copy()->startOfDay()->diffInDays($checkOut->copy()->startOfDay());
            $status = ReservationStatus::from($attributes['status']);
            $statusChanged = $status !== $reservation->status;
            if ($statusChanged) $this->stateMachine->assertTransition($reservation->status, $status);
            $reservation->update([
                'client_id' => $attributes['client_id'], 'room_id' => $room->id,
                'reservation_source_id' => ($attributes['reservation_source_id'] ?? null) ?: null,
                'updated_by' => $actorId, 'check_in' => $checkIn, 'check_out' => $checkOut,
                'adults' => (int) $attributes['adults'], 'children' => (int) ($attributes['children'] ?? 0),
                'nightly_rate' => (float) $attributes['nightly_rate'],
                'total_amount' => $nights * (float) $attributes['nightly_rate'],
                'notes' => $attributes['notes'] ?? null,
            ]);
            if ($statusChanged) $reservation = $this->stateMachine->apply($reservation, $status, $actorId);
            return [$reservation, $room, $status, $statusChanged];
        });
        $this->roomStatus->sync($room);
        event(new ReservationUpdated($reservation));
        if ($statusChanged && $status === ReservationStatus::Confirmed) event(new ReservationConfirmed($reservation));
        return $reservation->refresh();
    }
}
