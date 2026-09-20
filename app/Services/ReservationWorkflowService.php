<?php

namespace App\Services;

use App\Enums\HousekeepingStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomOperationalStatus;
use App\Events\GuestCheckedIn;
use App\Events\GuestCheckedOut;
use App\Events\ReservationCancelled;
use App\Events\ReservationMarkedNoShow;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use LogicException;

class ReservationWorkflowService
{
    public function __construct(
        private readonly ReservationStateMachine $stateMachine,
        private readonly RoomStatusService $roomStatus,
        private readonly HousekeepingService $housekeeping,
    ) {}

    public function checkIn(Reservation|int $reservation, ?int $actorId = null): Reservation
    {
        $result = DB::transaction(function () use ($reservation, $actorId) {
            $reservation = $this->lockReservation($reservation);
            $room = $reservation->room()->lockForUpdate()->firstOrFail();
            if ($reservation->status !== ReservationStatus::Confirmed) throw new LogicException('Only confirmed reservations can be checked in.');
            $reservation = $this->stateMachine->apply($reservation, ReservationStatus::CheckedIn, $actorId);
            $room->update(['operational_status' => RoomOperationalStatus::Occupied]);
            return $reservation;
        });
        event(new GuestCheckedIn($result));
        return $result;
    }

    public function checkOut(Reservation|int $reservation, ?int $actorId = null): Reservation
    {
        $result = DB::transaction(function () use ($reservation, $actorId) {
            $reservation = $this->lockReservation($reservation);
            $room = $reservation->room()->lockForUpdate()->firstOrFail();
            if ($reservation->status !== ReservationStatus::CheckedIn) throw new LogicException('Only checked-in reservations can be checked out.');
            $reservation = $this->stateMachine->apply($reservation, ReservationStatus::CheckedOut, $actorId);
            $room->update(['operational_status' => RoomOperationalStatus::MustClean, 'housekeeping_status' => HousekeepingStatus::Dirty]);
            $this->housekeeping->ensureTurnoverTask($room, $actorId, $reservation->code);
            return $reservation;
        });
        event(new GuestCheckedOut($result));
        return $result;
    }

    public function cancel(Reservation|int $reservation, ?int $actorId = null, ?string $reason = null): Reservation
    {
        [$result, $room] = DB::transaction(function () use ($reservation, $actorId, $reason) {
            $reservation = $this->lockReservation($reservation);
            $room = $reservation->room()->lockForUpdate()->firstOrFail();
            if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) throw new LogicException('Only pending or confirmed reservations can be cancelled.');
            return [$this->stateMachine->apply($reservation, ReservationStatus::Cancelled, $actorId, $reason), $room];
        });
        $this->roomStatus->sync($room);
        event(new ReservationCancelled($result));
        return $result;
    }

    public function markNoShow(Reservation|int $reservation, ?int $actorId = null): Reservation
    {
        [$result, $room] = DB::transaction(function () use ($reservation, $actorId) {
            $reservation = $this->lockReservation($reservation);
            $room = $reservation->room()->lockForUpdate()->firstOrFail();
            if ($reservation->status !== ReservationStatus::Confirmed) throw new LogicException('Only confirmed reservations can be marked as no-show.');
            return [$this->stateMachine->apply($reservation, ReservationStatus::NoShow, $actorId), $room];
        });
        $this->roomStatus->sync($room);
        event(new ReservationMarkedNoShow($result));
        return $result;
    }

    private function lockReservation(Reservation|int $reservation): Reservation
    {
        return Reservation::query()->whereKey($reservation instanceof Reservation ? $reservation->id : $reservation)->lockForUpdate()->firstOrFail();
    }
}
