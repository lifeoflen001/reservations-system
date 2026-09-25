<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Events\PaymentReceived;
use App\Events\PaymentVoided;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(private readonly FinancialService $financials, private readonly FinanceService $finance) {}

    public function post(Reservation|int $reservation, array $attributes, ?int $createdBy = null): Payment
    {
        $payment = DB::transaction(function () use ($reservation, $attributes, $createdBy) {
            $reservation = Reservation::query()
                ->whereKey($reservation instanceof Reservation ? $reservation->getKey() : $reservation)
                ->lockForUpdate()
                ->firstOrFail();
            $rawStatus = $attributes['status'] ?? PaymentStatus::Paid;
            $status = $rawStatus instanceof PaymentStatus
                ? $rawStatus
                : PaymentStatus::from((string) $rawStatus);
            $amount = round((float) ($attributes['amount'] ?? 0), 2);

            if (! in_array($status, [PaymentStatus::Paid, PaymentStatus::Pending], true)) {
                throw new InvalidArgumentException('New payments must be Paid or Pending.');
            }
            if ($amount <= 0) {
                throw new InvalidArgumentException('Payment amount must be greater than zero.');
            }
            $committedAmount = $this->financials->paidAmount($reservation) + (float) Payment::query()
                ->where('reservation_id', $reservation->getKey())
                ->where('status', PaymentStatus::Pending->value)
                ->sum('amount');
            if ($amount + $committedAmount > app(FinancialService::class)->totalDue($reservation)) {
                throw new InvalidArgumentException('Payment amount cannot exceed the outstanding reservation balance.');
            }

            $invoiceNumber = $this->nextInvoiceNumber();
            $payment = Payment::create([
                'invoice_number' => $invoiceNumber,
                'reservation_id' => $reservation->getKey(),
                'client_id' => $reservation->client_id,
                'created_by' => $createdBy,
                'amount' => $amount,
                'method' => (string) $attributes['method'],
                'reference' => null,
                'transaction_date' => $attributes['transaction_date'] ?? now(),
                'notes' => $attributes['notes'] ?? null,
                'status' => $status,
            ]);

            $reference = $attributes['reference'] ?? $this->generatedReference($reservation, $payment);
            $payment->update(['reference' => $reference]);
            $payment->invoice()->create([
                'invoice_number' => $invoiceNumber,
                'issue_date' => $payment->transaction_date,
                'status' => $status->value,
                'created_by' => $createdBy,
            ]);
            $this->finance->postPayment($payment, $createdBy);

            return $payment->load(['invoice', 'reservation.client', 'reservation.room.roomType', 'creator']);
        });
        if ($payment->status === PaymentStatus::Paid) {
            event(new PaymentReceived($payment));
        }

        return $payment;
    }

    public function update(Payment $payment, array $attributes, User $actor): Payment
    {
        $wasPending = $payment->status === PaymentStatus::Pending;
        $payment = DB::transaction(function () use ($payment, $attributes, $actor) {
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $data = ['updated_by' => $actor->getKey()];

            if ($payment->status === PaymentStatus::Pending) {
                $reservation = Reservation::query()->whereKey($payment->reservation_id)->lockForUpdate()->firstOrFail();
                $status = isset($attributes['status']) ? ($attributes['status'] instanceof PaymentStatus ? $attributes['status'] : PaymentStatus::from((string) $attributes['status'])) : PaymentStatus::Pending;
                if (! in_array($status, [PaymentStatus::Pending, PaymentStatus::Paid], true)) {
                    throw new InvalidArgumentException('Pending payments can only be kept pending or marked paid.');
                }
                $amount = array_key_exists('amount', $attributes) ? round((float) $attributes['amount'], 2) : (float) $payment->amount;
                $committedExcludingPayment = (float) Payment::query()
                    ->where('reservation_id', $reservation->getKey())
                    ->where('id', '!=', $payment->getKey())
                    ->whereIn('status', [PaymentStatus::Paid->value, PaymentStatus::Pending->value])
                    ->sum('amount');
                if ($amount <= 0 || $amount + $committedExcludingPayment > app(FinancialService::class)->totalDue($reservation)) {
                    throw new InvalidArgumentException('Payment amount cannot exceed the outstanding reservation balance.');
                }
                $data['amount'] = $amount;
                $data['method'] = $attributes['method'] ?? $payment->method;
                $data['transaction_date'] = $attributes['transaction_date'] ?? $payment->transaction_date;
                $data['status'] = $status;
            }

            $data['reference'] = $attributes['reference'] ?? $payment->reference;
            $data['notes'] = $attributes['notes'] ?? $payment->notes;
            $payment->update($data);
            $payment->invoice?->update(['issue_date' => $payment->transaction_date, 'status' => $payment->status->value, 'updated_by' => $actor->getKey()]);

            return $payment->fresh(['invoice', 'reservation.client', 'reservation.room.roomType', 'creator', 'updater']);
        });

        if ($wasPending && $payment->status === PaymentStatus::Paid) {
            event(new PaymentReceived($payment));
            $this->finance->postPayment($payment, $actor->getKey());
        }

        return $payment;
    }

    public function void(Payment $payment, User $actor, string $reason): Payment
    {
        $payment = DB::transaction(function () use ($payment, $actor, $reason) {
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            if ($payment->status === PaymentStatus::Voided) {
                throw new InvalidArgumentException('This payment is already voided.');
            }
            if ($payment->status === PaymentStatus::Refunded) {
                throw new InvalidArgumentException('Refunded payments cannot be voided.');
            }

            $payment->update([
                'status' => PaymentStatus::Voided,
                'updated_by' => $actor->getKey(),
                'voided_by' => $actor->getKey(),
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);
            $payment->invoice?->update(['status' => PaymentStatus::Voided->value, 'updated_by' => $actor->getKey()]);

            return $payment->fresh(['invoice', 'reservation.client', 'creator', 'voider']);
        });
        $this->finance->reverseSource(Payment::class, $payment->getKey(), $actor->getKey(), 'Payment voided: '.$reason);
        event(new PaymentVoided($payment));

        return $payment;
    }

    private function nextInvoiceNumber(): string
    {
        $sequence = DB::table('invoice_sequences')->where('id', 1)->lockForUpdate()->first();
        if (! $sequence) {
            DB::table('invoice_sequences')->insert(['id' => 1, 'next_number' => 2, 'created_at' => now(), 'updated_at' => now()]);

            return 'INV-00001';
        }

        DB::table('invoice_sequences')->where('id', 1)->update(['next_number' => $sequence->next_number + 1, 'updated_at' => now()]);

        return 'INV-'.str_pad((string) $sequence->next_number, 5, '0', STR_PAD_LEFT);
    }

    private function generatedReference(Reservation $reservation, Payment $payment): string
    {
        $base = 'PAY-'.strtoupper($reservation->code);
        $reference = $base;
        if (Payment::query()->where('reference', $reference)->exists()) {
            $reference = $base.'-'.$payment->getKey();
        }

        return Str::limit($reference, 100, '');
    }
}
