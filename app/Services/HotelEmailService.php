<?php

namespace App\Services;

use App\Jobs\SendHotelEmail;
use App\Models\EmailDeliveryLog;
use App\Models\EmailTemplate;
use Throwable;

class HotelEmailService
{
    public function __construct(private readonly SafeTemplateRenderer $renderer) {}

    public function queue(string $templateKey, string $recipient, array $variables, ?int $reservationId = null, ?int $clientId = null, bool $sendImmediately = false): ?EmailDeliveryLog
    {
        $template = EmailTemplate::query()->where('key', $templateKey)->where('is_enabled', true)->first();
        if (! $template || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $log = EmailDeliveryLog::create([
            'recipient' => $recipient, 'subject' => $this->renderer->render($template->subject, $variables),
            'template' => $templateKey, 'reservation_id' => $reservationId, 'client_id' => $clientId, 'status' => 'queued', 'provider' => 'smtp',
        ]);
        try {
            $dispatch = SendHotelEmail::dispatch($log->id, $variables);
            if ($sendImmediately) {
                $dispatch->onConnection('sync');
                unset($dispatch);
            }
        } catch (Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_summary' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            throw $exception;
        }

        return $log;
    }
}
