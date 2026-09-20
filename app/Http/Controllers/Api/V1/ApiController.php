<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\ReservationService;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ApiController extends Controller
{
    public function rooms(Request $request): JsonResponse
    {
        $this->ability($request, 'rooms:read');

        return response()->json(['data' => Room::query()->with(['roomType', 'floor'])->where('is_active', true)->paginate(25)->through(fn (Room $room) => ['id' => $room->id, 'room_number' => $room->room_number, 'room_type' => $room->roomType?->name, 'floor' => $room->floor?->name, 'capacity' => $room->capacity, 'status' => $room->operational_status?->value])]);
    }

    public function availability(Request $request): JsonResponse
    {
        $this->ability($request, 'availability:read');
        $data = $request->validate(['check_in' => ['required', 'date'], 'check_out' => ['required', 'date', 'after:check_in']]);
        $rooms = app(RoomAvailabilityService::class)->findAvailableRooms(Carbon::parse($data['check_in']), Carbon::parse($data['check_out']));

        return response()->json(['data' => $rooms->map(fn (Room $room) => ['id' => $room->id, 'room_number' => $room->room_number, 'room_type' => $room->roomType?->name, 'rate' => $room->base_rate, 'capacity' => $room->capacity])]);
    }

    public function reservation(Request $request, Reservation $reservation): JsonResponse
    {
        $this->ability($request, 'reservations:read');
        Gate::forUser($request->user())->authorize('view', $reservation);
        $reservation->load(['client', 'room.roomType', 'source']);

        return response()->json(['data' => ['id' => $reservation->id, 'code' => $reservation->code, 'guest' => $reservation->client?->full_name, 'room' => $reservation->room?->room_number, 'room_type' => $reservation->room?->roomType?->name, 'check_in' => $reservation->check_in?->toIso8601String(), 'check_out' => $reservation->check_out?->toIso8601String(), 'total_amount' => $reservation->total_amount, 'status' => $reservation->status?->value, 'source' => $reservation->source?->name]]);
    }

    public function storeReservation(Request $request, ReservationService $reservations): JsonResponse
    {
        $this->ability($request, 'reservations:write');
        Gate::forUser($request->user())->authorize('create', Reservation::class);
        $data = $request->validate(['client_id' => ['required', 'integer', 'exists:clients,id'], 'room_id' => ['required', 'integer', 'exists:rooms,id'], 'reservation_source_id' => ['nullable', 'integer', 'exists:reservation_sources,id'], 'check_in' => ['required', 'date'], 'check_out' => ['required', 'date', 'after:check_in'], 'adults' => ['required', 'integer', 'min:1'], 'children' => ['required', 'integer', 'min:0'], 'status' => ['required', 'in:pending,confirmed'], 'nightly_rate' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:5000']]);
        $reservation = $reservations->create($data, $request->user()->id);

        return response()->json(['data' => ['id' => $reservation->id, 'code' => $reservation->code]], 201);
    }

    public function client(Request $request, Client $client): JsonResponse
    {
        $this->ability($request, 'clients:read');
        Gate::forUser($request->user())->authorize('view', $client);

        return response()->json(['data' => ['id' => $client->id, 'name' => $client->full_name, 'email' => $client->email, 'phone' => $client->phone, 'country' => $client->country, 'city' => $client->city]]);
    }

    public function payment(Request $request, Payment $payment): JsonResponse
    {
        $this->ability($request, 'payments:read');
        Gate::forUser($request->user())->authorize('view', $payment);

        return response()->json(['data' => ['id' => $payment->id, 'invoice_number' => $payment->invoice_number, 'reservation_id' => $payment->reservation_id, 'amount' => $payment->amount, 'method' => $payment->method, 'status' => $payment->status?->value, 'transaction_date' => $payment->transaction_date?->toIso8601String()]]);
    }

    private function ability(Request $request, string $ability): void
    {
        abort_unless($request->attributes->get('api_token')?->allows($ability), 403, 'This token does not have the required scope.');
    }
}
