<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\HotelDatabaseNotification;
use Illuminate\Support\Collection;

class HotelNotificationService
{
    public function __construct(private readonly HotelEmailService $emails, private readonly IntegrationSettingsService $integrations) {}

    public function reservation(string $type, Reservation $reservation): void
    {
        $reservation->loadMissing(['client', 'room.roomType']);
        $payload = [
            'title' => match ($type) {
                'created' => 'New reservation', 'confirmed' => 'Reservation confirmed', 'cancelled' => 'Reservation cancelled', 'checked_in' => 'Guest checked in', 'checked_out' => 'Guest checked out', default => 'Reservation updated'
            },
            'message' => $reservation->code.' · '.($reservation->client?->full_name ?? 'Guest').' · Room '.($reservation->room?->room_number ?? '—'),
            'severity' => in_array($type, ['cancelled'], true) ? 'warning' : 'info',
            'category' => 'operational',
            'entity_type' => Reservation::class, 'entity_id' => $reservation->id, 'action_url' => route('reservations.index', ['search' => $reservation->code]),
        ];
        $this->notifyUsers($this->usersWithPermission('reservations.view'), $payload);
        if ($type === 'confirmed' && $this->integrations->isConfigured('email') && $reservation->client?->email) {
            $this->emails->queue('reservation_confirmation', $reservation->client->email, $this->reservationVariables($reservation), $reservation->id, $reservation->client_id);
        }
    }

    public function payment(string $type, Payment $payment): void
    {
        $payment->loadMissing(['reservation.client', 'reservation.room']);
        $reservation = $payment->reservation;
        $payload = [
            'title' => $type === 'voided' ? 'Payment voided' : 'Payment received',
            'message' => ($payment->invoice_number ?? 'Payment').' · '.($payment->amount ?? '0').' · '.($reservation?->code ?? 'Reservation'),
            'severity' => $type === 'voided' ? 'warning' : 'success',
            'category' => 'financial',
            'entity_type' => Payment::class, 'entity_id' => $payment->id, 'action_url' => route('payments.index', ['invoice' => $payment->invoice?->id]),
        ];
        $this->notifyUsers($this->usersWithPermission('payments.view'), $payload);
    }

    private function usersWithPermission(string $permission): Collection
    {
        return User::query()->with(['notificationPreferences', 'role.permissions'])->get()
            ->filter(fn (User $user) => $user->hasPermission($permission));
    }

    private function notifyUsers(Collection $users, array $payload): void
    {
        foreach ($users as $user) {
            $preferences = $user->notificationPreferences;
            if ($preferences && ! in_array('in_app', $preferences->channels ?? [], true)) {
                continue;
            }
            if ($preferences && ! in_array($payload['category'] ?? 'operational', $preferences->categories ?? [], true)) {
                continue;
            }
            $user->notify(new HotelDatabaseNotification($payload));
        }
    }

    private function reservationVariables(Reservation $reservation): array
    {
        return ['guest_name' => $reservation->client?->full_name ?? 'Guest', 'reservation_code' => $reservation->code, 'room_number' => $reservation->room?->room_number ?? '—', 'room_type' => $reservation->room?->roomType?->name ?? 'Room', 'check_in' => $reservation->check_in?->format('m/d/Y H:i'), 'check_out' => $reservation->check_out?->format('m/d/Y H:i'), 'total_amount' => $reservation->total_amount, 'paid_amount' => $reservation->paidAmount(), 'balance' => $reservation->balance(), 'property_name' => app(PropertySettingsService::class)->name(), 'property_phone' => app(PropertySettingsService::class)->phone() ?? '', 'property_email' => app(PropertySettingsService::class)->email() ?? ''];
    }
}
