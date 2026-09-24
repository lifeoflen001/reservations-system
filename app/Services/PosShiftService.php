<?php

namespace App\Services;

use App\Models\PosAudit;
use App\Models\PosOutlet;
use App\Models\PosPayment;
use App\Models\PosShift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PosShiftService
{
    public function open(PosOutlet|int $outlet, User $cashier, float $openingCash): PosShift
    {
        return DB::transaction(function () use ($outlet, $cashier, $openingCash): PosShift {
            $outletId = $outlet instanceof PosOutlet ? $outlet->getKey() : $outlet;
            if (PosShift::query()->where('outlet_id', $outletId)->where('cashier_id', $cashier->getKey())->where('status', 'open')->lockForUpdate()->exists()) {
                throw new InvalidArgumentException('You already have an open shift for this outlet.');
            }
            if ($openingCash < 0) {
                throw new InvalidArgumentException('Opening cash cannot be negative.');
            }
            $shift = PosShift::create(['outlet_id' => $outletId, 'cashier_id' => $cashier->getKey(), 'status' => 'open', 'opening_cash' => round($openingCash, 2), 'opened_at' => now()]);
            PosAudit::create(['event' => 'shift.opened', 'actor_id' => $cashier->getKey(), 'shift_id' => $shift->getKey(), 'metadata' => ['opening_cash' => $openingCash]]);
            return $shift->load(['outlet', 'cashier']);
        });
    }

    public function close(PosShift $shift, User $cashier, float $actualCash, ?string $notes = null): PosShift
    {
        return DB::transaction(function () use ($shift, $cashier, $actualCash, $notes): PosShift {
            $shift = PosShift::query()->whereKey($shift->getKey())->lockForUpdate()->firstOrFail();
            if ($shift->status !== 'open') {
                throw new InvalidArgumentException('This shift is already closed.');
            }
            if (! $cashier->hasPermission('pos.shifts.view_all') && $shift->cashier_id !== $cashier->getKey()) {
                throw new InvalidArgumentException('You can only close your own shift.');
            }
            if ($actualCash < 0) {
                throw new InvalidArgumentException('Actual cash cannot be negative.');
            }
            $cashSales = (float) PosPayment::query()->whereHas('order', fn ($query) => $query->where('shift_id', $shift->getKey())->where('status', 'completed'))->where('method', 'cash')->where('status', 'paid')->sum('amount');
            $expected = round((float) $shift->opening_cash + $cashSales, 2);
            $shift->update(['status' => 'closed', 'cash_sales' => $cashSales, 'expected_cash' => $expected, 'actual_cash' => round($actualCash, 2), 'variance' => round($actualCash - $expected, 2), 'closed_at' => now(), 'notes' => $notes]);
            PosAudit::create(['event' => 'shift.closed', 'actor_id' => $cashier->getKey(), 'shift_id' => $shift->getKey(), 'metadata' => ['cash_sales' => $cashSales, 'expected_cash' => $expected, 'actual_cash' => $actualCash, 'variance' => $actualCash - $expected]]);
            return $shift->fresh(['outlet', 'cashier']);
        });
    }
}
