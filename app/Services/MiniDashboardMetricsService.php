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
        $total = User::query()->count();
        $active = User::query()->where('is_active', true)->count();
        $departments = Department::query()->where('is_active', true)->count();
        $recent = User::query()->whereNotNull('last_login_at')->where('last_login_at', '>=', $this->now()->subDay())->count();

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
        $pending = HousekeepingTask::query()->whereIn('status', [TaskStatus::New->value, TaskStatus::Pending->value])->count();
        $inProgress = HousekeepingTask::query()->where('status', TaskStatus::InProgress->value)->count();
        $completedToday = HousekeepingTask::query()->where('status', TaskStatus::Completed->value)->whereBetween('completed_at', $this->todayBounds())->count();
        $total = HousekeepingTask::query()->whereIn('status', $activeStatuses)->count() + $completedToday;

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
        $open = fn (Builder $query) => $query->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value]);
        $openCount = MaintenanceTask::query()->tap($open)->count();
        $inProgress = MaintenanceTask::query()->where('status', TaskStatus::InProgress->value)->count();
        $highPriority = MaintenanceTask::query()->tap($open)->whereIn('priority', [TaskPriority::High->value, TaskPriority::Urgent->value])->count();
        $overdue = MaintenanceTask::query()->tap($open)->whereNotNull('due_at')->where('due_at', '<', $this->now())->count();

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

        return [
            $this->card('Total reservations', Reservation::query()->whereIn('status', $valid)->count(), 'calendar', 'info', 'Current valid bookings'),
            $this->card('Arrivals today', Reservation::query()->whereIn('status', $arrivals)->whereBetween('check_in', $today)->count(), 'arrow-right', 'warning', 'Expected arrivals today', route('reservations.index', ['from' => $this->now()->toDateString(), 'to' => $this->now()->toDateString()])),
            $this->card('In-house', Reservation::query()->where('status', ReservationStatus::CheckedIn->value)->count(), 'bed', 'success', 'Guests currently staying'),
            $this->card('Departures today', Reservation::query()->whereIn('status', $departures)->whereBetween('check_out', $today)->count(), 'logout', 'info', 'Expected check-outs today'),
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
