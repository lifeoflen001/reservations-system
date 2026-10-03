<?php

namespace App\Services\Platform;

use App\Models\Client;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\HousekeepingTask;
use App\Models\MaintenanceTask;
use App\Models\PosOrder;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class PlatformSupportQueryService
{
    public function __construct(private readonly PlatformSupportContext $context) {}

    public function dashboard(): array
    {
        if (! $this->hasProperty()) {
            return ['metrics' => ['reservations' => 0, 'clients' => 0, 'rooms' => 0, 'open_tasks' => 0], 'recentReservations' => collect(), 'organizationOnly' => true];
        }

        return [
            'metrics' => [
                'reservations' => $this->query(Reservation::class)->count(),
                'clients' => $this->query(Client::class)->count(),
                'rooms' => $this->query(Room::class)->count(),
                'open_tasks' => $this->query(Task::class)->whereNotIn('status', ['completed', 'cancelled'])->count(),
            ],
            'recentReservations' => $this->query(Reservation::class)->with(['client', 'room'])->latest()->limit(8)->get(),
            'organizationOnly' => false,
        ];
    }

    public function reservations(?string $search = null): LengthAwarePaginator|Collection
    {
        if (! $this->hasProperty()) return collect();

        return $this->query(Reservation::class)->with(['client', 'room'])->when($search, function (Builder $query) use ($search): void {
            $term = '%'.str_replace('%', '', $search).'%';
            $query->where(function (Builder $nested) use ($term): void {
                $nested->where('code', 'like', $term)->orWhereHas('client', fn (Builder $client): Builder => $client->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term));
            });
        })->latest()->paginate(20)->withQueryString();
    }

    public function reservation(int $id): Reservation
    {
        return $this->query(Reservation::class)->with(['client', 'room', 'payments', 'posOrders'])->whereKey($id)->firstOrFail();
    }

    public function clients(?string $search = null): LengthAwarePaginator|Collection
    {
        if (! $this->hasProperty()) return collect();

        return $this->query(Client::class)->when($search, fn (Builder $query): Builder => $query->where(function (Builder $nested) use ($search): void { $term = '%'.str_replace('%', '', $search).'%'; $nested->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('email', 'like', $term); }))->latest()->paginate(20)->withQueryString();
    }

    public function client(int $id): Client
    {
        return $this->query(Client::class)->withCount(['reservations', 'posOrders'])->whereKey($id)->firstOrFail();
    }

    public function rooms(): Collection
    {
        if (! $this->hasProperty()) return collect();

        return $this->query(Room::class)->with(['roomType', 'category'])->orderBy('room_number')->get();
    }

    public function roomPlanning(): array
    {
        if (! $this->hasProperty()) return ['rooms' => collect(), 'reservations' => collect(), 'blocks' => collect()];

        return ['rooms' => $this->rooms(), 'reservations' => $this->query(Reservation::class)->with(['client', 'room'])->blockingAvailability()->orderBy('check_in')->get(), 'blocks' => $this->query(RoomBlock::class)->with('room')->whereDate('starts_at', '<=', now()->addDays(30))->whereDate('ends_at', '>=', now())->get()];
    }

    public function tasks(): Collection
    {
        return $this->hasProperty() ? $this->query(Task::class)->with(['room', 'department'])->active()->latest()->limit(100)->get() : collect();
    }

    public function housekeeping(): Collection
    {
        return $this->hasProperty() ? $this->query(HousekeepingTask::class)->with('room')->latest()->limit(100)->get() : collect();
    }

    public function maintenance(): Collection
    {
        return $this->hasProperty() ? $this->query(MaintenanceTask::class)->with('room')->latest()->limit(100)->get() : collect();
    }

    public function pos(): Collection
    {
        return $this->hasProperty() ? $this->query(PosOrder::class)->with(['outlet', 'client'])->latest()->limit(100)->get() : collect();
    }

    public function finance(): array
    {
        if (! $this->hasProperty()) return ['accounts' => collect(), 'transactions' => collect()];

        return ['accounts' => $this->query(FinancialAccount::class)->orderBy('name')->get(), 'transactions' => $this->query(FinancialTransaction::class)->with('account')->latest('transaction_date')->limit(100)->get()];
    }

    public function reports(): array
    {
        $dashboard = $this->dashboard();
        $finance = $this->finance();

        return ['metrics' => $dashboard['metrics'], 'money_in' => collect($finance['transactions'])->where('direction', 'in')->sum(fn ($transaction): float => (float) $transaction->amount), 'money_out' => collect($finance['transactions'])->where('direction', 'out')->sum(fn ($transaction): float => (float) $transaction->amount)];
    }

    public function settings(): array
    {
        $property = $this->context->current()->property;
        if (! $property) return ['property' => null, 'settings' => []];

        return ['property' => $property, 'settings' => [
            'timezone' => $property->timezone,
            'currency' => $property->baseCurrency?->code,
            'check_in_time' => $property->check_in_time,
            'check_out_time' => $property->check_out_time,
            'email' => $property->email,
            'phone' => $property->phone,
            'credentials' => 'Redacted — support mode never renders secrets.',
        ]];
    }

    public function search(string $term): array
    {
        if (! $this->hasProperty() || trim($term) === '') return ['reservations' => collect(), 'clients' => collect(), 'rooms' => collect(), 'tasks' => collect()];
        $like = '%'.str_replace('%', '', trim($term)).'%';

        return [
            'reservations' => $this->query(Reservation::class)->where('code', 'like', $like)->limit(10)->get(),
            'clients' => $this->query(Client::class)->where(function (Builder $query) use ($like): void { $query->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('email', 'like', $like); })->limit(10)->get(),
            'rooms' => $this->query(Room::class)->where('room_number', 'like', $like)->limit(10)->get(),
            'tasks' => $this->query(Task::class)->where('title', 'like', $like)->limit(10)->get(),
        ];
    }

    private function hasProperty(): bool
    {
        return $this->context->propertyId() !== null;
    }

    private function query(string $model): Builder
    {
        abort_unless($this->hasProperty(), 403, 'This organization-level support session is limited to metadata.');

        return $model::query()->withoutGlobalScopes()->where('organization_id', $this->context->organizationId())->where('property_id', $this->context->propertyId());
    }
}
