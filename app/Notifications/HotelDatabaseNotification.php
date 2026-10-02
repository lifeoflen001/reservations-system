<?php

namespace App\Notifications;

use App\Notifications\Channels\TenantDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HotelDatabaseNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly array $payload) {}

    public function via(object $notifiable): array
    {
        return [TenantDatabaseChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }
}
