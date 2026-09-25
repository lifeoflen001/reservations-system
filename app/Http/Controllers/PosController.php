<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PaymentMethod;
use App\Models\PosCategory;
use App\Models\PosOrder;
use App\Models\PosOutlet;
use App\Models\PosProduct;
use App\Models\PosShift;
use App\Models\Reservation;
use App\Services\PosOrderService;
use App\Services\PosReportService;
use App\Services\PosShiftService;
use App\Support\CurrencyFormatter;
use App\Support\TablePagination;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class PosController extends Controller
{
    public function __construct(private readonly PosOrderService $orders, private readonly PosShiftService $shifts, private readonly PosReportService $reports) {}

    public function terminal(Request $request, CurrencyFormatter $formatter)
    {
        Gate::authorize('create', PosOrder::class);
        $outlets = PosOutlet::query()->where('is_active', true)->orderBy('name')->get();
        $outletId = $request->integer('outlet_id') ?: $outlets->first()?->id;
        $products = PosProduct::query()->with('category')->active()->where(fn (Builder $query) => $query->whereNull('outlet_id')->orWhere('outlet_id', $outletId))->when($request->filled('category'), fn (Builder $query) => $query->where('category_id', $request->integer('category')))->when($request->filled('search'), function (Builder $query) use ($request): void { $term = '%'.trim((string) $request->string('search')).'%'; $query->where(fn ($nested) => $nested->where('name', 'like', $term)->orWhere('sku', 'like', $term)); })->orderBy('name')->limit(80)->get();
        $categories = PosCategory::query()->where('is_active', true)->withCount(['products' => fn ($query) => $query->where('is_active', true)])->orderBy('sort_order')->orderBy('name')->get();
        $paymentMethods = PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $inHouse = Reservation::query()->where('status', ReservationStatus::CheckedIn->value)->with(['client', 'room.roomType'])->latest('check_in')->limit(50)->get();
        $openShift = $outletId ? PosShift::query()->where('outlet_id', $outletId)->where('cashier_id', $request->user()->id)->where('status', 'open')->first() : null;
        return view('pos.terminal', compact('outlets', 'outletId', 'products', 'categories', 'paymentMethods', 'inHouse', 'openShift', 'formatter') + ['canDiscount' => $request->user()->hasPermission('pos.discount')]);
    }

    public function productSearch(Request $request): JsonResponse
    {
        Gate::authorize('create', PosOrder::class);
        $term = trim((string) $request->get('q', ''));
        $products = PosProduct::query()->with('category')->active()->when($request->filled('outlet_id'), fn ($query) => $query->where(fn ($nested) => $nested->whereNull('outlet_id')->orWhere('outlet_id', $request->integer('outlet_id'))))->when($term !== '', function ($query) use ($term): void { $like = '%'.$term.'%'; $query->where(fn ($nested) => $nested->where('name', 'like', $like)->orWhere('sku', 'like', $like)); })->orderBy('name')->limit(40)->get();
        return response()->json($products->map(fn (PosProduct $product) => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'price' => (float) $product->selling_price, 'category_id' => $product->category_id, 'category' => $product->category?->name, 'tax_rate' => (float) $product->tax_rate, 'tax_inclusive' => $product->tax_inclusive, 'track_stock' => $product->track_stock, 'stock_quantity' => (float) $product->stock_quantity]));
    }

    public function guestSearch(Request $request): JsonResponse
    {
        Gate::authorize('create', PosOrder::class);
        $term = trim((string) $request->get('q', ''));
        $like = '%'.$term.'%';
        $reservations = Reservation::query()->where('status', ReservationStatus::CheckedIn->value)->with(['client', 'room.roomType'])->where(function ($query) use ($like): void { $query->where('code', 'like', $like)->orWhereHas('room', fn ($room) => $room->where('room_number', 'like', $like))->orWhereHas('client', fn ($client) => $client->where(fn ($nested) => $nested->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like))); })->limit(20)->get();
        return response()->json($reservations->map(fn (Reservation $reservation) => ['id' => $reservation->id, 'code' => $reservation->code, 'client_id' => $reservation->client_id, 'client' => $reservation->client?->full_name, 'room_id' => $reservation->room_id, 'room' => $reservation->room?->room_number, 'room_type' => $reservation->room?->roomType?->name, 'status' => $reservation->status->label()]));
    }

    public function checkout(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', PosOrder::class);
        $data = $request->validate(['outlet_id' => ['required', 'integer', 'exists:pos_outlets,id'], 'shift_id' => ['nullable', 'integer', 'exists:pos_shifts,id'], 'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'], 'client_id' => ['nullable', 'integer', 'exists:clients,id'], 'room_id' => ['nullable', 'integer', 'exists:rooms,id'], 'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'integer', 'exists:pos_products,id'], 'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999'], 'items.*.note' => ['nullable', 'string', 'max:500'], 'payments' => ['required', 'array', 'min:1'], 'payments.*.method' => ['required', 'string', 'max:60'], 'payments.*.amount' => ['required', 'numeric', 'gt:0'], 'payments.*.reference' => ['nullable', 'string', 'max:100'], 'discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])], 'discount_value' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['nullable', 'string', 'max:80']]);
        try {
            $order = $this->orders->checkout($data, $request->user());
        } catch (InvalidArgumentException $exception) {
            if ($request->expectsJson()) return response()->json(['message' => $exception->getMessage()], 422);
            return back()->withInput()->with('error', $exception->getMessage());
        }
        if ($request->expectsJson()) return response()->json(['message' => 'Sale completed successfully.', 'order' => ['id' => $order->id, 'order_number' => $order->order_number, 'total' => (float) $order->total, 'receipt_url' => route('pos.receipts.show', $order)]]);
        return redirect()->route('pos.receipts.show', $order)->with('success', 'Sale completed successfully.');
    }

    public function orders(Request $request, CurrencyFormatter $formatter)
    {
        Gate::authorize('viewAny', PosOrder::class);
        $query = PosOrder::query()->with(['outlet', 'cashier', 'client', 'room', 'payments'])->when($request->filled('search'), function (Builder $query) use ($request): void { $like = '%'.trim((string) $request->string('search')).'%'; $query->where(fn ($nested) => $nested->where('order_number', 'like', $like)->orWhereHas('client', fn ($client) => $client->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like))->orWhereHas('room', fn ($room) => $room->where('room_number', 'like', $like))); })->when($request->filled('status') && $request->string('status') !== 'all', fn ($query) => $query->where('status', $request->string('status')))->when($request->input('scope') === 'room_charges', fn ($query) => $query->whereHas('roomCharge', fn ($charge) => $charge->where('status', 'active')))->when($request->filled('outlet_id') && $request->integer('outlet_id'), fn ($query) => $query->where('outlet_id', $request->integer('outlet_id')))->when($request->filled('from'), fn ($query) => $query->whereDate('completed_at', '>=', $request->date('from')))->when($request->filled('to'), fn ($query) => $query->whereDate('completed_at', '<=', $request->date('to')));
        if (! $request->user()->hasPermission('pos.view_all_orders')) $query->where('cashier_id', $request->user()->id);
        $orders = $query->latest('completed_at')->paginate(TablePagination::perPage($request, 20))->withQueryString();
        $today = now(config('app.timezone'))->startOfDay(); $todayEnd = $today->copy()->endOfDay();
        $base = PosOrder::query()->whereBetween('completed_at', [$today, $todayEnd])->where('status', 'completed');
        if (! $request->user()->hasPermission('pos.view_all_orders')) $base->where('cashier_id', $request->user()->id);
        $roomCharges = PosOrder::query()->whereBetween('completed_at', [$today, $todayEnd])->where('status', 'completed')->whereHas('roomCharge', fn ($q) => $q->where('status', 'active'));
        if (! $request->user()->hasPermission('pos.view_all_orders')) $roomCharges->where('cashier_id', $request->user()->id);
        return view('pos.orders', ['orders' => $orders, 'outlets' => PosOutlet::where('is_active', true)->orderBy('name')->get(), 'formatter' => $formatter, 'kpis' => [['label' => 'Today sales', 'value' => $formatter->format((clone $base)->sum('total')), 'icon' => 'currency', 'tone' => 'warning', 'href' => route('pos.orders', ['status' => 'completed', 'from' => now()->toDateString(), 'to' => now()->toDateString()])], ['label' => 'Orders today', 'value' => (string) (clone $base)->count(), 'icon' => 'document', 'tone' => 'info', 'href' => route('pos.orders', ['status' => 'completed', 'from' => now()->toDateString(), 'to' => now()->toDateString()])], ['label' => 'Room charges', 'value' => $formatter->format($roomCharges->sum('total')), 'icon' => 'bed', 'tone' => 'success', 'href' => route('pos.orders', ['status' => 'completed', 'scope' => 'room_charges', 'from' => now()->toDateString(), 'to' => now()->toDateString()])]]]);
    }

    public function showOrder(PosOrder $order, CurrencyFormatter $formatter)
    {
        Gate::authorize('view', $order);
        return view('pos.show', ['order' => $order->load(['items.product', 'payments', 'roomCharge.reservation', 'outlet', 'cashier', 'client', 'room', 'refunds']), 'formatter' => $formatter]);
    }

    public function receipt(PosOrder $order, CurrencyFormatter $formatter)
    {
        Gate::authorize('view', $order);
        return view('pos.receipt', ['order' => $order->load(['items', 'payments', 'roomCharge.reservation.client', 'roomCharge.reservation.room', 'reservation.client', 'reservation.room', 'outlet', 'cashier', 'client', 'room']), 'formatter' => $formatter, 'property' => app(\App\Services\PropertySettingsService::class)->current()]);
    }

    public function receiptDownload(PosOrder $order, CurrencyFormatter $formatter)
    {
        Gate::authorize('view', $order);
        $data = ['order' => $order->load(['items', 'payments', 'roomCharge.reservation.client', 'roomCharge.reservation.room', 'reservation.client', 'reservation.room', 'outlet', 'cashier', 'client', 'room']), 'formatter' => $formatter, 'property' => app(\App\Services\PropertySettingsService::class)->current()];
        $html = view('pos.receipts.pdf', $data)->render();
        if (class_exists(\Dompdf\Dompdf::class)) { $pdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true]); $pdf->loadHtml($html, 'UTF-8'); $pdf->setPaper('A4', 'portrait'); $pdf->render(); return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$order->order_number.'.pdf"']); }
        return response($html, 200, ['Content-Type' => 'text/html']);
    }

    public function void(Request $request, PosOrder $order): RedirectResponse
    {
        Gate::authorize('void', $order); $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        try { $this->orders->void($order, $request->user(), $data['reason']); } catch (InvalidArgumentException $exception) { return back()->with('error', $exception->getMessage()); }
        return back()->with('success', 'Sale voided and stock restored.');
    }

    public function refund(Request $request, PosOrder $order): RedirectResponse
    {
        Gate::authorize('refund', $order); $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string', 'max:1000']]);
        try { $this->orders->refund($order, $request->user(), (float) $data['amount'], $data['reason']); } catch (InvalidArgumentException $exception) { return back()->with('error', $exception->getMessage()); }
        return back()->with('success', 'Refund completed.');
    }

    public function products(Request $request, CurrencyFormatter $formatter)
    {
        Gate::authorize('viewAny', PosProduct::class);
        $products = PosProduct::query()->with(['category', 'outlet'])->withCount('items')->when($request->filled('search'), fn ($q) => $q->where(fn ($nested) => $nested->where('name', 'like', '%'.$request->string('search').'%')->orWhere('sku', 'like', '%'.$request->string('search').'%')))->when($request->filled('category_id') && $request->integer('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))->when($request->filled('outlet_id') && $request->integer('outlet_id'), fn ($q) => $q->where('outlet_id', $request->integer('outlet_id')))->when($request->filled('status') && $request->string('status') !== 'all', fn ($q) => $q->where('is_active', $request->string('status') === 'active'))->orderBy('name')->paginate(TablePagination::perPage($request, 20))->withQueryString();
        return view('pos.products', ['products' => $products, 'categories' => PosCategory::orderBy('name')->get(), 'outlets' => PosOutlet::orderBy('name')->get(), 'editProduct' => $request->filled('edit') ? PosProduct::find($request->integer('edit')) : null, 'openNew' => $request->boolean('new'), 'formatter' => $formatter]);
    }

    public function productStore(Request $request): RedirectResponse
    {
        Gate::authorize('create', PosProduct::class); $data = $this->productData($request); PosProduct::create($data + ['is_active' => $request->boolean('is_active'), 'track_stock' => $request->boolean('track_stock')]); return redirect()->route('pos.products')->with('success', 'Product created.');
    }

    public function productUpdate(Request $request, PosProduct $product): RedirectResponse
    {
        Gate::authorize('update', $product); $data = $this->productData($request, $product); $product->update($data + ['is_active' => $request->boolean('is_active'), 'track_stock' => $request->boolean('track_stock')]); return redirect()->route('pos.products')->with('success', 'Product updated.');
    }

    public function productDestroy(PosProduct $product): RedirectResponse
    {
        Gate::authorize('delete', $product); $product->update(['is_active' => false]); return back()->with('success', 'Product archived. Historical orders were preserved.');
    }

    public function catalog(Request $request)
    {
        Gate::authorize('pos.access'); return view('pos.catalog', ['tab' => $request->string('tab', 'categories')->toString(), 'categories' => PosCategory::withCount('products')->orderBy('sort_order')->orderBy('name')->get(), 'outlets' => PosOutlet::withCount(['products', 'orders'])->orderBy('name')->get(), 'editCategory' => $request->filled('edit_category') ? PosCategory::find($request->integer('edit_category')) : null, 'editOutlet' => $request->filled('edit_outlet') ? PosOutlet::find($request->integer('edit_outlet')) : null, 'openNew' => $request->boolean('new')]);
    }

    public function categoryStore(Request $request): RedirectResponse { Gate::authorize('pos.categories.manage'); $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:pos_categories,name'], 'code' => ['required', 'string', 'max:40', 'alpha_dash', 'unique:pos_categories,code'], 'description' => ['nullable', 'string', 'max:1000'], 'sort_order' => ['nullable', 'integer', 'min:0']]); PosCategory::create($data + ['is_active' => true]); return back()->with('success', 'POS category created.'); }
    public function categoryUpdate(Request $request, PosCategory $category): RedirectResponse { Gate::authorize('pos.categories.manage'); $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('pos_categories', 'name')->ignore($category)], 'code' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('pos_categories', 'code')->ignore($category)], 'description' => ['nullable', 'string', 'max:1000'], 'sort_order' => ['nullable', 'integer', 'min:0']]); $category->update($data + ['is_active' => $request->boolean('is_active')]); return back()->with('success', 'POS category updated.'); }
    public function categoryDestroy(PosCategory $category): RedirectResponse { Gate::authorize('pos.categories.manage'); if ($category->products()->exists()) return back()->with('error', 'Categories with products cannot be deleted.'); $category->delete(); return back()->with('success', 'POS category deleted.'); }

    public function outletStore(Request $request): RedirectResponse { Gate::authorize('pos.outlets.manage'); $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'code' => ['required', 'string', 'max:40', 'alpha_dash', 'unique:pos_outlets,code'], 'location' => ['nullable', 'string', 'max:255'], 'receipt_header' => ['nullable', 'string', 'max:2000']]); PosOutlet::create($data + ['is_active' => true]); return back()->with('success', 'POS outlet created.'); }
    public function outletUpdate(Request $request, PosOutlet $outlet): RedirectResponse { Gate::authorize('pos.outlets.manage'); $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'code' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('pos_outlets', 'code')->ignore($outlet)], 'location' => ['nullable', 'string', 'max:255'], 'receipt_header' => ['nullable', 'string', 'max:2000']]); $outlet->update($data + ['is_active' => $request->boolean('is_active')]); return back()->with('success', 'POS outlet updated.'); }
    public function outletDestroy(PosOutlet $outlet): RedirectResponse { Gate::authorize('pos.outlets.manage'); if ($outlet->orders()->exists()) return back()->with('error', 'Outlets with sales cannot be deleted. Deactivate the outlet instead.'); $outlet->delete(); return back()->with('success', 'POS outlet deleted.'); }

    public function shifts(Request $request, CurrencyFormatter $formatter)
    {
        Gate::authorize('viewAny', PosShift::class); $query = PosShift::query()->with(['outlet', 'cashier'])->latest('opened_at'); if (! $request->user()->hasPermission('pos.shifts.view_all')) $query->where('cashier_id', $request->user()->id); $shifts = $query->paginate(TablePagination::perPage($request, 20))->withQueryString(); return view('pos.shifts', ['shifts' => $shifts, 'outlets' => PosOutlet::where('is_active', true)->orderBy('name')->get(), 'formatter' => $formatter]);
    }
    public function shiftOpen(Request $request): RedirectResponse { Gate::authorize('open', PosShift::class); $data = $request->validate(['outlet_id' => ['required', 'integer', 'exists:pos_outlets,id'], 'opening_cash' => ['required', 'numeric', 'min:0']]); try { $this->shifts->open((int) $data['outlet_id'], $request->user(), (float) $data['opening_cash']); } catch (InvalidArgumentException $exception) { return back()->with('error', $exception->getMessage()); } return back()->with('success', 'Shift opened.'); }
    public function shiftClose(Request $request, PosShift $shift): RedirectResponse { Gate::authorize('close', $shift); $data = $request->validate(['actual_cash' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:1000']]); try { $this->shifts->close($shift, $request->user(), (float) $data['actual_cash'], $data['notes'] ?? null); } catch (InvalidArgumentException $exception) { return back()->with('error', $exception->getMessage()); } return back()->with('success', 'Shift closed.'); }

    public function reports(Request $request, CurrencyFormatter $formatter)
    {
        Gate::authorize('pos.reports.view'); $from = Carbon::createFromFormat('Y-m-d', $request->input('from', now(config('app.timezone'))->toDateString()), config('app.timezone'))->startOfDay(); $to = Carbon::createFromFormat('Y-m-d', $request->input('to', now(config('app.timezone'))->toDateString()), config('app.timezone'))->endOfDay(); if ($from->greaterThan($to)) [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()]; return view('pos.reports', ['from' => $from, 'to' => $to, 'report' => $this->reports->summary($from, $to), 'formatter' => $formatter]);
    }

    private function productData(Request $request, ?PosProduct $product = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:160'], 'sku' => ['nullable', 'string', 'max:80', Rule::unique('pos_products', 'sku')->ignore($product)], 'category_id' => ['nullable', 'integer', 'exists:pos_categories,id'], 'outlet_id' => ['nullable', 'integer', 'exists:pos_outlets,id'], 'description' => ['nullable', 'string', 'max:2000'], 'selling_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'], 'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,3'], 'cost_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'], 'stock_quantity' => ['nullable', 'numeric', 'min:0'], 'reorder_level' => ['nullable', 'numeric', 'min:0']]) + ['tax_inclusive' => $request->boolean('tax_inclusive')];
    }
}
