<?php

namespace App\Services;

use App\Models\PosOrder;
use App\Models\PosPayment;
use App\Models\PosProduct;
use App\Models\PosRefund;
use App\Models\PosRoomCharge;
use App\Models\PosShift;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class PosReportService
{
    public function summary(Carbon $from, Carbon $to): array
    {
        $orders = $this->completedOrders($from, $to);
        return [
            'sales' => (float) (clone $orders)->sum('total'),
            'orders' => (int) (clone $orders)->count(),
            'room_charges' => (float) PosRoomCharge::query()->where('status', 'active')->whereBetween('posted_at', [$from, $to])->sum('amount'),
            'discounts' => (float) (clone $orders)->sum('discount_total'),
            'tax' => (float) (clone $orders)->sum('tax_total'),
            'refunds' => (float) PosRefund::query()->whereBetween('refunded_at', [$from, $to])->sum('amount'),
            'by_outlet' => (clone $orders)->selectRaw('outlet_id, SUM(total) as amount, COUNT(*) as orders')->with('outlet')->groupBy('outlet_id')->get(),
            'by_cashier' => (clone $orders)->selectRaw('cashier_id, SUM(total) as amount, COUNT(*) as orders')->with('cashier')->groupBy('cashier_id')->get(),
            'by_payment' => PosPayment::query()->whereHas('order', fn ($query) => $query->where('status', 'completed')->whereBetween('completed_at', [$from, $to]))->selectRaw('method, SUM(amount) as amount, COUNT(*) as payments')->groupBy('method')->orderByDesc('amount')->get(),
            'top_products' => PosProduct::query()->select('pos_products.name')->selectRaw('SUM(pos_order_items.quantity) as quantity, SUM(pos_order_items.total) as amount')->join('pos_order_items', 'pos_products.id', '=', 'pos_order_items.product_id')->join('pos_orders', 'pos_orders.id', '=', 'pos_order_items.order_id')->where('pos_orders.status', 'completed')->whereBetween('pos_orders.completed_at', [$from, $to])->groupBy('pos_products.id', 'pos_products.name')->orderByDesc('quantity')->limit(10)->get(),
        ];
    }

    public function completedOrders(Carbon $from, Carbon $to): Builder
    {
        return PosOrder::query()->where('status', 'completed')->whereBetween('completed_at', [$from, $to]);
    }
}
