<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialService
{
    public function paidAmount(Reservation|int $reservation): float
    {
        if ($reservation instanceof Reservation && $reservation->relationLoaded('payments')) {
            return (float) $reservation->payments
                ->filter(fn (Payment $payment) => $payment->status?->countsTowardsPaid())
                ->sum('amount');
        }

        $id = $reservation instanceof Reservation ? $reservation->getKey() : $reservation;
        return (float) Payment::query()->where('reservation_id', $id)->successful()->sum('amount');
    }

    public function balance(Reservation|int $reservation): float
    {
        $model = $reservation instanceof Reservation ? $reservation : Reservation::findOrFail($reservation);
        return max(0, round((float) $model->total_amount - $this->paidAmount($model), 2));
    }

    public function balanceFromAggregate(Reservation $reservation): float
    {
        return max(0, round((float) $reservation->total_amount - (float) ($reservation->paid_amount ?? 0), 2));
    }

    public function clientTotalSpent(Client|int $client): float
    {
        $id = $client instanceof Client ? $client->getKey() : $client;
        return (float) Payment::query()->where('client_id', $id)->successful()->sum('amount');
    }

    public function outstandingBalance(?Builder $reservations = null): float
    {
        $paid = Payment::query()->successful()
            ->select('reservation_id')
            ->selectRaw('SUM(amount) as paid_amount')
            ->groupBy('reservation_id');
        $query = ($reservations ? clone $reservations : Reservation::query())
            ->whereIn('status', [
                ReservationStatus::Pending->value,
                ReservationStatus::Confirmed->value,
                ReservationStatus::CheckedIn->value,
                ReservationStatus::CheckedOut->value,
            ])
            ->leftJoinSub($paid, 'paid_totals', fn ($join) => $join->on('reservations.id', '=', 'paid_totals.reservation_id'));
        return (float) ($query->selectRaw('COALESCE(SUM(CASE WHEN reservations.total_amount > COALESCE(paid_totals.paid_amount, 0) THEN reservations.total_amount - COALESCE(paid_totals.paid_amount, 0) ELSE 0 END), 0) as outstanding')->value('outstanding') ?? 0);
    }

    public function collectedBetween(CarbonInterface|string|null $from = null, CarbonInterface|string|null $to = null): float
    {
        return (float) Payment::query()->successful()
            ->when($from, fn (Builder $query) => $query->whereDate('transaction_date', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('transaction_date', '<=', $to))
            ->sum('amount');
    }

    public function withPaidAmount(Builder|HasMany $query): Builder|HasMany
    {
        return $query->withSum(['payments as paid_amount' => fn (Builder $paymentQuery) => $paymentQuery->successful()], 'amount');
    }

    public function qualifiesForBalance(Reservation $reservation): bool
    {
        return in_array($reservation->status?->value, [
            ReservationStatus::Pending->value,
            ReservationStatus::Confirmed->value,
            ReservationStatus::CheckedIn->value,
            ReservationStatus::CheckedOut->value,
        ], true);
    }
}
