<?php

namespace App\Http\Controllers;

use App\Enums\RoomOperationalStatus;
use App\Enums\ReservationStatus;
use App\Enums\TaskStatus;
use App\Models\Floor;
use App\Models\MaintenanceTask;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomCategory;
use App\Models\RoomType;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class RoomPlanningController extends Controller
{
    public function __construct(private readonly RoomAvailabilityService $availability) {}

    public function index(Request $request)
    {
        Gate::authorize('room_planning.view');
        $legacyDays = $request->integer('days');
        $view = in_array($request->string('view')->toString(), ['day', 'week', 'month', 'two_months'], true)
            ? $request->string('view')->toString()
            : null;
        $periodDays = match ($view) {
            'day' => 1,
            'week' => 7,
            'month' => 31,
            'two_months' => 62,
            default => in_array($legacyDays, [14, 30], true) ? $legacyDays : 31,
        };
        $view ??= match ($periodDays) {
            1 => 'day', 7 => 'week', 62 => 'two_months', default => 'month',
        };

        $startInput = (string) $request->input('start', '');
        $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startInput)
            ? Carbon::createFromFormat('Y-m-d', $startInput, config('app.timezone'))->startOfDay()
            : now()->startOfDay();
        $end = $start->copy()->addDays($periodDays)->startOfDay();
        $reservationSearch = trim((string) $request->input('reservation_search', ''));
        $canViewFinancials = $request->user()->hasPermission('payments.view');

        $rooms = Room::query()->active()->select(['id', 'room_number', 'floor_id', 'room_category_id', 'room_type_id', 'operational_status', 'housekeeping_status', 'is_active'])
            ->with(['floor:id,name', 'category:id,name,color', 'roomType:id,name'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.(string) $request->string('search').'%';
                $query->where(function ($roomQuery) use ($search) {
                    $roomQuery->where('room_number', 'like', $search)
                        ->orWhereHas('roomType', fn ($typeQuery) => $typeQuery->where('name', 'like', $search))
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', $search));
                });
            })
            ->when($request->filled('floor_id') && $request->input('floor_id') !== 'all', fn ($query) => $query->where('floor_id', $request->integer('floor_id')))
            ->when($request->filled('category_id') && $request->input('category_id') !== 'all', fn ($query) => $query->where('room_category_id', $request->integer('category_id')))
            ->when($request->filled('room_type_id') && $request->input('room_type_id') !== 'all', fn ($query) => $query->where('room_type_id', $request->integer('room_type_id')))
            ->when($request->filled('status') && $request->input('status') !== 'all', fn ($query) => $query->where('operational_status', (string) $request->input('status')))
            ->orderBy('room_category_id')->orderBy('room_type_id')->orderBy('floor_id')->orderBy('room_number')->get();
        $reservations = Reservation::query()->select(['id', 'code', 'client_id', 'room_id', 'check_in', 'check_out', 'adults', 'children', 'total_amount', 'status'])
            ->blockingAvailability()
            ->where('check_in', '<', $end)
            ->where('check_out', '>', $start)
            ->when($request->filled('reservation_status') && $request->input('reservation_status') !== 'all', fn ($query) => $query->where('status', (string) $request->input('reservation_status')))
            ->when($reservationSearch !== '', function ($query) use ($reservationSearch) {
                $term = '%'.$reservationSearch.'%';
                $query->where(function ($reservationQuery) use ($term) {
                    $reservationQuery->where('code', 'like', $term)
                        ->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term));
                });
            })
            ->with('client:id,first_name,middle_name,last_name')
            ->when($canViewFinancials, fn ($query) => $query->withSum(['payments as paid_amount' => fn ($paymentQuery) => $paymentQuery->successful()], DB::raw("payments.amount - COALESCE((SELECT SUM(payment_refunds.amount) FROM payment_refunds WHERE payment_refunds.payment_id = payments.id AND payment_refunds.status = 'posted'), 0)")))
            ->orderBy('check_in')
            ->get()
            ->groupBy('room_id');
        $conflictingReservationIds = $this->findConflictingReservationIds($reservations);

        $blocks = RoomBlock::query()->select(['id', 'room_id', 'type', 'reason', 'starts_at', 'ends_at'])->where('is_active', true)
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)->get()->groupBy('room_id');
        $maintenance = MaintenanceTask::query()->select(['id', 'room_id', 'issue', 'starts_at', 'ends_at', 'status'])->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value])
            ->whereNotNull('starts_at')->whereNotNull('ends_at')
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)->get()->groupBy('room_id');

        $bars = $rooms->mapWithKeys(function (Room $room) use ($reservations, $start, $end, $periodDays) {
            $items = ($reservations->get($room->id) ?? collect())->map(function (Reservation $reservation) use ($start, $end, $periodDays) {
                $barStart = $reservation->check_in->lessThan($start) ? $start : $reservation->check_in;
                $barEnd = $reservation->check_out->greaterThan($end) ? $end : $reservation->check_out;
                $offset = $start->diffInDays($barStart);
                $span = max(1, $barStart->diffInDays($barEnd));
                return ['reservation' => $reservation, 'offset' => $offset, 'span' => min($span, $periodDays - $offset)];
            });
            return [$room->id, $items];
        });

        $blocksByRoom = $rooms->mapWithKeys(function (Room $room) use ($blocks, $maintenance, $start, $end, $periodDays) {
            $items = collect();
            foreach (($blocks->get($room->id) ?? collect()) as $block) {
                $items->push($this->periodBar($block->starts_at, $block->ends_at, $start, $end, $periodDays, $block->type?->value ?? 'blocked', $block->reason));
            }
            foreach (($maintenance->get($room->id) ?? collect()) as $task) {
                $items->push($this->periodBar($task->starts_at, $task->ends_at, $start, $end, $periodDays, 'maintenance', $task->issue));
            }
            return [$room->id, $items->filter()->values()];
        });

        $days = collect(range(0, $periodDays - 1))->map(fn (int $day) => $start->copy()->addDays($day));
        $availabilityByCategory = $this->availabilityByCategory($rooms, $days, $reservations, $blocks, $maintenance);

        $weekGroups = collect();
        foreach ($days as $index => $day) {
            $weekKey = $day->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
            $existing = $weekGroups->search(fn (array $week) => $week['key'] === $weekKey);
            if ($existing === false) {
                $weekGroups->push(['key' => $weekKey, 'label' => $day->copy()->startOfWeek(Carbon::MONDAY)->format('d M Y'), 'offset' => $index, 'span' => 1]);
            } else {
                $week = $weekGroups->get($existing);
                $week['span']++;
                $weekGroups->put($existing, $week);
            }
        }

        return view('room-planning.index', [
            'rooms' => $rooms,
            'days' => $days,
            'weekGroups' => $weekGroups,
            'start' => $start,
            'rangeEnd' => $end->copy()->subDay(),
            'daysCount' => $periodDays,
            'periodDays' => $periodDays,
            'view' => $view,
            'bars' => $bars,
            'blocksByRoom' => $blocksByRoom,
            'availabilityByCategory' => $availabilityByCategory,
            'conflictingReservationIds' => $conflictingReservationIds,
            'floors' => Floor::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'categories' => RoomCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'roomTypes' => RoomType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'statuses' => RoomOperationalStatus::cases(),
            'reservationStatuses' => ReservationStatus::cases(),
            'canViewFinancials' => $canViewFinancials,
        ]);
    }

    private function availabilityByCategory(Collection $rooms, Collection $days, Collection $reservations, Collection $blocks, Collection $maintenance): Collection
    {
        $unavailableByRoom = [];
        foreach ($rooms as $room) {
            $unavailableByRoom[$room->id] = [];
        }

        foreach ($reservations->flatten(1)->concat($blocks->flatten(1))->concat($maintenance->flatten(1)) as $interval) {
            $from = $interval->check_in ?? $interval->starts_at;
            $to = $interval->check_out ?? $interval->ends_at;
            if (! $from || ! $to) continue;
            $cursor = Carbon::parse((string) $from)->startOfDay();
            $last = Carbon::parse((string) $to)->startOfDay();
            while ($cursor->lessThan($last)) {
                if (isset($unavailableByRoom[$interval->room_id])) $unavailableByRoom[$interval->room_id][$cursor->toDateString()] = true;
                $cursor->addDay();
            }
        }

        return $rooms->groupBy(fn (Room $room) => $room->category?->name ?? 'Uncategorized')
            ->mapWithKeys(function (Collection $categoryRooms, string $categoryName) use ($days, $unavailableByRoom) {
                return [$categoryName => $days->map(function (Carbon $day) use ($categoryRooms, $unavailableByRoom) {
                    $dateKey = $day->toDateString();
                    return $categoryRooms->filter(fn (Room $room) => $room->operational_status === RoomOperationalStatus::Available && ! isset($unavailableByRoom[$room->id][$dateKey]))->count();
                })->values()];
            });
    }

    private function findConflictingReservationIds(Collection $reservationsByRoom): array
    {
        $conflicts = [];
        foreach ($reservationsByRoom as $roomReservations) {
            $ordered = $roomReservations->sortBy('check_in')->values();
            for ($index = 1; $index < $ordered->count(); $index++) {
                $previous = $ordered[$index - 1];
                $current = $ordered[$index];
                if ($current->check_in->lessThan($previous->check_out)) {
                    $conflicts[] = $previous->id;
                    $conflicts[] = $current->id;
                }
            }
        }
        return array_values(array_unique($conflicts));
    }

    private function periodBar($from, $to, Carbon $start, Carbon $end, int $periodDays, string $type, ?string $label): ?array
    {
        $barStart = $from->lessThan($start) ? $start : $from;
        $barEnd = $to->greaterThan($end) ? $end : $to;
        $offset = $start->diffInDays($barStart);
        $span = max(1, $barStart->copy()->startOfDay()->diffInDays($barEnd->copy()->startOfDay()));

        return $offset >= $periodDays ? null : ['type' => $type, 'label' => $label, 'offset' => $offset, 'span' => min($span, $periodDays - $offset)];
    }

    public function availableRooms(Request $request)
    {
        Gate::authorize('room_planning.view');
        $data = $request->validate(['check_in' => ['required', 'date'], 'check_out' => ['required', 'date', 'after:check_in']]);
        $rooms = $this->availability->findAvailableRooms(Carbon::parse($data['check_in']), Carbon::parse($data['check_out']), $request->integer('ignore_reservation_id') ?: null);
        return response()->json($rooms->map(fn (Room $room) => [
            'id' => $room->id,
            'label' => sprintf('%s · %s · %s', $room->room_number, $room->roomType?->name ?? 'Room', $room->operational_status->label()),
            'rate' => (float) ($room->base_rate ?: $room->roomType?->base_rate ?? 0),
            'capacity' => $room->capacity,
        ])->values());
    }

    public function data(Request $request): JsonResponse
    {
        Gate::authorize('room_planning.view');

        $view = in_array($request->input('view', 'month'), ['day', 'week', 'month', 'two_months'], true)
            ? (string) $request->input('view', 'month')
            : 'month';
        $periodDays = match ($view) {
            'day' => 1,
            'week' => 7,
            'two_months' => 62,
            default => 31,
        };
        $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('start'))
            ? Carbon::createFromFormat('Y-m-d', (string) $request->input('start'), config('app.timezone'))->startOfDay()
            : now()->startOfDay();
        $end = $start->copy()->addDays($periodDays)->startOfDay();
        $reservationSearch = trim((string) $request->input('reservation_search', ''));

        $rooms = Room::query()->active()->select(['id', 'room_number', 'floor_id', 'room_category_id', 'room_type_id', 'operational_status', 'housekeeping_status'])
            ->with(['floor:id,name', 'category:id,name,color', 'roomType:id,name'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.trim((string) $request->input('search')).'%';
                $query->where(function ($roomQuery) use ($term) {
                    $roomQuery->where('room_number', 'like', $term)
                        ->orWhereHas('roomType', fn ($typeQuery) => $typeQuery->where('name', 'like', $term))
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', $term));
                });
            })
            ->when($request->filled('floor_id') && $request->input('floor_id') !== 'all', fn ($query) => $query->where('floor_id', $request->integer('floor_id')))
            ->when($request->filled('category_id') && $request->input('category_id') !== 'all', fn ($query) => $query->where('room_category_id', $request->integer('category_id')))
            ->when($request->filled('room_type_id') && $request->input('room_type_id') !== 'all', fn ($query) => $query->where('room_type_id', $request->integer('room_type_id')))
            ->when($request->filled('status') && $request->input('status') !== 'all', fn ($query) => $query->where('operational_status', (string) $request->input('status')))
            ->orderBy('room_category_id')->orderBy('room_type_id')->orderBy('floor_id')->orderBy('room_number')
            ->get();

        $reservations = Reservation::query()->select(['id', 'code', 'client_id', 'room_id', 'check_in', 'check_out', 'adults', 'children', 'status'])->blockingAvailability()
            ->where('check_in', '<', $end)->where('check_out', '>', $start)
            ->when($request->filled('reservation_status') && $request->input('reservation_status') !== 'all', fn ($query) => $query->where('status', (string) $request->input('reservation_status')))
            ->when($reservationSearch !== '', function ($query) use ($reservationSearch) {
                $term = '%'.$reservationSearch.'%';
                $query->where(function ($reservationQuery) use ($term) {
                    $reservationQuery->where('code', 'like', $term)
                        ->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term));
                });
            })
            ->with(['client:id,first_name,middle_name,last_name', 'room:id,room_number,room_type_id', 'room.roomType:id,name'])
            ->orderBy('check_in')
            ->get();

        $blocks = RoomBlock::query()->select(['id', 'room_id', 'type', 'reason', 'starts_at', 'ends_at'])->where('is_active', true)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->get();
        $maintenance = MaintenanceTask::query()->select(['id', 'room_id', 'issue', 'starts_at', 'ends_at', 'status'])->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value])
            ->whereNotNull('starts_at')->whereNotNull('ends_at')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->get();

        return response()->json([
            'data' => [
                'view' => $view,
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'days' => $periodDays,
                'rooms' => $rooms->map(fn (Room $room) => [
                    'id' => $room->id,
                    'room_number' => $room->room_number,
                    'floor' => $room->floor?->name,
                    'category' => $room->category?->name,
                    'category_color' => $room->category?->color,
                    'room_type' => $room->roomType?->name,
                    'operational_status' => $room->operational_status->value,
                    'housekeeping_status' => $room->housekeeping_status->value,
                ])->values(),
                'reservations' => $reservations->map(fn (Reservation $reservation) => [
                    'id' => $reservation->id,
                    'code' => $reservation->code,
                    'guest' => $reservation->client?->full_name,
                    'room_id' => $reservation->room_id,
                    'room_number' => $reservation->room?->room_number,
                    'room_type' => $reservation->room?->roomType?->name,
                    'status' => $reservation->status->value,
                    'check_in' => $reservation->check_in->toIso8601String(),
                    'check_out' => $reservation->check_out->toIso8601String(),
                    'nights' => $reservation->nights(),
                    'adults' => $reservation->adults,
                    'children' => $reservation->children,
                ])->values(),
                'blocks' => $blocks->map(fn (RoomBlock $block) => [
                    'id' => $block->id,
                    'room_id' => $block->room_id,
                    'type' => $block->type?->value ?? 'blocked',
                    'label' => $block->reason,
                    'starts_at' => $block->starts_at->toIso8601String(),
                    'ends_at' => $block->ends_at->toIso8601String(),
                ])->values(),
                'maintenance' => $maintenance->map(fn (MaintenanceTask $task) => [
                    'id' => $task->id,
                    'room_id' => $task->room_id,
                    'type' => 'maintenance',
                    'label' => $task->issue,
                    'starts_at' => $task->starts_at->toIso8601String(),
                    'ends_at' => $task->ends_at->toIso8601String(),
                ])->values(),
            ],
        ]);
    }
}
