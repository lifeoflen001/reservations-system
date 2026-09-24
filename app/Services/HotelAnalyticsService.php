<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\RoomOperationalStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\HousekeepingTask;
use App\Models\MaintenanceTask;
use App\Models\Payment;
use App\Models\PosOrder;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\Task;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Support\TablePagination;

class HotelAnalyticsService
{
    public function __construct(private readonly FinancialService $financials) {}

    public function dashboard(string $range = 'daily', ?CarbonInterface $from = null, ?CarbonInterface $to = null, bool $includeFinancial = true): array
    {
        $today = $this->propertyNow()->startOfDay();
        $dayEnd = $today->copy()->endOfDay();
        $sellableStatuses = [RoomOperationalStatus::Available->value, RoomOperationalStatus::Reserved->value, RoomOperationalStatus::Occupied->value, RoomOperationalStatus::MustClean->value];
        $totalSellableRooms = Room::query()->active()->whereIn('operational_status', $sellableStatuses)->count();
        $occupiedRooms = Room::query()->active()->where('operational_status', RoomOperationalStatus::Occupied->value)->count();
        $reservationsToday = Reservation::query()->whereBetween('created_at', [$today, $dayEnd])->count();
        $arrivalsToday = Reservation::query()->blockingAvailability()->whereDate('check_in', $today->toDateString())->count();
        $departuresToday = Reservation::query()->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value, ReservationStatus::CheckedIn->value, ReservationStatus::CheckedOut->value])->whereDate('check_out', $today->toDateString())->count();
        $outstanding = $includeFinancial ? $this->financials->outstandingBalance() : 0;
        $paymentContext = $includeFinancial ? 'Outstanding' : null;

        $metrics = [
            ['label' => 'Reservations today', 'value' => (string) $reservationsToday, 'hint' => 'New bookings', 'icon' => 'calendar', 'tone' => 'info'],
            ['label' => 'Check-ins today', 'value' => (string) $arrivalsToday, 'hint' => 'Arrivals', 'icon' => 'check', 'tone' => 'success'],
            ['label' => 'Check-outs today', 'value' => (string) $departuresToday, 'hint' => 'Departures', 'icon' => 'arrow-right', 'tone' => 'danger'],
            ['label' => 'Occupancy', 'value' => ($totalSellableRooms ? round($occupiedRooms / $totalSellableRooms * 100) : 0).'%', 'hint' => $occupiedRooms.'/'.$totalSellableRooms.' Rooms', 'icon' => 'bed', 'tone' => 'info'],
        ];
        if ($includeFinancial) {
            $posToday = (float) PosOrder::query()->where('status', 'completed')->whereBetween('completed_at', [$today, $dayEnd])->sum('total');
            $metrics[] = ['label' => 'Revenue today', 'value' => $this->money($this->financials->collectedBetween($today, $dayEnd) + $posToday), 'hint' => 'Collected + POS', 'icon' => 'currency', 'tone' => 'warning'];
            $metrics[] = ['label' => 'Payments due', 'value' => $this->money($outstanding), 'hint' => $paymentContext, 'icon' => 'card', 'tone' => 'danger'];
        }

        $chartRange = $this->dashboardRange($range, $from, $to);
        return [
            'metrics' => $metrics,
            'statusCounts' => $this->roomStatusCounts(),
            'roomTotal' => (int) Room::query()->active()->count(),
            'recentReservations' => Reservation::query()->with(['client', 'room'])->latest('created_at')->orderByDesc('reservations.id')->limit(5)->get(),
            'upcomingTasks' => $this->upcomingTasks(),
            'chart' => $this->trend($chartRange[0], $chartRange[1], $range, $includeFinancial),
            'range' => $range,
            'rangeFrom' => $chartRange[0],
            'rangeTo' => $chartRange[1],
            'financialVisible' => $includeFinancial,
        ];
    }

    public function report(CarbonInterface $from, CarbonInterface $to, bool $includeFinancial = true): array
    {
        $from = Carbon::instance($from)->startOfDay();
        $to = Carbon::instance($to)->endOfDay();
        $reservations = $this->reportReservationsQuery($from, $to)->paginate(TablePagination::perPage(request(), 25))->withQueryString();
        $roomNights = $this->overlappingRoomNights($from, $to);
        $availableRoomNights = $this->availableRoomNights($from, $to);
        $revenue = $includeFinancial ? $this->financials->collectedBetween($from, $to) + (float) PosOrder::query()->where('status', 'completed')->whereBetween('completed_at', [$from, $to])->sum('total') : 0;
        $sourceCounts = $this->sourceCounts($from, $to);
        $roomTypeRevenue = $includeFinancial ? $this->roomTypeRevenue($from, $to) : collect();
        $reportOutstanding = $includeFinancial ? $this->financials->outstandingBalance($this->reportReservationsBaseQuery($from, $to)) : 0;

        return [
            'reservations' => $reservations,
            'revenue' => (float) $revenue,
            'roomNights' => $roomNights,
            'availableRoomNights' => $availableRoomNights,
            'occupancy' => $availableRoomNights > 0 ? round($roomNights / $availableRoomNights * 100, 1) : 0,
            'adr' => $roomNights > 0 ? round($revenue / $roomNights, 2) : 0,
            'revpar' => $availableRoomNights > 0 ? round($revenue / $availableRoomNights, 2) : 0,
            'outstanding' => (float) $reportOutstanding,
            'sourceCounts' => $sourceCounts,
            'roomTypeRevenue' => $roomTypeRevenue,
            'financialVisible' => $includeFinancial,
        ];
    }

    public function reportReservationsQuery(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $this->reportReservationsBaseQuery($from, $to)
            ->with(['client', 'room.roomType', 'source'])
            ->withSum(['payments as paid_amount' => fn (Builder $query) => $query->successful()], 'amount')
            ->withSum(['posRoomCharges as room_charge_amount' => fn (Builder $query) => $query->where('status', 'active')], 'amount')
            ->latest('check_in')
            ->orderByDesc('reservations.id');
    }

    public function reportRange(?string $from, ?string $to): array
    {
        $today = $this->propertyNow()->startOfDay();
        $start = $from ? Carbon::createFromFormat('Y-m-d', $from, config('app.timezone'))->startOfDay() : $today->copy()->subDays(30);
        $end = $to ? Carbon::createFromFormat('Y-m-d', $to, config('app.timezone'))->endOfDay() : $today->copy()->endOfDay();
        return [$start, $end];
    }

    public function dashboardRange(string $range, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $today = $this->propertyNow()->startOfDay();
        return match ($range) {
            'weekly' => [$today->copy()->startOfWeek()->subWeeks(7), $today->copy()->endOfWeek()],
            'monthly' => [$today->copy()->startOfMonth()->subMonths(11), $today->copy()->endOfMonth()],
            'yearly' => [$today->copy()->startOfYear()->subYears(4), $today->copy()->endOfYear()],
            'custom' => [Carbon::instance($from ?: $today->copy()->subDays(6))->startOfDay(), Carbon::instance($to ?: $today)->endOfDay()],
            default => [$today->copy()->subDays(6), $today->copy()->endOfDay()],
        };
    }

    private function propertyNow(): Carbon
    {
        return now(config('app.timezone'));
    }

    private function money(float $amount): string
    {
        return app(\App\Support\CurrencyFormatter::class)->format($amount);
    }

    private function reportReservationsBaseQuery(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Reservation::query()->whereNotIn('status', [ReservationStatus::Cancelled->value, ReservationStatus::NoShow->value])
            ->where('check_in', '<', Carbon::instance($to)->startOfDay()->addDay())
            ->where('check_out', '>', Carbon::instance($from)->startOfDay());
    }

    private function roomStatusCounts(): Collection
    {
        return Room::query()->active()->selectRaw('operational_status, COUNT(*) as aggregate')->groupBy('operational_status')->pluck('aggregate', 'operational_status');
    }

    private function upcomingTasks(): Collection
    {
        $now = $this->propertyNow();
        $active = [TaskStatus::New->value, TaskStatus::Pending->value, TaskStatus::InProgress->value, TaskStatus::OnHold->value];
        $generic = Task::query()->with(['room', 'department'])->active()->open()->whereIn('status', $active)
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 WHEN due_at < ? THEN 0 ELSE 1 END', [$now])
            ->orderBy('due_at')->limit(8)->get()
            ->map(fn (Task $task) => $this->taskCard(
                $task->room?->room_number,
                $task->title,
                $task->due_at,
                $task->priority,
                'check-square',
                route('tasks.show', $task)
            ));
        $housekeeping = HousekeepingTask::query()->with('room')->whereIn('status', $active)->orderByRaw('CASE WHEN due_at IS NULL THEN 1 WHEN due_at < ? THEN 0 ELSE 1 END', [$now])->orderBy('due_at')->limit(8)->get()->map(fn (HousekeepingTask $task) => $this->taskCard($task->room?->room_number, 'Prepare room '.($task->room?->room_number ?? ''), $task->due_at, $task->priority, 'broom', route('housekeeping.index', ['edit' => $task->id])));
        $maintenance = MaintenanceTask::query()->with('room')->whereIn('status', $active)->orderByRaw('CASE WHEN due_at IS NULL THEN 1 WHEN due_at < ? THEN 0 ELSE 1 END', [$now])->orderBy('due_at')->limit(8)->get()->map(fn (MaintenanceTask $task) => $this->taskCard($task->room?->room_number, $task->issue, $task->due_at, $task->priority, 'wrench', route('maintenance.index', ['edit' => $task->id])));
        return $generic->concat($housekeeping)->concat($maintenance)->sortBy(fn (array $task) => [$task['overdue'] ? 0 : 1, $task['due_sort'], $task['priority_sort']])->take(5)->values();
    }

    private function taskCard(?string $room, string $title, ?CarbonInterface $due, TaskPriority $priority, string $icon, string $href): array
    {
        $now = $this->propertyNow();
        return ['title' => trim($title), 'detail' => $room ? 'Room '.$room.' · '.($due?->setTimezone(config('app.timezone'))->format('m/d/Y, h:i A') ?? 'No due date') : 'Operational task', 'priority' => $priority->label(), 'priority_sort' => array_search($priority, TaskPriority::cases(), true), 'icon' => $icon, 'href' => $href, 'overdue' => $due?->lessThan($now) ?? false, 'due_sort' => $due?->timestamp ?? PHP_INT_MAX];
    }

    private function trend(CarbonInterface $from, CarbonInterface $to, string $range, bool $includeFinancial): array
    {
        $mode = $range === 'custom' ? $this->customTrendMode($from, $to) : $range;
        $buckets = $this->trendBuckets($from, $to, $mode);
        $reservationDate = $this->databaseDateExpression('created_at', $mode);
        $reservationCounts = Reservation::query()->whereBetween('created_at', [$from, $to])
            ->selectRaw("{$reservationDate} as bucket_key, COUNT(*) as aggregate")
            ->groupBy('bucket_key')->pluck('aggregate', 'bucket_key');
        foreach ($reservationCounts as $key => $count) if (isset($buckets[$key])) $buckets[$key]['reservations'] = (int) $count;

        if ($includeFinancial) {
            $paymentTotals = $this->financials->recognizedPaymentsBetween($from, $to)
                ->groupBy(fn (array $item) => $this->bucketKey(Carbon::instance($item['payment']->transaction_date), $mode))
                ->map(fn (Collection $items) => (float) $items->sum('amount'));
            foreach ($paymentTotals as $key => $amount) if (isset($buckets[$key])) $buckets[$key]['revenue'] = (float) $amount;
            $posTotals = PosOrder::query()->where('status', 'completed')->whereBetween('completed_at', [$from, $to])
                ->selectRaw("{$this->databaseDateExpression('completed_at', $mode)} as bucket_key, COALESCE(SUM(total), 0) as aggregate")
                ->groupBy('bucket_key')->pluck('aggregate', 'bucket_key');
            foreach ($posTotals as $key => $amount) if (isset($buckets[$key])) $buckets[$key]['revenue'] += (float) $amount;
        }
        return ['mode' => $mode, 'items' => array_values($buckets), 'hasData' => collect($buckets)->contains(fn (array $item) => $item['revenue'] > 0 || $item['reservations'] > 0)];
    }

    private function databaseDateExpression(string $column, string $mode): string
    {
        $driver = DB::connection()->getDriverName();
        if ($mode === 'monthly') {
            return match ($driver) {
                'sqlite' => "strftime('%Y-%m', {$column})",
                'pgsql' => "to_char({$column}, 'YYYY-MM')",
                default => "DATE_FORMAT({$column}, '%Y-%m')",
            };
        }
        if ($mode === 'yearly') {
            return match ($driver) {
                'sqlite' => "strftime('%Y', {$column})",
                'pgsql' => "to_char({$column}, 'YYYY')",
                default => "DATE_FORMAT({$column}, '%Y')",
            };
        }
        if ($mode === 'weekly') {
            return match ($driver) {
                'sqlite' => "date({$column}, '-' || ((strftime('%w', {$column}) + 6) % 7) || ' days')",
                'pgsql' => "to_char(date_trunc('week', {$column}), 'YYYY-MM-DD')",
                default => "DATE_SUB(DATE({$column}), INTERVAL WEEKDAY({$column}) DAY)",
            };
        }
        return match ($driver) {
            'sqlite' => "date({$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM-DD')",
            default => "DATE({$column})",
        };
    }

    private function customTrendMode(CarbonInterface $from, CarbonInterface $to): string
    {
        $days = Carbon::instance($from)->startOfDay()->diffInDays(Carbon::instance($to)->startOfDay()) + 1;
        return $days <= 31 ? 'daily' : ($days <= 180 ? 'weekly' : 'monthly');
    }

    private function trendBuckets(CarbonInterface $from, CarbonInterface $to, string $mode): array
    {
        $start = Carbon::instance($from)->startOfDay();
        $end = Carbon::instance($to)->startOfDay();
        if ($mode === 'weekly') $start = $start->startOfWeek();
        if ($mode === 'monthly') $start = $start->startOfMonth();
        if ($mode === 'yearly') $start = $start->startOfYear();
        $buckets = [];
        for ($cursor = $start->copy(); $cursor <= $end; $cursor = $this->nextBucket($cursor, $mode)) {
            $key = $this->bucketKey($cursor, $mode);
            $buckets[$key] = ['key' => $key, 'label' => $this->bucketLabel($cursor, $mode), 'revenue' => 0.0, 'reservations' => 0];
        }
        return $buckets;
    }

    private function nextBucket(Carbon $date, string $mode): Carbon
    {
        return match ($mode) { 'weekly' => $date->copy()->addWeek(), 'monthly' => $date->copy()->addMonth(), 'yearly' => $date->copy()->addYear(), default => $date->copy()->addDay() };
    }

    private function bucketKey(CarbonInterface $date, string $mode): string
    {
        $date = Carbon::instance($date);
        return match ($mode) { 'weekly' => $date->copy()->startOfWeek()->toDateString(), 'monthly' => $date->format('Y-m'), 'yearly' => $date->format('Y'), default => $date->toDateString() };
    }

    private function bucketLabel(CarbonInterface $date, string $mode): string
    {
        return match ($mode) { 'weekly' => Carbon::instance($date)->format('d.m'), 'monthly' => Carbon::instance($date)->format('M Y'), 'yearly' => Carbon::instance($date)->format('Y'), default => Carbon::instance($date)->format('d.m') };
    }

    private function overlappingRoomNights(CarbonInterface $from, CarbonInterface $to): int
    {
        $from = Carbon::instance($from)->startOfDay();
        $to = Carbon::instance($to)->startOfDay()->addDay();
        $nights = 0;
        foreach ($this->reportReservationsBaseQuery($from, $to->copy()->subSecond())->select(['check_in', 'check_out'])->cursor() as $row) {
            $checkIn = Carbon::parse((string) $row->check_in, config('app.timezone'))->startOfDay()->max($from);
            $checkOut = Carbon::parse((string) $row->check_out, config('app.timezone'))->startOfDay()->min($to);
            $nights += max(0, $checkIn->diffInDays($checkOut));
        }
        return $nights;
    }

    private function availableRoomNights(CarbonInterface $from, CarbonInterface $to): int
    {
        $from = Carbon::instance($from)->startOfDay();
        $to = Carbon::instance($to)->startOfDay()->addDay();
        $days = $from->diffInDays($to);
        $roomIds = Room::query()->active()
            ->whereNotIn('operational_status', [
                RoomOperationalStatus::Maintenance->value,
                RoomOperationalStatus::Blocked->value,
            ])
            ->pluck('id');
        $blockedDays = 0.0;
        $intervals = RoomBlock::query()->whereIn('room_id', $roomIds)->where('is_active', true)->where('starts_at', '<', $to)->where('ends_at', '>', $from)->get(['room_id', 'starts_at', 'ends_at']);
        $maintenance = MaintenanceTask::query()->whereIn('room_id', $roomIds)->whereNotNull('starts_at')->where('starts_at', '<', $to)->where(function (Builder $query) use ($from) { $query->whereNull('ends_at')->orWhere('ends_at', '>', $from); })->get(['room_id', 'starts_at', 'ends_at']);
        foreach ($intervals->concat($maintenance)->groupBy('room_id') as $roomIntervals) {
            $merged = [];
            foreach ($roomIntervals->sortBy('starts_at') as $interval) {
                $start = Carbon::parse((string) $interval->starts_at, config('app.timezone'))->max($from);
                $end = $interval->ends_at ? Carbon::parse((string) $interval->ends_at, config('app.timezone'))->min($to) : $to;
                if ($end->lessThanOrEqualTo($start)) continue;
                if ($merged && $start->lessThanOrEqualTo($merged[count($merged) - 1][1])) $merged[count($merged) - 1][1] = $merged[count($merged) - 1][1]->max($end);
                else $merged[] = [$start, $end];
            }
            $blockedDays += array_sum(array_map(fn (array $pair) => $pair[0]->startOfDay()->diffInDays($pair[1]->startOfDay()), $merged));
        }
        return max(0, (int) ($roomIds->count() * $days - $blockedDays));
    }

    private function sourceCounts(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->reportReservationsBaseQuery($from, $to)->leftJoin('reservation_sources', 'reservation_sources.id', '=', 'reservations.reservation_source_id')->selectRaw("COALESCE(reservation_sources.name, 'Direct') as source_name, COUNT(reservations.id) as aggregate")->groupBy('source_name')->orderByDesc('aggregate')->pluck('aggregate', 'source_name');
    }

    private function roomTypeRevenue(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $payments = $this->financials->recognizedPaymentsBetween($from, $to);
        $payments->each(fn (array $item) => $item['payment']->loadMissing('reservation.room.roomType'));
        return $payments
            ->groupBy(fn (array $item) => $item['payment']->reservation?->room?->roomType?->name ?? 'Room')
            ->map(fn (Collection $items) => (float) $items->sum('amount'))
            ->sortDesc();
    }
}
