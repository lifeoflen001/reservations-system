<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Department;
use App\Models\HousekeepingTask;
use App\Models\MaintenanceTask;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Support\CurrencyFormatter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class MiniDashboardMetricsService
{
    public function __construct(
        private readonly FinancialService $financials,
        private readonly PropertySettingsService $propertySettings,
        private readonly CurrencyFormatter $currency,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function staff(): array
    {
        $recentCutoff = $this->now()->subDay();
        $summary = User::query()->selectRaw(
            'COUNT(*) as total, SUM(CASE WHEN is_active = ? THEN 1 ELSE 0 END) as active, SUM(CASE WHEN last_login_at IS NOT NULL AND last_login_at >= ? THEN 1 ELSE 0 END) as recent',
            [true, $recentCutoff],
        )->first();
        $total = (int) ($summary->total ?? 0);
        $active = (int) ($summary->active ?? 0);
        $departments = Department::query()->where('is_active', true)->count();
        $recent = (int) ($summary->recent ?? 0);

        return [
            $this->card('Total staff', $total, 'users', 'info', "Across {$departments} active departments"),
            $this->card('Active staff', $active, 'check', 'success', ($total - $active).' inactive'),
            $this->card('Departments', $departments, 'building', 'info', 'Operational departments'),
            $this->card('Recently active', $recent, 'history', 'warning', 'Logged in during the last 24 hours'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function housekeeping(): array
    {
        $activeStatuses = [TaskStatus::New->value, TaskStatus::Pending->value, TaskStatus::InProgress->value, TaskStatus::OnHold->value];
        [$todayStart, $todayEnd] = $this->todayBounds();
        $summary = HousekeepingTask::query()->selectRaw(
            'SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress, SUM(CASE WHEN status = ? AND completed_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as completed_today, SUM(CASE WHEN status IN (?, ?, ?, ?) THEN 1 ELSE 0 END) as active',
            [TaskStatus::New->value, TaskStatus::Pending->value, TaskStatus::InProgress->value, TaskStatus::Completed->value, $todayStart, $todayEnd, ...$activeStatuses],
        )->first();
        $pending = (int) ($summary->pending ?? 0);
        $inProgress = (int) ($summary->in_progress ?? 0);
        $completedToday = (int) ($summary->completed_today ?? 0);
        $total = (int) ($summary->active ?? 0) + $completedToday;

        return [
            $this->card('Total tasks', $total, 'broom', 'info', 'Current operational workload'),
            $this->card('Pending cleaning', $pending, 'history', 'warning', 'Rooms waiting', route('housekeeping.index', ['status' => 'pending'])),
            $this->card('In progress', $inProgress, 'broom', 'info', 'Being serviced', route('housekeeping.index', ['status' => 'in_progress'])),
            $this->card('Completed today', $completedToday, 'check', 'success', 'Rooms prepared'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function maintenance(): array
    {
        $now = $this->now();
        $summary = MaintenanceTask::query()->selectRaw(
            'SUM(CASE WHEN status NOT IN (?, ?) THEN 1 ELSE 0 END) as open_count, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress, SUM(CASE WHEN status NOT IN (?, ?) AND priority IN (?, ?) THEN 1 ELSE 0 END) as high_priority, SUM(CASE WHEN status NOT IN (?, ?) AND due_at IS NOT NULL AND due_at < ? THEN 1 ELSE 0 END) as overdue',
            [TaskStatus::Completed->value, TaskStatus::Cancelled->value, TaskStatus::InProgress->value, TaskStatus::Completed->value, TaskStatus::Cancelled->value, TaskPriority::High->value, TaskPriority::Urgent->value, TaskStatus::Completed->value, TaskStatus::Cancelled->value, $now],
        )->first();
        $openCount = (int) ($summary->open_count ?? 0);
        $inProgress = (int) ($summary->in_progress ?? 0);
        $highPriority = (int) ($summary->high_priority ?? 0);
        $overdue = (int) ($summary->overdue ?? 0);

        return [
            $this->card('Open issues', $openCount, 'wrench', 'info', 'Not completed or cancelled'),
            $this->card('In progress', $inProgress, 'wrench', 'warning', 'Current repair work', route('maintenance.index', ['status' => 'in_progress'])),
            $this->card('High priority', $highPriority, 'alert', 'warning', 'Requires attention'),
            $this->card('Overdue', $overdue, 'alert', 'danger', 'Past due and still open', route('maintenance.index', ['overdue' => 1])),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function payments(): array
    {
        $today = $this->todayBounds();
        $summary = Payment::query()->successful()->selectRaw(
            'COALESCE(SUM(amount), 0) as total_collected, COUNT(*) as transactions, COALESCE(SUM(CASE WHEN transaction_date BETWEEN ? AND ? THEN amount ELSE 0 END), 0) as today_amount, SUM(CASE WHEN transaction_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as today_transactions',
            [$today[0], $today[1], $today[0], $today[1]],
        )->first();
        $pending = Payment::query()->where('status', PaymentStatus::Pending->value)->count();
        $outstanding = $this->financials->outstandingBalance();

        return [
            $this->card('Total collected', $this->currency->format($summary->total_collected ?? 0), 'currency', 'success', 'All successful payments'),
            $this->card("Today's payments", $this->currency->format($summary->today_amount ?? 0), 'card', 'info', ((int) ($summary->today_transactions ?? 0)).' transactions'),
            $this->card('Outstanding', $this->currency->format($outstanding), 'alert', 'danger', 'Open reservation balances'),
            $this->card('Transactions', (int) ($summary->transactions ?? 0), 'document', 'info', $pending.' pending'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function clients(): array
    {
        $qualifying = [ReservationStatus::CheckedIn->value, ReservationStatus::CheckedOut->value];
        $future = [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value];
        $now = $this->now();
        $total = Client::query()->count();
        $inHouse = Client::query()->whereHas('reservations', fn (Builder $query) => $query->where('status', ReservationStatus::CheckedIn->value))->count();
        $upcoming = Client::query()->whereHas('reservations', function (Builder $query) use ($future, $now): void {
            $query->whereIn('status', $future)->where('check_in', '>', $now);
        })->count();
        $returning = Client::query()->whereIn('id', Reservation::query()->select('client_id')->whereNotNull('client_id')->whereIn('status', $qualifying)->groupBy('client_id')->havingRaw('COUNT(*) > 1'))->count();

        return [
            $this->card('Total clients', $total, 'users', 'info', 'Guest profiles'),
            $this->card('In-house guests', $inHouse, 'bed', 'success', 'Currently checked in'),
            $this->card('Upcoming guests', $upcoming, 'calendar', 'warning', 'Future pending or confirmed stays'),
            $this->card('Returning guests', $returning, 'history', 'info', 'Clients with 2 or more stays'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function reservations(): array
    {
        $today = $this->todayBounds();
        $valid = [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value, ReservationStatus::CheckedIn->value, ReservationStatus::CheckedOut->value];
        $arrivals = [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value, ReservationStatus::CheckedIn->value];
        $departures = [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value, ReservationStatus::CheckedIn->value, ReservationStatus::CheckedOut->value];

        $summary = Reservation::query()->selectRaw(
            'SUM(CASE WHEN status IN (?, ?, ?, ?) THEN 1 ELSE 0 END) as total, SUM(CASE WHEN status IN (?, ?, ?) AND check_in BETWEEN ? AND ? THEN 1 ELSE 0 END) as arrivals, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_house, SUM(CASE WHEN status IN (?, ?, ?, ?) AND check_out BETWEEN ? AND ? THEN 1 ELSE 0 END) as departures',
            [...$valid, ...$arrivals, $today[0], $today[1], ReservationStatus::CheckedIn->value, ...$departures, $today[0], $today[1]],
        )->first();

        return [
            $this->card('Total reservations', (int) ($summary->total ?? 0), 'calendar', 'info', 'Current valid bookings'),
            $this->card('Arrivals today', (int) ($summary->arrivals ?? 0), 'arrow-right', 'warning', 'Expected arrivals today', route('reservations.index', ['from' => $this->now()->toDateString(), 'to' => $this->now()->toDateString()])),
            $this->card('In-house', (int) ($summary->in_house ?? 0), 'bed', 'success', 'Guests currently staying'),
            $this->card('Departures today', (int) ($summary->departures ?? 0), 'logout', 'info', 'Expected check-outs today'),
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function todayBounds(): array
    {
        $today = $this->now()->startOfDay();
        return [$today, $today->copy()->endOfDay()];
    }

    private function now(): Carbon
    {
        return now($this->propertySettings->timezone());
    }

    private function card(string $label, mixed $value, string $icon, string $tone, string $context, ?string $href = null): array
    {
        return compact('label', 'value', 'icon', 'tone', 'context', 'href');
    }
}
