<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Validation\ValidationException;

class ReservationStateMachine
{
    public function canTransition(ReservationStatus $from, ReservationStatus $to): bool
    {
        return in_array($to, match ($from) {
            ReservationStatus::Pending => [ReservationStatus::Confirmed, ReservationStatus::Cancelled],
            ReservationStatus::Confirmed => [ReservationStatus::CheckedIn, ReservationStatus::Cancelled, ReservationStatus::NoShow],
            ReservationStatus::CheckedIn => [ReservationStatus::CheckedOut],
            ReservationStatus::CheckedOut, ReservationStatus::Cancelled, ReservationStatus::NoShow => [],
        }, true);
    }

    public function assertTransition(ReservationStatus $from, ReservationStatus $to): void
    {
        if ($from !== $to && ! $this->canTransition($from, $to)) {
            throw ValidationException::withMessages(['status' => "A reservation cannot move from {$from->label()} to {$to->label()}."]);
        }
    }

    public function apply(Reservation $reservation, ReservationStatus $status, ?int $actorId = null, ?string $reason = null): Reservation
    {
        $this->assertTransition($reservation->status, $status);
        $attributes = ['status' => $status, 'updated_by' => $actorId];

        if ($status === ReservationStatus::CheckedIn) $attributes['checked_in_at'] = now();
        if ($status === ReservationStatus::CheckedOut) $attributes['checked_out_at'] = now();
        if ($status === ReservationStatus::Cancelled) {
            $attributes['cancelled_at'] = now();
            $attributes['cancelled_by'] = $actorId;
            $attributes['cancellation_reason'] = $reason;
        }
        if ($status === ReservationStatus::NoShow) {
            $attributes['no_show_at'] = now();
            $attributes['no_show_by'] = $actorId;
        }

        $reservation->update($attributes);
        return $reservation->refresh();
    }
}
