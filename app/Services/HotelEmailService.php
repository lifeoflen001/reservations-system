<?php

namespace App\Services;

use App\Jobs\SendHotelEmail;
use App\Models\EmailDeliveryLog;
use App\Models\EmailTemplate;

class HotelEmailService
{
    public function __construct(private readonly SafeTemplateRenderer $renderer) {}

    public function queue(string $templateKey, string $recipient, array $variables, ?int $reservationId = null, ?int $clientId = null): ?EmailDeliveryLog
    {
        $template = EmailTemplate::query()->where('key', $templateKey)->where('is_enabled', true)->first();
        if (! $template || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $log = EmailDeliveryLog::create([
            'recipient' => $recipient, 'subject' => $this->renderer->render($template->subject, $variables),
            'template' => $templateKey, 'reservation_id' => $reservationId, 'client_id' => $clientId, 'status' => 'queued', 'provider' => 'smtp',
        ]);
        SendHotelEmail::dispatch($log->id, $variables);

        return $log;
    }
}
