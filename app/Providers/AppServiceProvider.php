<?php

namespace App\Providers;

use App\Contracts\BookingChannelInterface;
use App\Contracts\PaymentGatewayInterface;
use App\Contracts\WhatsAppProviderInterface;
use App\Events\GuestCheckedIn;
use App\Events\GuestCheckedOut;
use App\Events\PaymentReceived;
use App\Events\PaymentVoided;
use App\Events\ReservationCancelled;
use App\Events\ReservationConfirmed;
use App\Events\ReservationCreated;
use App\Events\ReservationUpdated;
use App\Models\Client;
use App\Models\HousekeepingTask;
use App\Models\Invoice;
use App\Models\MaintenanceTask;
use App\Models\Payment;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\PosShift;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Models\Task;
use App\Models\Announcement;
use App\Policies\AnnouncementPolicy;
use App\Policies\ClientPolicy;
use App\Policies\HousekeepingTaskPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\MaintenanceTaskPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PosOrderPolicy;
use App\Policies\PosProductPolicy;
use App\Policies\PosShiftPolicy;
use App\Policies\ReservationPolicy;
use App\Policies\RolePolicy;
use App\Policies\RoomPolicy;
use App\Policies\StaffPolicy;
use App\Policies\TaskPolicy;
use App\Services\HotelNotificationService;
use App\Services\NullBookingChannelProvider;
use App\Services\NullPaymentGateway;
use App\Services\NullWhatsAppProvider;
use App\Services\WebhookService;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WhatsAppProviderInterface::class, NullWhatsAppProvider::class);
        $this->app->bind(PaymentGatewayInterface::class, NullPaymentGateway::class);
        $this->app->bind(BookingChannelInterface::class, NullBookingChannelProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerDatabaseSafetyGuard();
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->bearerToken() ? hash('sha256', $request->bearerToken()) : $request->ip()));
        Event::listen(ReservationCreated::class, fn (ReservationCreated $event) => $this->reservationEvent($event->reservation, 'created'));
        Event::listen(ReservationUpdated::class, fn (ReservationUpdated $event) => $this->reservationEvent($event->reservation, 'updated'));
        Event::listen(ReservationConfirmed::class, fn (ReservationConfirmed $event) => $this->reservationEvent($event->reservation, 'confirmed'));
        Event::listen(ReservationCancelled::class, fn (ReservationCancelled $event) => $this->reservationEvent($event->reservation, 'cancelled'));
        Event::listen(GuestCheckedIn::class, fn (GuestCheckedIn $event) => $this->reservationEvent($event->reservation, 'checked_in'));
        Event::listen(GuestCheckedOut::class, fn (GuestCheckedOut $event) => $this->reservationEvent($event->reservation, 'checked_out'));
        Event::listen(PaymentReceived::class, fn (PaymentReceived $event) => $this->paymentEvent($event->payment, 'received'));
        Event::listen(PaymentVoided::class, fn (PaymentVoided $event) => $this->paymentEvent($event->payment, 'voided'));
        Gate::policy(Reservation::class, ReservationPolicy::class);
        Gate::policy(Room::class, RoomPolicy::class);
        Gate::policy(HousekeepingTask::class, HousekeepingTaskPolicy::class);
        Gate::policy(MaintenanceTask::class, MaintenanceTaskPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(User::class, StaffPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Announcement::class, AnnouncementPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(PosOrder::class, PosOrderPolicy::class);
        Gate::policy(PosProduct::class, PosProductPolicy::class);
        Gate::policy(PosShift::class, PosShiftPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::define('room_planning.view', fn (User $user): bool => $user->hasPermission('room_planning.view'));
        Gate::define('dashboard.view', fn (User $user): bool => $user->hasPermission('dashboard.view'));
        Gate::define('reports.view', fn (User $user): bool => $user->hasPermission('reports.view'));
        Gate::define('reports.export', fn (User $user): bool => $user->hasPermission('reports.export'));
        foreach (['pos.access', 'pos.sell', 'pos.charge_room', 'pos.discount', 'pos.void', 'pos.refund', 'pos.products.view', 'pos.products.manage', 'pos.categories.manage', 'pos.outlets.manage', 'pos.shifts.open', 'pos.shifts.close', 'pos.shifts.view_all', 'pos.reports.view', 'pos.receipts.view', 'pos.manage'] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
        Gate::before(function (User $user): ?bool {
            return $user->roleName() === 'super_administrator' ? true : null;
        });
    }

    private function registerDatabaseSafetyGuard(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            $destructiveCommands = [
                'db:wipe',
                'migrate:fresh',
                'migrate:refresh',
                'migrate:reset',
                'migrate:rollback',
                'schema:drop',
            ];

            if (! in_array($event->command, $destructiveCommands, true)) {
                return;
            }

            $connection = (string) config('database.default');
            $database = strtolower((string) config("database.connections.{$connection}.database"));
            $testingDatabase = app()->environment('testing') && $connection === 'sqlite' && $database === ':memory:';
            $disposableOverride = filter_var(env('DB_ALLOW_DESTRUCTIVE_COMMANDS', false), FILTER_VALIDATE_BOOL)
                && $connection === 'sqlite'
                && ($database === ':memory:' || str_contains($database, 'test') || str_contains($database, 'tmp') || str_contains($database, 'audit'))
                && ! app()->environment(['production', 'staging'])
                && ! str_contains($database, 'reservations_db')
                && ! str_contains($database, 'production')
                && ! str_contains($database, 'staging');

            if (! $testingDatabase && ! $disposableOverride) {
                throw new \RuntimeException(sprintf(
                    'Blocked destructive database command [%s]. Use php artisan migrate for normal schema updates. Set DB_ALLOW_DESTRUCTIVE_COMMANDS=true only after verifying that the database is disposable and the destructive action is explicitly approved.',
                    $event->command,
                ));
            }
        });
    }

    private function reservationEvent(Reservation $reservation, string $type): void
    {
        app(HotelNotificationService::class)->reservation($type, $reservation);
        $reservation->loadMissing(['client', 'room.roomType']);
        $event = in_array($type, ['checked_in', 'checked_out'], true) ? 'guest.'.$type : 'reservation.'.$type;
        app(WebhookService::class)->queue($event, ['code' => $reservation->code, 'guest' => $reservation->client?->full_name, 'room' => $reservation->room?->room_number, 'status' => $reservation->status?->value], Reservation::class, $reservation->id);
    }

    private function paymentEvent(Payment $payment, string $type): void
    {
        app(HotelNotificationService::class)->payment($type, $payment);
        app(WebhookService::class)->queue('payment.'.$type, ['payment_id' => $payment->id, 'invoice_number' => $payment->invoice_number, 'amount' => $payment->amount, 'status' => $payment->status?->value], Payment::class, $payment->id);
    }
}
