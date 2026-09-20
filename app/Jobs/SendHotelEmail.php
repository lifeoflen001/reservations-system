<?php

namespace App\Jobs;

use App\Exceptions\ProviderNotConfiguredException;
use App\Models\EmailDeliveryLog;
use App\Models\EmailTemplate;
use App\Services\ConfiguredEmailProvider;
use App\Services\IntegrationSettingsService;
use App\Services\SafeTemplateRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendHotelEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $deliveryLogId, public array $variables) {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(IntegrationSettingsService $integrations, SafeTemplateRenderer $renderer): void
    {
        $log = EmailDeliveryLog::query()->findOrFail($this->deliveryLogId);
        $template = EmailTemplate::query()->where('key', $log->template)->firstOrFail();
        $log->update(['status' => 'sending', 'attempts' => $log->attempts + 1]);
        $provider = $integrations->get('email');
        if (! $provider) {
            throw new ProviderNotConfiguredException('Email provider is not configured.');
        }
        $subject = $renderer->render($template->subject, $this->variables);
        $body = $renderer->render($template->body, $this->variables);
        app(ConfiguredEmailProvider::class, ['integration' => $provider])->send($log->recipient, $subject, $body);
        $log->update(['status' => 'sent', 'sent_at' => now(), 'error_summary' => null]);
        $provider->update(['last_success_at' => now(), 'last_error' => null]);
        $integrations->forget('email');
    }

    public function failed(Throwable $exception): void
    {
        $log = EmailDeliveryLog::query()->find($this->deliveryLogId);
        $log?->update(['status' => 'failed', 'failed_at' => now(), 'error_summary' => mb_substr($exception->getMessage(), 0, 500)]);
    }
}
