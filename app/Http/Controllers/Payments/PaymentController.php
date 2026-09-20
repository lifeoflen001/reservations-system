<?php

namespace App\Http\Controllers\Payments;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Http\Requests\Payments\UpdatePaymentRequest;
use App\Http\Requests\Payments\VoidPaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Services\PaymentService;
use App\Services\PropertySettingsService;
use App\Services\MiniDashboardMetricsService;
use App\Support\CurrencyFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {}

    public function index(Request $request, CurrencyFormatter $formatter, PropertySettingsService $propertySettings, MiniDashboardMetricsService $metricsService)
    {
        Gate::authorize('viewAny', Payment::class);
        $payments = $this->filteredQuery($request)
            ->with(['invoice', 'reservation.client', 'reservation.room.roomType', 'creator'])
            ->latest('transaction_date')
            ->orderByDesc('payments.id')
            ->paginate(15)
            ->withQueryString();

        $reservationOptions = Reservation::query()
            ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value, ReservationStatus::CheckedIn->value, ReservationStatus::CheckedOut->value])
            ->where(function (Builder $query): void {
                $query->whereRaw('reservations.total_amount > (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.reservation_id = reservations.id AND payments.status = ?)', [PaymentStatus::Paid->value])
                    ->orWhereExists(fn ($paymentQuery) => $paymentQuery->selectRaw('1')->from('payments')->whereColumn('payments.reservation_id', 'reservations.id'));
            })
            ->with(['client', 'room.roomType'])
            ->withSum(['payments as paid_amount' => fn (Builder $query) => $query->successful()], 'amount')
            ->orderByDesc('check_in')
            ->limit(300)
            ->get();
        $selectedReservation = $request->filled('reservation') ? $reservationOptions->firstWhere('id', $request->integer('reservation')) : null;
        $openPayment = $request->filled('edit') ? Payment::with(['invoice', 'reservation.client', 'reservation.room.roomType'])->find($request->integer('edit')) : null;
        $invoice = $request->filled('invoice') ? Invoice::with(['payment.client', 'payment.reservation.room.roomType', 'payment.creator'])->find($request->integer('invoice')) : null;

        return view('payments.index', [
            'payments' => $payments,
            'formatter' => $formatter,
            'paymentMethods' => PaymentMethod::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'statuses' => PaymentStatus::cases(),
            'reservationOptions' => $reservationOptions,
            'selectedReservation' => $selectedReservation,
            'openPayment' => $openPayment,
            'invoice' => $invoice,
            'property' => $propertySettings->current(),
            'openNew' => $request->boolean('new'),
            'kpis' => $metricsService->payments(),
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        Gate::authorize('create', Payment::class);
        try {
            $payment = $this->payments->post($request->integer('reservation_id'), $request->validated(), $request->user()->getKey());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }
        return redirect()->route('payments.index', ['invoice' => $payment->invoice->getKey()])->with('success', 'Payment recorded successfully.');
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('update', $payment);
        try {
            $payment = $this->payments->update($payment, $request->validated(), $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }
        return redirect()->route('payments.index', ['invoice' => $payment->invoice->getKey()])->with('success', 'Payment updated successfully.');
    }

    public function void(VoidPaymentRequest $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('void', $payment);
        try {
            $this->payments->void($payment, $request->user(), $request->string('reason')->toString());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }
        return redirect()->route('payments.index')->with('success', 'Payment voided successfully.');
    }

    public function destroy(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('delete', $payment);
        if ($payment->status !== PaymentStatus::Pending) {
            return back()->with('error', 'Settled payments must be voided instead of deleted.');
        }
        $payment->delete();
        return back()->with('success', 'Pending payment deleted.');
    }

    public function export(Request $request): Response
    {
        Gate::authorize('export', Payment::class);
        $query = $this->filteredQuery($request)->with(['invoice', 'reservation.client', 'creator']);
        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice number', 'Reservation code', 'Guest', 'Amount', 'Method', 'Reference', 'Payment date', 'Status', 'Created by']);
            foreach ($query->cursor() as $payment) {
                fputcsv($handle, [$payment->invoice_number, $payment->reservation?->code, $payment->client?->full_name, $payment->amount, ucwords(str_replace('_', ' ', $payment->method)), $payment->reference, $payment->transaction_date?->toDateTimeString(), $payment->status->label(), $payment->creator?->display_name]);
            }
            fclose($handle);
        }, 'payments-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function print(Request $request, CurrencyFormatter $formatter)
    {
        Gate::authorize('print', Payment::class);
        return view('payments.print', ['payments' => $this->filteredQuery($request)->with(['invoice', 'reservation.client', 'reservation.room'])->get(), 'formatter' => $formatter, 'filters' => $request->only(['search', 'status', 'method', 'from', 'to'])]);
    }

    private function filteredQuery(Request $request): Builder
    {
        return Payment::query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->string('search')).'%';
                $query->where(function (Builder $nested) use ($term) {
                    $nested->where('invoice_number', 'like', $term)
                        ->orWhere('reference', 'like', $term)
                        ->orWhereHas('reservation', fn (Builder $reservation) => $reservation->where('code', 'like', $term))
                        ->orWhereHas('client', fn (Builder $client) => $client->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->when($request->filled('status') && $request->string('status')->toString() !== 'all', fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('method') && $request->string('method')->toString() !== 'all', fn (Builder $query) => $query->where('method', $request->string('method')->toString()))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('transaction_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('transaction_date', '<=', $request->date('to')));
    }
}
