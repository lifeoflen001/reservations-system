<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Client;
use App\Models\Payment;
use App\Models\PosOrder;
use App\Models\PosRoomCharge;
use App\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class FinancialService
{
    public function paidAmount(Reservation|int $reservation): float
    {
        if ($reservation instanceof Reservation && $reservation->relationLoaded('payments')) {
            return (float) $reservation->payments
                ->filter(fn (Payment $payment) => $payment->status?->countsTowardsPaid())
                ->sum(fn (Payment $payment) => (float) $payment->amount - (float) $payment->refunds->where('status', 'posted')->sum('amount'));
        }

        $id = $reservation instanceof Reservation ? $reservation->getKey() : $reservation;
        $refunds = \App\Models\PaymentRefund::query()->select('payment_id')->where('status', 'posted')->selectRaw('SUM(amount) as refunded_amount')->groupBy('payment_id');
        return (float) Payment::query()->where('reservation_id', $id)->successful()->leftJoinSub($refunds, 'refund_totals', fn ($join) => $join->on('payments.id', '=', 'refund_totals.payment_id'))->selectRaw('COALESCE(SUM(payments.amount - COALESCE(refund_totals.refunded_amount, 0)), 0) as paid_amount')->value('paid_amount');
    }

    public function balance(Reservation|int $reservation): float
    {
        $model = $reservation instanceof Reservation ? $reservation : Reservation::findOrFail($reservation);
        return max(0, round($this->totalDue($model) - $this->paidAmount($model), 2));
    }

    public function roomChargeAmount(Reservation|int $reservation): float
    {
        $id = $reservation instanceof Reservation ? $reservation->getKey() : $reservation;
        return (float) PosRoomCharge::query()->where('reservation_id', $id)->where('status', 'active')->sum('amount');
    }

    public function totalDue(Reservation|int $reservation): float
    {
        $model = $reservation instanceof Reservation ? $reservation : Reservation::findOrFail($reservation);
        return round((float) $model->total_amount + $this->roomChargeAmount($model), 2);
    }

    public function balanceFromAggregate(Reservation $reservation): float
    {
        return max(0, round((float) $reservation->total_amount + (float) ($reservation->room_charge_amount ?? 0) - (float) ($reservation->paid_amount ?? 0), 2));
    }

    public function clientTotalSpent(Client|int $client): float
    {
        $id = $client instanceof Client ? $client->getKey() : $client;
        return (float) $this->recognizedPaymentsBetween(null, null, $id)->sum('amount')
            + (float) PosOrder::query()->where('client_id', $id)->where('status', 'completed')->sum('total');
    }

    public function outstandingBalance(?Builder $reservations = null): float
    {
        $refunds = \App\Models\PaymentRefund::query()->select('payment_id')->where('status', 'posted')->selectRaw('SUM(amount) as refunded_amount')->groupBy('payment_id');
        $paid = Payment::query()->successful()->leftJoinSub($refunds, 'refund_totals', fn ($join) => $join->on('payments.id', '=', 'refund_totals.payment_id'))
            ->select('reservation_id')
            ->selectRaw('SUM(payments.amount - COALESCE(refund_totals.refunded_amount, 0)) as paid_amount')
            ->groupBy('reservation_id');
        $charges = PosRoomCharge::query()->where('status', 'active')->select('reservation_id')->selectRaw('SUM(amount) as room_charge_amount')->groupBy('reservation_id');
        $query = ($reservations ? clone $reservations : Reservation::query())
            ->whereIn('status', [
                ReservationStatus::Pending->value,
                ReservationStatus::Confirmed->value,
                ReservationStatus::CheckedIn->value,
                ReservationStatus::CheckedOut->value,
            ])
            ->leftJoinSub($paid, 'paid_totals', fn ($join) => $join->on('reservations.id', '=', 'paid_totals.reservation_id'))
            ->leftJoinSub($charges, 'room_charge_totals', fn ($join) => $join->on('reservations.id', '=', 'room_charge_totals.reservation_id'));
        return (float) ($query->selectRaw('COALESCE(SUM(CASE WHEN reservations.total_amount + COALESCE(room_charge_totals.room_charge_amount, 0) > COALESCE(paid_totals.paid_amount, 0) THEN reservations.total_amount + COALESCE(room_charge_totals.room_charge_amount, 0) - COALESCE(paid_totals.paid_amount, 0) ELSE 0 END), 0) as outstanding')->value('outstanding') ?? 0);
    }

    public function collectedBetween(CarbonInterface|string|null $from = null, CarbonInterface|string|null $to = null): float
    {
        return round((float) $this->recognizedPaymentsBetween($from, $to)->sum('amount'), 2);
    }

    /**
     * Return successful reservation payments recognized as accommodation revenue.
     *
     * POS room charges are recognized when the POS order is completed. A later
     * PMS payment can settle that charge, but only the portion allocated to the
     * reservation's accommodation total is revenue here.
     */
    public function recognizedPaymentsBetween(CarbonInterface|string|null $from = null, CarbonInterface|string|null $to = null, ?int $clientId = null): Collection
    {
        $fromDate = $from ? \Carbon\Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? \Carbon\Carbon::parse($to)->endOfDay() : null;
        $remaining = [];

        return Payment::query()->successful()
            ->when($clientId, fn (Builder $query) => $query->where('client_id', $clientId))
            ->with(['reservation:id,total_amount,room_id', 'refunds'])
            ->orderBy('reservation_id')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->map(function (Payment $payment) use (&$remaining, $fromDate, $toDate): ?array {
                $reservationId = $payment->reservation_id;
                $amount = max(0, (float) $payment->amount - (float) $payment->refunds->where('status', 'posted')->sum('amount'));
                if ($reservationId !== null) {
                    if (! array_key_exists($reservationId, $remaining)) {
                        $remaining[$reservationId] = (float) ($payment->reservation?->total_amount ?? 0);
                    }
                    $recognized = min($amount, max(0, $remaining[$reservationId]));
                    $remaining[$reservationId] = round($remaining[$reservationId] - $recognized, 2);
                } else {
                    $recognized = $amount;
                }

                $date = $payment->transaction_date;
                if ($fromDate && $date?->lt($fromDate)) return null;
                if ($toDate && $date?->gt($toDate)) return null;
                if ($recognized <= 0) return null;
                return ['payment' => $payment, 'amount' => round($recognized, 2)];
            })
            ->filter()
            ->values();
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
