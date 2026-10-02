<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PosOrder;
use App\Models\PosOutlet;
use App\Models\PosProduct;
use App\Models\Reservation;
use App\Models\User;
use App\Services\FinancialService;
use App\Services\FinanceService;
use App\Services\HotelAnalyticsService;
use App\Services\PaymentService;
use App\Services\PosOrderService;
use App\Services\PosReportService;
use App\Services\ReservationService;
use App\Services\RoomAvailabilityService;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ApiController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly FinancialService $financials,
        private readonly FinanceService $finance,
        private readonly PaymentService $payments,
        private readonly PosOrderService $posOrders,
        private readonly PosReportService $posReports,
        private readonly HotelAnalyticsService $analytics,
        private readonly RoomAvailabilityService $availability,
    ) {}

    public function rooms(Request $request): JsonResponse
    {
        $this->scope($request, 'rooms:read');
        $rooms = \App\Models\Room::query()->with(['roomType', 'floor'])->where('is_active', true)->paginate($this->perPage($request));

        return $this->page($rooms, fn ($room) => [
            'id' => $room->id,
            'room_number' => $room->room_number,
            'room_type' => $room->roomType?->name,
            'floor' => $room->floor?->name,
            'capacity' => $room->capacity,
            'status' => $room->operational_status?->value,
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $this->scope($request, 'availability:read');
        $data = $request->validate(['check_in' => ['required', 'date'], 'check_out' => ['required', 'date', 'after:check_in']]);
        $rooms = $this->availability->findAvailableRooms(Carbon::parse($data['check_in']), Carbon::parse($data['check_out']));

        return response()->json(['data' => $rooms->map(fn ($room) => [
            'id' => $room->id,
            'room_number' => $room->room_number,
            'room_type' => $room->roomType?->name,
            'rate' => (float) $room->base_rate,
            'capacity' => $room->capacity,
        ])->values()]);
    }

    public function reservation(Request $request, Reservation $reservation): JsonResponse
    {
        $this->scope($request, 'reservations:read');
        Gate::forUser($request->user())->authorize('view', $reservation);
        $reservation->load(['client', 'room.roomType', 'source']);

        return response()->json(['data' => $this->reservationData($reservation)]);
    }

    public function storeReservation(Request $request): JsonResponse
    {
        $this->scope($request, 'reservations:write');
        Gate::forUser($request->user())->authorize('create', Reservation::class);
        $data = $request->validate([
            'client_id' => ['required', 'integer', $this->organizationExists('clients')],
            'room_id' => ['required', 'integer', $this->propertyExists('rooms')],
            'reservation_source_id' => ['nullable', 'integer', 'exists:reservation_sources,id'],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:pending,confirmed'],
            'nightly_rate' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $reservation = $this->reservations->create($data, $request->user()->id);

        return response()->json(['data' => ['id' => $reservation->id, 'code' => $reservation->code, 'status' => $reservation->status?->value]], 201);
    }

    public function client(Request $request, Client $client): JsonResponse
    {
        $this->scope($request, 'clients:read');
        Gate::forUser($request->user())->authorize('view', $client);

        return response()->json(['data' => [
            'id' => $client->id,
            'name' => $client->full_name,
            'email' => $client->email,
            'phone' => $client->phone,
            'country' => $client->country,
            'city' => $client->city,
        ]]);
    }

    public function payments(Request $request): JsonResponse
    {
        $this->scope($request, 'payments:read');
        Gate::forUser($request->user())->authorize('viewAny', Payment::class);
        $query = Payment::query()->with(['reservation.client', 'reservation.room', 'invoice', 'refunds'])
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $like = '%'.trim((string) $request->input('search')).'%';
                $query->where(fn (Builder $nested) => $nested->where('invoice_number', 'like', $like)->orWhere('reference', 'like', $like)->orWhereHas('reservation', fn (Builder $reservation) => $reservation->where('code', 'like', $like)));
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('transaction_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('transaction_date', '<=', $request->date('to')))
            ->latest('transaction_date')->latest('id')->paginate($this->perPage($request));

        return $this->page($query, fn (Payment $payment) => $this->paymentData($payment));
    }

    public function payment(Request $request, Payment $payment): JsonResponse
    {
        $this->scope($request, 'payments:read');
        Gate::forUser($request->user())->authorize('view', $payment);
        $payment->load(['invoice', 'reservation.client', 'reservation.room', 'refunds']);

        return response()->json(['data' => $this->paymentData($payment)]);
    }

    public function storePayment(Request $request): JsonResponse
    {
        $this->scope($request, 'payments:write');
        Gate::forUser($request->user())->authorize('create', Payment::class);
        $data = $request->validate([
            'reservation_id' => ['required', 'integer', $this->propertyExists('reservations')],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'method' => ['required', 'string', 'exists:payment_methods,code'],
            'reference' => ['required', 'string', 'max:100'],
            'transaction_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $existing = Payment::query()->with(['invoice', 'reservation.client', 'reservation.room', 'refunds'])->where('reference', $data['reference'])->first();
        if ($existing) {
            if ((int) $existing->reservation_id !== (int) $data['reservation_id'] || round((float) $existing->amount, 2) !== round((float) $data['amount'], 2) || $existing->method !== $data['method']) {
                return response()->json(['message' => 'The payment reference is already attached to different payment data.'], 409);
            }

            return response()->json(['data' => $this->paymentData($existing), 'meta' => ['idempotent_replay' => true]]);
        }
        try {
            $payment = $this->payments->post((int) $data['reservation_id'], $data + ['status' => PaymentStatus::Pending->value], $request->user()->id);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->paymentData($payment), 'meta' => ['state_transition' => 'initiated -> pending']], 201);
    }

    public function confirmPayment(Request $request, Payment $payment): JsonResponse
    {
        $this->scope($request, 'payments:confirm');
        Gate::forUser($request->user())->authorize('update', $payment);
        $data = $request->validate(['provider_reference' => ['nullable', 'string', 'max:100']]);
        if ($payment->status !== PaymentStatus::Pending) {
            return response()->json(['data' => $this->paymentData($payment), 'meta' => ['idempotent_replay' => true]]);
        }
        try {
            $payment = $this->payments->update($payment, ['status' => PaymentStatus::Paid->value, 'reference' => $data['provider_reference'] ?? $payment->reference], $request->user());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->paymentData($payment), 'meta' => ['state_transition' => 'pending -> confirmed']]);
    }

    public function voidPayment(Request $request, Payment $payment): JsonResponse
    {
        $this->scope($request, 'payments:manage');
        Gate::forUser($request->user())->authorize('void', $payment);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        try {
            $payment = $this->payments->void($payment, $request->user(), $data['reason']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->paymentData($payment)]);
    }

    public function refundPayment(Request $request, Payment $payment): JsonResponse
    {
        $this->scope($request, 'payments:manage');
        Gate::forUser($request->user())->authorize('refund', $payment);
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'], 'method' => ['required', 'string', 'max:60'], 'reason' => ['required', 'string', 'max:2000']]);
        try {
            $refund = $this->payments->refund($payment, $data, $request->user());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->paymentData($refund->payment->load(['invoice', 'reservation.client', 'reservation.room', 'refunds'])), 'refund' => ['id' => $refund->id, 'amount' => (float) $refund->amount, 'method' => $refund->method, 'reference' => $refund->refund_reference, 'status' => $refund->status]]);
    }

    public function posOutlets(Request $request): JsonResponse
    {
        $this->scope($request, 'pos:read');
        Gate::forUser($request->user())->authorize('viewAny', PosOrder::class);

        return response()->json(['data' => PosOutlet::query()->where('is_active', true)->withCount('products')->orderBy('name')->get()->map(fn (PosOutlet $outlet) => ['id' => $outlet->id, 'name' => $outlet->name, 'code' => $outlet->code, 'location' => $outlet->location, 'product_count' => $outlet->products_count])->values()]);
    }

    public function posProducts(Request $request): JsonResponse
    {
        $this->scope($request, 'pos:read');
        Gate::forUser($request->user())->authorize('viewAny', PosProduct::class);
        $products = PosProduct::query()->with('category')->where('is_active', true)
            ->when($request->filled('outlet_id'), fn (Builder $query) => $query->where(fn (Builder $nested) => $nested->whereNull('outlet_id')->orWhere('outlet_id', $request->integer('outlet_id'))))
            ->when($request->filled('category_id'), fn (Builder $query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $like = '%'.trim((string) $request->input('search')).'%';
                $query->where(fn (Builder $nested) => $nested->where('name', 'like', $like)->orWhere('sku', 'like', $like));
            })->orderBy('name')->paginate($this->perPage($request));

        return $this->page($products, fn (PosProduct $product) => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'category' => $product->category?->name, 'selling_price' => (float) $product->selling_price, 'tax_rate' => (float) $product->tax_rate, 'tax_inclusive' => $product->tax_inclusive, 'track_stock' => $product->track_stock, 'stock_quantity' => (float) $product->stock_quantity]);
    }

    public function posOrders(Request $request): JsonResponse
    {
        $this->scope($request, 'pos:read');
        Gate::forUser($request->user())->authorize('viewAny', PosOrder::class);
        $query = PosOrder::query()->with(['outlet', 'cashier', 'client', 'room', 'payments', 'roomCharge', 'refunds'])
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $like = '%'.trim((string) $request->input('search')).'%';
                $query->where(fn (Builder $nested) => $nested->where('order_number', 'like', $like)->orWhereHas('client', fn (Builder $client) => $client->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like))->orWhereHas('room', fn (Builder $room) => $room->where('room_number', 'like', $like)));
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('outlet_id'), fn (Builder $query) => $query->where('outlet_id', $request->integer('outlet_id')))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('completed_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('completed_at', '<=', $request->date('to')));
        if (! $request->user()->hasPermission('pos.view_all_orders')) $query->where('cashier_id', $request->user()->id);

        return $this->page($query->latest('completed_at')->latest('id')->paginate($this->perPage($request)), fn (PosOrder $order) => $this->posOrderData($order));
    }

    public function posOrder(Request $request, PosOrder $order): JsonResponse
    {
        $this->scope($request, 'pos:read');
        Gate::forUser($request->user())->authorize('view', $order);
        $order->load(['items', 'payments', 'roomCharge.reservation', 'outlet', 'cashier', 'client', 'room', 'refunds']);

        return response()->json(['data' => $this->posOrderData($order)]);
    }

    public function storePosOrder(Request $request): JsonResponse
    {
        $this->scope($request, 'pos:write');
        Gate::forUser($request->user())->authorize('create', PosOrder::class);
        $request->merge(['idempotency_key' => $request->input('idempotency_key') ?: $request->header('Idempotency-Key')]);
        $data = $request->validate([
            'outlet_id' => ['required', 'integer', $this->propertyExists('pos_outlets')],
            'shift_id' => ['nullable', 'integer', $this->propertyExists('pos_shifts')],
            'reservation_id' => ['nullable', 'integer', $this->propertyExists('reservations')],
            'client_id' => ['nullable', 'integer', $this->organizationExists('clients')],
            'room_id' => ['nullable', 'integer', $this->propertyExists('rooms')],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', $this->propertyExists('pos_products')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', 'max:60'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:80'],
        ]);
        $replay = PosOrder::query()->where('idempotency_key', $data['idempotency_key'])->exists();
        try {
            $order = $this->posOrders->checkout($data, $request->user());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->posOrderData($order), 'meta' => ['idempotent_replay' => $replay]], $replay ? 200 : 201);
    }

    public function voidPosOrder(Request $request, PosOrder $order): JsonResponse
    {
        $this->scope($request, 'pos:manage');
        Gate::forUser($request->user())->authorize('void', $order);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        try {
            $order = $this->posOrders->void($order, $request->user(), $data['reason']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->posOrderData($order)]);
    }

    public function refundPosOrder(Request $request, PosOrder $order): JsonResponse
    {
        $this->scope($request, 'pos:manage');
        Gate::forUser($request->user())->authorize('refund', $order);
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'], 'reason' => ['required', 'string', 'max:1000']]);
        try {
            $order = $this->posOrders->refund($order, $request->user(), (float) $data['amount'], $data['reason']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->posOrderData($order)]);
    }

    public function posReport(Request $request): JsonResponse
    {
        $this->scope($request, 'pos:read');
        Gate::forUser($request->user())->authorize('pos.reports.view');
        [$from, $to] = $this->range($request);
        $report = $this->posReports->summary($from, $to);

        return response()->json(['data' => [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'sales' => (float) $report['sales'], 'orders' => (int) $report['orders'],
            'room_charges' => (float) $report['room_charges'], 'discounts' => (float) $report['discounts'],
            'tax' => (float) $report['tax'], 'refunds' => (float) $report['refunds'],
            'by_outlet' => collect($report['by_outlet'])->map(fn ($row) => ['outlet_id' => $row->outlet_id, 'outlet' => $row->outlet?->name, 'orders' => (int) $row->orders, 'amount' => (float) $row->amount])->values(),
            'by_payment' => collect($report['by_payment'])->map(fn ($row) => ['method' => $row->method, 'payments' => (int) $row->payments, 'amount' => (float) $row->amount])->values(),
            'top_products' => collect($report['top_products'])->map(fn ($row) => ['name' => $row->name, 'quantity' => (float) $row->quantity, 'amount' => (float) $row->amount])->values(),
        ]]);
    }

    public function financeAccounts(Request $request): JsonResponse
    {
        $this->scope($request, 'finance:read');
        Gate::forUser($request->user())->authorize('finance.accounts.view');
        $accounts = FinancialAccount::query()->where('is_active', true)->orderBy('name')->get();

        return response()->json(['data' => $accounts->map(fn (FinancialAccount $account) => ['id' => $account->id, 'name' => $account->name, 'code' => $account->code, 'type' => $account->type, 'currency' => $account->currency, 'balance' => round($this->finance->balance($account), 2)])->values()]);
    }

    public function financeTransactions(Request $request): JsonResponse
    {
        $this->scope($request, 'finance:read');
        Gate::forUser($request->user())->authorize('finance.view');
        $transactions = FinancialTransaction::query()->with('account')
            ->when($request->filled('account_id'), fn (Builder $query) => $query->where('account_id', $request->integer('account_id')))
            ->when($request->filled('type'), fn (Builder $query) => $query->where('transaction_type', $request->string('type')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('transaction_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('transaction_date', '<=', $request->date('to')))
            ->latest('transaction_date')->latest('id')->paginate($this->perPage($request));

        return $this->page($transactions, fn (FinancialTransaction $transaction) => [
            'id' => $transaction->id, 'transaction_number' => $transaction->transaction_number,
            'account' => ['id' => $transaction->account?->id, 'name' => $transaction->account?->name, 'code' => $transaction->account?->code],
            'type' => $transaction->transaction_type, 'direction' => $transaction->direction,
            'amount' => (float) $transaction->amount, 'currency' => $transaction->currency,
            'reference' => $transaction->reference, 'description' => $transaction->description,
            'date' => $transaction->transaction_date?->toIso8601String(), 'status' => $transaction->status,
        ]);
    }

    public function financeReport(Request $request): JsonResponse
    {
        $this->scope($request, 'finance:read');
        Gate::forUser($request->user())->authorize('finance.reports.view');
        [$from, $to] = $this->range($request);
        $transactions = FinancialTransaction::query()->whereIn('status', ['posted', 'reversed'])->whereBetween('transaction_date', [$from, $to])->get(['transaction_type', 'direction', 'amount']);
        $expenses = Expense::query()->whereIn('status', ['paid', 'posted'])->whereBetween('expense_date', [$from, $to])->sum('amount');

        return response()->json(['data' => [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'money_in' => round((float) $transactions->where('direction', 'credit')->sum('amount'), 2),
            'money_out' => round((float) $transactions->where('direction', 'debit')->sum('amount'), 2),
            'expenses' => round((float) $expenses, 2),
            'by_type' => $transactions->groupBy('transaction_type')->map(fn ($rows) => ['money_in' => round((float) $rows->where('direction', 'credit')->sum('amount'), 2), 'money_out' => round((float) $rows->where('direction', 'debit')->sum('amount'), 2), 'count' => $rows->count()])->all(),
        ]]);
    }

    public function invoice(Request $request, Invoice $invoice): JsonResponse
    {
        $this->scope($request, 'invoices:read');
        Gate::forUser($request->user())->authorize('view', $invoice);
        $invoice->load(['payment.reservation.client', 'payment.reservation.room', 'payment.reservation.posRoomCharges.order.outlet', 'payment.refunds']);
        $payment = $invoice->payment;
        $reservation = $payment?->reservation;
        $lineItems = [];
        if ($reservation) {
            $lineItems[] = ['type' => 'accommodation', 'description' => 'Accommodation', 'amount' => (float) $reservation->total_amount, 'date' => $reservation->check_in?->toDateString()];
            foreach ($reservation->posRoomCharges->where('status', 'active') as $charge) {
                $lineItems[] = ['type' => 'pos_room_charge', 'pos_order' => $charge->order?->order_number, 'outlet' => $charge->order?->outlet?->name, 'description' => 'POS room charge '.$charge->order?->order_number, 'amount' => (float) $charge->amount, 'date' => $charge->posted_at?->toDateString()];
            }
        }

        return response()->json(['data' => [
            'id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'status' => $invoice->status, 'issue_date' => $invoice->issue_date?->toIso8601String(),
            'payment' => $payment ? $this->paymentData($payment) : null,
            'reservation' => $reservation ? $this->reservationData($reservation) : null,
            'line_items' => $lineItems,
        ]]);
    }

    public function hotelReport(Request $request): JsonResponse
    {
        $this->scope($request, 'reports:read');
        Gate::forUser($request->user())->authorize('reports.view');
        [$from, $to] = $this->range($request);
        $report = $this->analytics->report($from, $to, $request->user()->hasPermission('payments.view'));

        return response()->json(['data' => [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'revenue' => (float) $report['revenue'], 'room_nights' => (int) $report['roomNights'], 'available_room_nights' => (int) $report['availableRoomNights'],
            'occupancy' => (float) $report['occupancy'], 'adr' => (float) $report['adr'], 'revpar' => (float) $report['revpar'], 'outstanding' => (float) $report['outstanding'],
            'source_counts' => $report['sourceCounts'], 'room_type_revenue' => $report['roomTypeRevenue'],
            'reservations' => $this->pageData($report['reservations'], fn ($reservation) => [
                'id' => $reservation->id, 'code' => $reservation->code, 'guest' => $reservation->client?->full_name, 'room' => $reservation->room?->room_number,
                'status' => $reservation->status?->value, 'total_due' => round((float) $reservation->total_amount + (float) ($reservation->room_charge_amount ?? 0), 2),
                'paid' => (float) ($reservation->paid_amount ?? 0), 'balance' => max(0, round((float) $reservation->total_amount + (float) ($reservation->room_charge_amount ?? 0) - (float) ($reservation->paid_amount ?? 0), 2)),
            ]),
        ]]);
    }

    public function staff(Request $request): JsonResponse
    {
        $this->scope($request, 'staff:read');
        Gate::forUser($request->user())->authorize('viewAny', User::class);
        $staff = User::query()->with(['role', 'department'])
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $like = '%'.trim((string) $request->input('search')).'%';
                $query->where(fn (Builder $nested) => $nested->where('name', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('username', 'like', $like));
            })
            ->when($request->filled('active'), fn (Builder $query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('name')->paginate($this->perPage($request));

        return $this->page($staff, fn (User $user) => $this->staffData($user));
    }

    public function staffMember(Request $request, User $staff): JsonResponse
    {
        $this->scope($request, 'staff:read');
        Gate::forUser($request->user())->authorize('view', $staff);
        $staff->load(['role', 'department']);

        return response()->json(['data' => $this->staffData($staff)]);
    }

    private function scope(Request $request, string $ability): void
    {
        abort_unless($request->attributes->get('api_token')?->allows($ability), 403, 'This token does not have the required scope.');
    }

    private function perPage(Request $request, int $default = 25): int
    {
        return min(100, max(1, $request->integer('per_page', $default)));
    }

    private function page(LengthAwarePaginator $paginator, callable $mapper): JsonResponse
    {
        return response()->json(['data' => $paginator->getCollection()->map($mapper)->values(), 'meta' => [
            'current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total(),
        ], 'links' => [
            'first' => $paginator->url(1), 'last' => $paginator->url(max(1, $paginator->lastPage())), 'prev' => $paginator->previousPageUrl(), 'next' => $paginator->nextPageUrl(),
        ]]);
    }

    private function pageData(LengthAwarePaginator $paginator, callable $mapper): array
    {
        return ['data' => $paginator->getCollection()->map($mapper)->values(), 'meta' => [
            'current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total(),
        ]];
    }

    private function range(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);
        [$from, $to] = $this->analytics->reportRange($data['from'] ?? null, $data['to'] ?? null);
        if ($from->greaterThan($to) || $from->diffInDays($to) > 366) abort(422, 'Report periods must be ordered and cannot exceed 366 days.');

        return [$from, $to];
    }

    private function propertyExists(string $table): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists($table, 'id')->where(fn ($query) => $query->where('property_id', app(TenantContext::class)->propertyId()));
    }

    private function organizationExists(string $table): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists($table, 'id')->where(fn ($query) => $query->where('organization_id', app(TenantContext::class)->organizationId()));
    }

    private function reservationData(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id, 'code' => $reservation->code,
            'guest' => ['id' => $reservation->client?->id, 'name' => $reservation->client?->full_name],
            'room' => ['id' => $reservation->room?->id, 'number' => $reservation->room?->room_number, 'type' => $reservation->room?->roomType?->name],
            'check_in' => $reservation->check_in?->toIso8601String(), 'check_out' => $reservation->check_out?->toIso8601String(), 'status' => $reservation->status?->value,
            'accommodation_total' => (float) $reservation->total_amount, 'pos_room_charges' => (float) $this->financials->roomChargeAmount($reservation),
            'total_due' => $this->financials->totalDue($reservation), 'paid' => $this->financials->paidAmount($reservation), 'balance' => $this->financials->balance($reservation),
            'source' => $reservation->source?->name,
        ];
    }

    private function paymentData(Payment $payment): array
    {
        return [
            'id' => $payment->id, 'invoice_number' => $payment->invoice_number, 'reservation_id' => $payment->reservation_id,
            'amount' => (float) $payment->amount, 'method' => $payment->method, 'reference' => $payment->reference, 'status' => $payment->status?->value,
            'transaction_date' => $payment->transaction_date?->toIso8601String(),
            'refunds' => $payment->relationLoaded('refunds') ? $payment->refunds->map(fn ($refund) => ['id' => $refund->id, 'amount' => (float) $refund->amount, 'reference' => $refund->refund_reference, 'status' => $refund->status])->values() : [],
        ];
    }

    private function posOrderData(PosOrder $order): array
    {
        return [
            'id' => $order->id, 'order_number' => $order->order_number, 'status' => $order->status,
            'outlet' => ['id' => $order->outlet?->id, 'name' => $order->outlet?->name, 'code' => $order->outlet?->code],
            'cashier' => ['id' => $order->cashier?->id, 'name' => $order->cashier?->display_name],
            'reservation_id' => $order->reservation_id, 'room' => ['id' => $order->room?->id, 'number' => $order->room?->room_number],
            'subtotal' => (float) $order->subtotal, 'discount' => (float) $order->discount_total, 'tax' => (float) $order->tax_total, 'total' => (float) $order->total,
            'completed_at' => $order->completed_at?->toIso8601String(),
            'items' => $order->relationLoaded('items') ? $order->items->map(fn ($item) => ['id' => $item->id, 'product' => $item->product_name_snapshot, 'sku' => $item->sku_snapshot, 'quantity' => (float) $item->quantity, 'unit_price' => (float) $item->unit_price, 'tax' => (float) $item->tax, 'total' => (float) $item->total])->values() : [],
            'payments' => $order->relationLoaded('payments') ? $order->payments->map(fn ($payment) => ['id' => $payment->id, 'method' => $payment->method, 'amount' => (float) $payment->amount, 'reference' => $payment->reference, 'status' => $payment->status])->values() : [],
            'room_charge' => $order->roomCharge ? ['id' => $order->roomCharge->id, 'reservation_id' => $order->roomCharge->reservation_id, 'amount' => (float) $order->roomCharge->amount, 'status' => $order->roomCharge->status, 'posted_at' => $order->roomCharge->posted_at?->toIso8601String()] : null,
            'refunds' => $order->relationLoaded('refunds') ? $order->refunds->map(fn ($refund) => ['id' => $refund->id, 'amount' => (float) $refund->amount, 'reason' => $refund->reason, 'refunded_at' => $refund->refunded_at?->toIso8601String()])->values() : [],
        ];
    }

    private function staffData(User $staff): array
    {
        return [
            'id' => $staff->id, 'display_name' => $staff->display_name, 'username' => $staff->username, 'active' => $staff->is_active,
            'role' => $staff->role?->name, 'department' => $staff->department?->name, 'last_login_at' => $staff->last_login_at?->toIso8601String(),
        ];
    }
}
