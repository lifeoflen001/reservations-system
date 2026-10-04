<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Writes tenant ownership with the notification row itself. The stock
 * database channel emits NotificationSent only after insertion, which is too
 * late once notification organization ownership is NOT NULL.
 */
final class TenantDatabaseChannel extends DatabaseChannel
{
    public function send($notifiable, Notification $notification)
    {
        $context = app(TenantContext::class);
        if ($context->organizationId() === null && $notifiable instanceof User) {
            $context->resolveFor($notifiable);
        }

        $payload = $this->buildPayload($notifiable, $notification);
        $payload['organization_id'] = $context->requireOrganization()->getKey();
        $payload['property_id'] = $context->propertyId();

        return $notifiable->routeNotificationFor('database', $notification)->create($payload);
    }
}
