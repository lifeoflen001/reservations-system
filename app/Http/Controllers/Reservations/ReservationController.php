<?php

namespace App\Http\Controllers\Reservations;

use App\Enums\ReservationStatus;
use App\Exceptions\RoomUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\StoreReservationRequest;
use App\Http\Requests\Reservations\UpdateReservationRequest;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\ReservationSource;
use App\Models\Room;
use App\Services\ReservationService;
use App\Services\ReservationWorkflowService;
use App\Services\PropertySettingsService;
use App\Services\MiniDashboardMetricsService;
use App\Support\TablePagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly ReservationWorkflowService $workflow,
    ) {}

    public function index(Request $request, PropertySettingsService $propertySettings, MiniDashboardMetricsService $metricsService)
    {
        Gate::authorize('viewAny', Reservation::class);
        $reservations = $this->filteredQuery($request)
            ->with(['client', 'room.roomType', 'source', 'creator'])
            ->withSum(['payments as paid_amount' => fn (Builder $query) => $query->successful()], 'amount')
            ->withExists('payments')
            ->latest('check_in')
            ->orderByDesc('reservations.id')
            ->paginate(TablePagination::perPage($request, 15))
            ->withQueryString();

        $openReservation = $request->filled('reservation')
            ? Reservation::query()->with(['client', 'room.floor', 'room.category', 'room.roomType.amenities', 'source', 'creator', 'updater', 'payments'])->find($request->integer('reservation'))
            : null;
        $editReservation = $request->filled('edit')
            ? Reservation::query()->with(['client', 'room.roomType', 'source'])->find($request->integer('edit'))
            : null;

        return view('reservations.index', [
            'reservations' => $reservations,
            'clients' => Client::query()->orderBy('last_name')->orderBy('first_name')->get(),
            'rooms' => Room::query()->active()->with(['floor', 'category', 'roomType'])->orderBy('room_number')->get(),
            'sources' => ReservationSource::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => ReservationStatus::cases(),
            'openNew' => $request->boolean('new'),
            'openReservation' => $openReservation,
            'editReservation' => $editReservation,
            'defaultCheckInTime' => $propertySettings->checkInTime(),
            'defaultCheckOutTime' => $propertySettings->checkOutTime(),
            'kpis' => $metricsService->reservations(),
        ]);
    }

    public function create()
    {
        Gate::authorize('create', Reservation::class);
        return redirect()->route('reservations.index', ['new' => 1]);
    }

    public function store(StoreReservationRequest $request)
    {
        Gate::authorize('create', Reservation::class);
        try {
            $reservation = $this->reservations->create($request->validated(), $request->user()->id);
        } catch (RoomUnavailableException|LogicException|\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['room_id' => $exception->getMessage()]);
        }
        return redirect()->route('reservations.index', ['reservation' => $reservation->id])->with('success', 'Reservation created successfully.');
    }

    public function show(Reservation $reservation)
    {
        Gate::authorize('view', $reservation);
        return redirect()->route('reservations.index', ['reservation' => $reservation->id]);
    }

    public function edit(Reservation $reservation)
    {
        Gate::authorize('update', $reservation);
        return redirect()->route('reservations.index', ['edit' => $reservation->id]);
    }

    public function update(UpdateReservationRequest $request, Reservation $reservation)
    {
        Gate::authorize('update', $reservation);
        try {
            $reservation = $this->reservations->update($reservation, $request->validated(), $request->user()->id);
        } catch (RoomUnavailableException|LogicException|\InvalidArgumentException|ValidationException $exception) {
            if ($exception instanceof ValidationException) throw $exception;
            throw ValidationException::withMessages(['room_id' => $exception->getMessage()]);
        }
        return redirect()->route('reservations.index', ['reservation' => $reservation->id])->with('success', 'Reservation updated successfully.');
    }

    public function destroy(Request $request, Reservation $reservation)
    {
        Gate::authorize('delete', $reservation);
        if ($reservation->payments()->exists() || $reservation->status === ReservationStatus::CheckedIn) {
            return back()->with('error', 'This reservation cannot be deleted because it has financial or active stay history.');
        }
        $reservation->update(['updated_by' => $request->user()->id]);
        $reservation->delete();
        return redirect()->route('reservations.index')->with('success', 'Reservation removed from the active list.');
    }

    public function checkIn(Request $request, Reservation $reservation)
    {
        Gate::authorize('checkIn', $reservation);
        try { $this->workflow->checkIn($reservation, $request->user()->id); }
        catch (LogicException $exception) { return back()->with('error', $exception->getMessage()); }
        return back()->with('success', 'Guest checked in successfully.');
    }

    public function checkOut(Request $request, Reservation $reservation)
    {
        Gate::authorize('checkOut', $reservation);
        try { $this->workflow->checkOut($reservation, $request->user()->id); }
        catch (LogicException $exception) { return back()->with('error', $exception->getMessage()); }
        return back()->with('success', 'Guest checked out. Room sent to housekeeping.');
    }

    public function cancel(Request $request, Reservation $reservation)
    {
        Gate::authorize('cancel', $reservation);
        $data = $request->validate(['cancellation_reason' => ['nullable', 'string', 'max:2000']]);
        try { $this->workflow->cancel($reservation, $request->user()->id, $data['cancellation_reason'] ?? null); }
        catch (LogicException $exception) { return back()->with('error', $exception->getMessage()); }
        return redirect()->route('reservations.index', ['reservation' => $reservation->id])->with('success', 'Reservation cancelled.');
    }

    public function noShow(Request $request, Reservation $reservation)
    {
        Gate::authorize('noShow', $reservation);
        try { $this->workflow->markNoShow($reservation, $request->user()->id); }
        catch (LogicException $exception) { return back()->with('error', $exception->getMessage()); }
        return redirect()->route('reservations.index', ['reservation' => $reservation->id])->with('success', 'Reservation marked as no-show.');
    }

    public function export(Request $request)
    {
        Gate::authorize('export', Reservation::class);
        $query = $this->filteredQuery($request)->with(['client', 'room.roomType', 'source'])->withSum(['payments as paid_amount' => fn (Builder $query) => $query->successful()], 'amount');
        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Reservation code', 'Guest name', 'Guest email', 'Room', 'Room type', 'Check-in', 'Check-out', 'Source', 'Nightly rate', 'Total', 'Paid', 'Balance', 'Status']);
            foreach ($query->cursor() as $reservation) {
                $paid = (float) ($reservation->paid_amount ?? 0);
                fputcsv($handle, [$reservation->code, $reservation->client->full_name, $reservation->client->email, $reservation->room->room_number, $reservation->room->roomType?->name, $reservation->check_in->toDateTimeString(), $reservation->check_out->toDateTimeString(), $reservation->source?->name, $reservation->nightly_rate, $reservation->total_amount, $paid, max(0, (float) $reservation->total_amount - $paid), $reservation->status->label()]);
            }
            fclose($handle);
        }, 'reservations-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function filteredQuery(Request $request): Builder
    {
        return Reservation::query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = trim((string) $request->string('search'));
                $query->where(function (Builder $query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhereHas('client', fn (Builder $client) => $client->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('room', fn (Builder $room) => $room->where('room_number', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status') && (string) $request->string('status') !== 'all', fn (Builder $query) => $query->where('status', (string) $request->string('status')))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('check_in', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('check_in', '<=', $request->date('to')));
    }
}
