<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\Payment;
use App\Models\PosOrder;
use App\Services\FinanceService;
use Illuminate\Database\Seeder;

class FinanceReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $currency = (string) config('app.currency', 'USD');
        foreach ([
            ['name' => 'Cash on hand', 'code' => 'cash', 'type' => 'cash'],
            ['name' => 'Bank account', 'code' => 'bank', 'type' => 'bank'],
            ['name' => 'Mobile money', 'code' => 'mobile_money', 'type' => 'mobile_money'],
            ['name' => 'Card clearing', 'code' => 'card_clearing', 'type' => 'card_clearing'],
            ['name' => 'Petty cash', 'code' => 'petty_cash', 'type' => 'petty_cash'],
        ] as $account) {
            FinancialAccount::firstOrCreate(['code' => $account['code']], $account + ['currency' => $currency, 'is_active' => true]);
        }

        foreach (['Maintenance', 'Utilities', 'Food & Beverage', 'Housekeeping', 'Transport', 'Fuel', 'Staff', 'Marketing', 'Supplies', 'IT', 'Administration', 'Taxes / Fees', 'Miscellaneous'] as $name) {
            ExpenseCategory::firstOrCreate(['code' => str($name)->slug('_')->toString()], ['name' => $name, 'is_active' => true]);
        }

        // Backfill only source-linked entries that do not already exist. This is
        // safe for populated installations and never changes payment/POS rows.
        Payment::query()->successful()->each(fn (Payment $payment) => app(FinanceService::class)->postPayment($payment, $payment->created_by));
        PosOrder::query()->where('status', 'completed')->with('payments')->each(fn (PosOrder $order) => app(FinanceService::class)->postPosOrder($order, $order->cashier_id));
    }
}
