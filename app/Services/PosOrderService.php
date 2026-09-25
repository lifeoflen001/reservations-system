<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\PosAudit;
use App\Models\PosOrder;
use App\Models\PosPayment;
use App\Models\PosProduct;
use App\Models\PosRefund;
use App\Models\PosRoomCharge;
use App\Models\PosShift;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PosOrderService
{
    public function __construct(private readonly FinanceService $finance) {}

    public function checkout(array $data, User $actor): PosOrder
    {
        return DB::transaction(function () use ($data, $actor): PosOrder {
            if (! empty($data['idempotency_key']) && ($existing = PosOrder::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first())) {
                return $existing->load(['items', 'payments', 'roomCharge', 'outlet']);
            }

            $outletId = (int) $data['outlet_id'];
            $outlet = DB::table('pos_outlets')->where('id', $outletId)->where('is_active', true)->lockForUpdate()->first();
            if (! $outlet) throw new InvalidArgumentException('The selected outlet is no longer active.');

            $shift = null;
            if (! empty($data['shift_id'])) {
                $shift = PosShift::query()->whereKey($data['shift_id'])->lockForUpdate()->first();
                if (! $shift || $shift->status !== 'open' || ($shift->cashier_id !== $actor->getKey() && ! $actor->hasPermission('pos.shifts.view_all'))) throw new InvalidArgumentException('The selected shift is not available.');
                if ($shift->outlet_id !== $outletId) throw new InvalidArgumentException('The shift does not belong to this outlet.');
            } elseif (config('hotel.pos.require_shift')) {
                $shift = PosShift::query()->where('outlet_id', $outletId)->where('cashier_id', $actor->getKey())->where('status', 'open')->lockForUpdate()->first();
                if (! $shift) throw new InvalidArgumentException('Open a cashier shift before completing a sale.');
            }

            $items = $this->calculateItems($data['items'] ?? [], $outletId);
            if ($items['lines']->isEmpty()) throw new InvalidArgumentException('Add at least one active product to the order.');
            $discount = $this->calculateDiscount($items['subtotal'], $data, $actor);
            $tax = $items['tax'];
            $total = round(max(0, $items['subtotal'] - $discount + $tax), 2);

            $reservation = null;
            if (! empty($data['reservation_id'])) {
                $reservation = Reservation::query()->with('client')->whereKey($data['reservation_id'])->lockForUpdate()->first();
                if (! $reservation || $reservation->status !== ReservationStatus::CheckedIn) throw new InvalidArgumentException('This room is no longer checked in.');
                if (! empty($data['room_id']) && (int) $data['room_id'] !== $reservation->room_id) throw new InvalidArgumentException('The selected room does not match the reservation.');
                if (! empty($data['client_id']) && (int) $data['client_id'] !== $reservation->client_id) throw new InvalidArgumentException('The selected guest does not match the reservation.');
            }

            $payments = $this->normalizePayments($data['payments'] ?? [], $total, $reservation, $actor);
            $order = PosOrder::create(['order_number' => $this->nextOrderNumber(), 'idempotency_key' => $data['idempotency_key'] ?? null, 'outlet_id' => $outletId, 'cashier_id' => $actor->getKey(), 'shift_id' => $shift?->getKey(), 'client_id' => $reservation?->client_id ?: ($data['client_id'] ?? null), 'reservation_id' => $reservation?->getKey(), 'room_id' => $reservation?->room_id ?: ($data['room_id'] ?? null), 'status' => 'completed', 'subtotal' => $items['subtotal'], 'discount_total' => $discount, 'tax_total' => $tax, 'total' => $total, 'notes' => $data['notes'] ?? null, 'completed_at' => now()]);
            foreach ($items['lines'] as $line) {
                $product = $line['product'];
                $order->items()->create(['product_id' => $product->getKey(), 'product_name_snapshot' => $product->name, 'sku_snapshot' => $product->sku, 'quantity' => $line['quantity'], 'unit_price' => $line['unit_price'], 'discount' => $line['discount'], 'tax' => $line['tax'], 'total' => $line['total'], 'note' => $line['note']]);
                if ($product->track_stock) $product->decrement('stock_quantity', $line['quantity']);
            }
            foreach ($payments as $payment) $order->payments()->create($payment + ['created_by' => $actor->getKey(), 'paid_at' => now(), 'status' => 'paid']);
            if (collect($payments)->contains(fn (array $payment) => $payment['method'] === 'charge_to_room')) {
                if (! $reservation || ! $actor->hasPermission('pos.charge_room')) throw new InvalidArgumentException('Room charging is not permitted for this sale.');
                $order->roomCharge()->create(['reservation_id' => $reservation->getKey(), 'client_id' => $reservation->client_id, 'room_id' => $reservation->room_id, 'amount' => $total, 'status' => 'active', 'posted_at' => now()]);
            }
            PosAudit::create(['event' => 'sale.completed', 'actor_id' => $actor->getKey(), 'order_id' => $order->getKey(), 'shift_id' => $shift?->getKey(), 'metadata' => ['total' => $total, 'payment_methods' => collect($payments)->pluck('method')->all()]]);
            $this->finance->postPosOrder($order->fresh(['payments']), $actor->getKey());
            return $order->load(['items', 'payments', 'roomCharge', 'outlet', 'client', 'reservation.room']);
        });
    }

    public function void(PosOrder $order, User $actor, string $reason): PosOrder
    {
        return DB::transaction(function () use ($order, $actor, $reason): PosOrder {
            $order = PosOrder::query()->with(['items.product', 'roomCharge', 'payments'])->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if ($order->status === 'voided') throw new InvalidArgumentException('This transaction has already been voided.');
            if ($order->status !== 'completed') throw new InvalidArgumentException('Only completed sales can be voided.');
            $order->update(['status' => 'voided', 'voided_by' => $actor->getKey(), 'voided_at' => now(), 'void_reason' => $reason]);
            $order->payments()->update(['status' => 'voided']);
            $this->restoreStock($order);
            if ($order->roomCharge) $order->roomCharge->update(['status' => 'voided', 'voided_by' => $actor->getKey(), 'voided_at' => now(), 'void_reason' => $reason]);
            foreach ($order->payments as $payment) $this->finance->reverseSource(PosPayment::class, $payment->getKey(), $actor->getKey(), 'POS sale voided: '.$reason);
            PosAudit::create(['event' => 'sale.voided', 'actor_id' => $actor->getKey(), 'order_id' => $order->getKey(), 'metadata' => ['reason' => $reason]]);
            return $order->fresh(['items', 'payments', 'roomCharge', 'outlet', 'cashier']);
        });
    }

    public function refund(PosOrder $order, User $actor, float $amount, string $reason): PosOrder
    {
        return DB::transaction(function () use ($order, $actor, $amount, $reason): PosOrder {
            $order = PosOrder::query()->with(['items.product', 'roomCharge', 'refunds'])->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if ($order->status !== 'completed' && $order->status !== 'refunded') throw new InvalidArgumentException('Only completed sales can be refunded.');
            $refunded = (float) $order->refunds->sum('amount');
            $amount = round($amount, 2);
            if ($amount <= 0 || $amount > (float) $order->total - $refunded) throw new InvalidArgumentException('Refund amount exceeds the remaining refundable balance.');
            $refund = $order->refunds()->create(['amount' => $amount, 'reason' => $reason, 'refunded_by' => $actor->getKey(), 'approved_by' => $actor->hasPermission('pos.manage') ? $actor->getKey() : null, 'refunded_at' => now()]);
            $this->finance->postPosRefund($order, $refund, $actor->getKey());
            if (round($amount + $refunded, 2) >= (float) $order->total) {
                $order->update(['status' => 'refunded']);
                $this->restoreStock($order);
                if ($order->roomCharge) $order->roomCharge->update(['status' => 'refunded']);
            }
            PosAudit::create(['event' => 'sale.refunded', 'actor_id' => $actor->getKey(), 'order_id' => $order->getKey(), 'metadata' => ['amount' => $amount, 'reason' => $reason]]);
            return $order->fresh(['items', 'payments', 'refunds', 'roomCharge']);
        });
    }

    private function calculateItems(array $rawItems, int $outletId): array
    {
        $lines = collect(); $subtotal = 0.0; $tax = 0.0;
        foreach ($rawItems as $raw) {
            $productId = (int) ($raw['product_id'] ?? 0); $quantity = round((float) ($raw['quantity'] ?? 0), 3);
            if ($productId <= 0 || $quantity <= 0) continue;
            $product = PosProduct::query()->whereKey($productId)->where('is_active', true)->where(fn ($query) => $query->whereNull('outlet_id')->orWhere('outlet_id', $outletId))->lockForUpdate()->first();
            if (! $product) throw new InvalidArgumentException('One of the selected products is no longer available.');
            if ($product->track_stock && (float) $product->stock_quantity < $quantity) throw new InvalidArgumentException('Insufficient stock for '.$product->name.'.');
            $base = round((float) $product->selling_price * $quantity, 2);
            $lineTax = $product->tax_inclusive ? round($base - ($base / (1 + ((float) $product->tax_rate / 100))), 2) : round($base * ((float) $product->tax_rate / 100), 2);
            $lineTotal = $product->tax_inclusive ? $base : round($base + $lineTax, 2);
            $subtotal += $base; $tax += $lineTax;
            $lines->push(['product' => $product, 'quantity' => $quantity, 'unit_price' => (float) $product->selling_price, 'discount' => 0, 'tax' => $lineTax, 'total' => $lineTotal, 'note' => $raw['note'] ?? null]);
        }
        return ['lines' => $lines, 'subtotal' => round($subtotal, 2), 'tax' => round($tax, 2)];
    }

    private function calculateDiscount(float $subtotal, array $data, User $actor): float
    {
        $value = round((float) ($data['discount_value'] ?? 0), 2);
        if ($value <= 0) return 0;
        if (! $actor->hasPermission('pos.discount')) throw new InvalidArgumentException('You are not authorized to apply discounts.');
        $discount = ($data['discount_type'] ?? 'fixed') === 'percentage' ? round($subtotal * min(100, $value) / 100, 2) : $value;
        return min($subtotal, max(0, $discount));
    }

    private function normalizePayments(array $rawPayments, float $total, ?Reservation $reservation, User $actor): array
    {
        $payments = collect($rawPayments)->map(fn ($payment) => ['method' => strtolower(trim((string) ($payment['method'] ?? ''))), 'amount' => round((float) ($payment['amount'] ?? 0), 2), 'reference' => $payment['reference'] ?? null])->filter(fn (array $payment) => $payment['amount'] > 0)->values();
        if ($payments->isEmpty()) throw new InvalidArgumentException('Select a payment method.');
        $validMethods = PaymentMethod::query()->where('is_active', true)->whereIn('code', $payments->pluck('method'))->pluck('code');
        if ($payments->contains(fn (array $payment) => ! $validMethods->contains($payment['method']))) throw new InvalidArgumentException('One of the selected payment methods is not configured or active.');
        if (abs((float) $payments->sum('amount') - $total) > 0.009) throw new InvalidArgumentException('Payment amounts must equal the sale total.');
        if ($payments->contains(fn (array $payment) => $payment['method'] === 'charge_to_room') && ($payments->count() !== 1 || ! $reservation)) throw new InvalidArgumentException('Charge to room must be the only payment method and requires an in-house guest.');
        return $payments->all();
    }

    private function restoreStock(PosOrder $order): void
    {
        foreach ($order->items as $item) if ($item->product?->track_stock) $item->product->increment('stock_quantity', $item->quantity);
    }

    private function nextOrderNumber(): string
    {
        $sequence = DB::table('pos_order_sequences')->where('id', 1)->lockForUpdate()->first();
        if (! $sequence) { DB::table('pos_order_sequences')->insert(['id' => 1, 'next_number' => 2, 'created_at' => now(), 'updated_at' => now()]); return 'POS-000001'; }
        DB::table('pos_order_sequences')->where('id', 1)->update(['next_number' => $sequence->next_number + 1, 'updated_at' => now()]);
        return 'POS-'.str_pad((string) $sequence->next_number, 6, '0', STR_PAD_LEFT);
    }
}
