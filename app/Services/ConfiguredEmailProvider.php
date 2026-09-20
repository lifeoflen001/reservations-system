<?php

namespace App\Services;

use App\Contracts\EmailProviderInterface;
use App\Exceptions\ProviderNotConfiguredException;
use App\Models\IntegrationSetting;

class ConfiguredEmailProvider implements EmailProviderInterface
{
    public function configurationValid(): bool
    {
        $settings = $this->integration->settings ?? [];

        return $this->integration->is_enabled && $this->integration->provider === 'smtp' && ! empty($settings['host']) && ! empty($settings['from_email']);
    }

    public function __construct(private readonly IntegrationSetting $integration) {}

    public function send(string $recipient, string $subject, string $body): void
    {
        $settings = $this->integration->settings ?? [];
        $secrets = $this->integration->secrets ?? [];
        if (! $this->configurationValid() || $this->integration->status !== 'configured') {
            throw new ProviderNotConfiguredException('Email provider is not configured.');
        }
        $mailer = app('mail.manager')->build([
            'transport' => 'smtp', 'host' => $settings['host'], 'port' => (int) ($settings['port'] ?? 587),
            'scheme' => ($settings['encryption'] ?? null) === 'ssl' ? 'smtps' : 'smtp',
            'username' => $settings['username'] ?? null, 'password' => $secrets['password'] ?? null,
            'timeout' => 10, 'from' => ['address' => $settings['from_email'], 'name' => $settings['from_name'] ?? config('hotel.brand.name')],
        ]);
        $mailer->html($body, function ($message) use ($recipient, $subject, $settings): void {
            $message->to($recipient)->from($settings['from_email'], $settings['from_name'] ?? config('hotel.brand.name'))->subject($subject);
        });
    }
}
