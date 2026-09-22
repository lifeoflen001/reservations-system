<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\HousekeepingTask;
use App\Models\MaintenanceTask;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->string('q'));
        $like = '%'.$term.'%';
        $groups = [];

        if ($term !== '') {
            $pages = $this->pages($request->user(), $term);
            if ($pages !== []) {
                $groups[] = ['label' => 'Pages', 'icon' => 'grid', 'items' => $pages];
            }

            if ($request->user()->hasPermission('reservations.view')) {
                $items = Reservation::query()->with(['client', 'room'])->where(function (Builder $query) use ($like): void {
                    $query->where('code', 'like', $like)
                        ->orWhereHas('client', fn (Builder $client) => $client->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('email', 'like', $like))
                        ->orWhereHas('room', fn (Builder $room) => $room->where('room_number', 'like', $like));
                })->latest('id')->limit(8)->get()->map(fn (Reservation $reservation): array => [
                    'title' => $reservation->code,
                    'subtitle' => trim(($reservation->client?->full_name ?? 'Guest not assigned').' · Room '.($reservation->room?->room_number ?? '—')),
                    'meta' => $reservation->status?->label() ?? 'Reservation',
                    'url' => route('reservations.show', $reservation),
                ])->all();
                if ($items !== []) {
                    $groups[] = ['label' => 'Reservations', 'icon' => 'calendar', 'items' => $items];
                }
            }

            if ($request->user()->hasPermission('clients.view')) {
                $items = Client::query()->where(function (Builder $query) use ($like): void {
                    $query->where('first_name', 'like', $like)->orWhere('middle_name', 'like', $like)->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('city', 'like', $like)->orWhere('country', 'like', $like);
                })->latest('id')->limit(8)->get()->map(fn (Client $client): array => [
                    'title' => $client->full_name,
                    'subtitle' => $client->email ?: ($client->phone ?: 'Guest profile'),
                    'meta' => implode(', ', array_filter([$client->city, $client->country])) ?: 'Client',
                    'url' => route('clients.show', $client),
                ])->all();
                if ($items !== []) {
                    $groups[] = ['label' => 'Clients', 'icon' => 'users', 'items' => $items];
                }
            }

            if ($request->user()->hasPermission('rooms.view')) {
                $items = Room::query()->with(['roomType', 'category', 'floor'])->where(function (Builder $query) use ($like): void {
                    $query->where('room_number', 'like', $like)->orWhereHas('roomType', fn (Builder $type) => $type->where('name', 'like', $like))->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like));
                })->orderBy('room_number')->limit(8)->get()->map(fn (Room $room): array => [
                    'title' => 'Room '.$room->room_number,
                    'subtitle' => trim(($room->roomType?->name ?? 'Room').' · '.($room->floor?->name ?? 'No floor')),
                    'meta' => Str::headline($room->operational_status?->value ?? 'available'),
                    'url' => route('rooms.show', $room),
                ])->all();
                if ($items !== []) {
                    $groups[] = ['label' => 'Rooms', 'icon' => 'bed', 'items' => $items];
                }
            }

            if ($request->user()->hasPermission('staff.view')) {
                $items = User::query()->with(['role', 'department'])->where(function (Builder $query) use ($like): void {
                    $query->where('name', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('username', 'like', $like)->orWhere('email', 'like', $like);
                })->latest('id')->limit(8)->get()->map(fn (User $staff): array => [
                    'title' => $staff->display_name,
                    'subtitle' => trim(($staff->roleLabel() ?? 'Staff member').' · '.($staff->department?->name ?? 'No department')),
                    'meta' => $staff->email,
                    'url' => route('staff.show', $staff),
                ])->all();
                if ($items !== []) {
                    $groups[] = ['label' => 'Staff', 'icon' => 'users', 'items' => $items];
                }
            }

            if ($request->user()->hasPermission('payments.view')) {
                $items = Payment::query()->with(['reservation', 'client', 'invoice'])->where(function (Builder $query) use ($like): void {
                    $query->where('invoice_number', 'like', $like)->orWhere('reference', 'like', $like)
                        ->orWhereHas('reservation', fn (Builder $reservation) => $reservation->where('code', 'like', $like))
                        ->orWhereHas('client', fn (Builder $client) => $client->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like));
                })->latest('id')->limit(8)->get()->map(fn (Payment $payment): array => [
                    'title' => $payment->invoice_number ?: 'Payment #'.$payment->id,
                    'subtitle' => trim(($payment->client?->full_name ?? 'Guest not assigned').' · '.($payment->reservation?->code ?? 'No reservation')),
                    'meta' => '$'.number_format((float) $payment->amount, 2).' · '.Str::headline($payment->status?->value ?? 'pending'),
                    'url' => $payment->invoice ? route('invoices.show', $payment->invoice) : route('payments.index', ['search' => $payment->invoice_number ?: $payment->reference]),
                ])->all();
                if ($items !== []) {
                    $groups[] = ['label' => 'Payments', 'icon' => 'card', 'items' => $items];
                }
            }

            if ($request->user()->hasPermission('housekeeping.view')) {
                $items = HousekeepingTask::query()->with('room')->where(function (Builder $query) use ($like): void {
                    $query->where('task_type', 'like', $like)->orWhere('notes', 'like', $like)->orWhereHas('room', fn (Builder $room) => $room->where('room_number', 'like', $like));
                })->latest('id')->limit(8)->get()->map(fn (HousekeepingTask $task): array => [
                    'title' => Str::headline($task->task_type).' · Room '.($task->room?->room_number ?? '—'),
                    'subtitle' => $task->notes ?: 'Housekeeping task',
                    'meta' => Str::headline($task->status?->value ?? 'pending'),
                    'url' => route('housekeeping.index'),
                ])->all();
                if ($items !== []) {
                    $groups[] = ['label' => 'Housekeeping', 'icon' => 'broom', 'items' => $items];
                }
            }

            if ($request->user()->hasPermission('maintenance.view')) {
                $items = MaintenanceTask::query()->with('room')->where(function (Builder $query) use ($like): void {
                    $query->where('issue', 'like', $like)->orWhere('description', 'like', $like)->orWhere('notes', 'like', $like)->orWhereHas('room', fn (Builder $room) => $room->where('room_number', 'like', $like));
                })->latest('id')->limit(8)->get()->map(fn (MaintenanceTask $task): array => [
                    'title' => $task->issue,
                    'subtitle' => 'Room '.($task->room?->room_number ?? '—').' · '.($task->description ?: 'Maintenance task'),
                    'meta' => Str::headline($task->status?->value ?? 'pending'),
                    'url' => route('maintenance.index'),
                ])->all();
                if ($items !== []) {
                    $groups[] = ['label' => 'Maintenance', 'icon' => 'wrench', 'items' => $items];
                }
            }
        }

        return view('search.index', ['term' => $term, 'groups' => $groups, 'resultCount' => collect($groups)->sum(fn (array $group): int => count($group['items']))]);
    }

    private function pages(User $user, string $term): array
    {
        $pages = [
            ['label' => 'Dashboard', 'description' => 'Live hotel operations overview', 'icon' => 'grid', 'route' => 'dashboard', 'permission' => 'dashboard.view'],
            ['label' => 'Reservations', 'description' => 'Bookings, arrivals and departures', 'icon' => 'calendar', 'route' => 'reservations.index', 'permission' => 'reservations.view'],
            ['label' => 'Room planning', 'description' => 'Availability calendar and room assignments', 'icon' => 'calendar', 'route' => 'room-planning.index', 'permission' => 'room_planning.view'],
            ['label' => 'Clients', 'description' => 'Guest profiles and stay history', 'icon' => 'users', 'route' => 'clients.index', 'permission' => 'clients.view'],
            ['label' => 'Rooms', 'description' => 'Room inventory and operational status', 'icon' => 'bed', 'route' => 'rooms.index', 'permission' => 'rooms.view'],
            ['label' => 'Housekeeping', 'description' => 'Room preparation tasks', 'icon' => 'broom', 'route' => 'housekeeping.index', 'permission' => 'housekeeping.view'],
            ['label' => 'Maintenance', 'description' => 'Incidents, repairs and availability', 'icon' => 'wrench', 'route' => 'maintenance.index', 'permission' => 'maintenance.view'],
            ['label' => 'Staff', 'description' => 'Team accounts, roles and departments', 'icon' => 'users', 'route' => 'staff.index', 'permission' => 'staff.view'],
            ['label' => 'Payments', 'description' => 'Transactions and invoices', 'icon' => 'card', 'route' => 'payments.index', 'permission' => 'payments.view'],
            ['label' => 'Reports', 'description' => 'Revenue, occupancy and operational exports', 'icon' => 'chart', 'route' => 'reports.index', 'permission' => 'reports.view'],
            ['label' => 'Settings', 'description' => 'Property and system configuration', 'icon' => 'settings', 'route' => 'settings.index', 'permission' => 'settings.view'],
        ];
        $needle = Str::lower($term);

        return collect($pages)->filter(fn (array $page): bool => $user->hasPermission($page['permission']) && str_contains(Str::lower($page['label'].' '.$page['description']), $needle))->map(fn (array $page): array => [
            'title' => $page['label'],
            'subtitle' => $page['description'],
            'meta' => 'Open page',
            'url' => route($page['route']),
            'icon' => $page['icon'],
        ])->values()->all();
    }
}
