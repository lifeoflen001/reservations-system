<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use Illuminate\Support\Facades\Cache;

class IntegrationSettingsService
{
    private const CACHE_KEY = 'hotel.integrations';

    public function get(string $key): ?IntegrationSetting
    {
        return Cache::rememberForever(self::CACHE_KEY.'.'.$key, fn () => IntegrationSetting::query()->where('key', $key)->first());
    }

    public function save(string $key, array $values, ?int $actorId = null): IntegrationSetting
    {
        $integration = IntegrationSetting::query()->firstOrNew(['key' => $key]);
        $integration->fill($values);
        $integration->save();
        $this->forget($key);

        return $integration;
    }

    public function forget(string $key): void
    {
        Cache::forget(self::CACHE_KEY.'.'.$key);
    }

    public function isConfigured(string $key): bool
    {
        $integration = $key === 'email' ? $this->emailProvider() : $this->get($key);

        return (bool) ($integration?->is_enabled && $integration->status === 'configured');
    }

    /**
     * Resolve the database-backed email integration, or the Laravel SMTP
     * configuration when no integration record has been saved yet.
     */
    public function emailProvider(): ?IntegrationSetting
    {
        $integration = $this->get('email');
        if ($integration) {
            return $integration;
        }

        $host = (string) config('mail.mailers.smtp.host');
        $from = (string) config('mail.from.address');
        $username = (string) config('mail.mailers.smtp.username');
        $password = (string) config('mail.mailers.smtp.password');
        if ($host === '' || $from === '' || $username === '' || $password === '') {
            return null;
        }

        $scheme = (string) config('mail.mailers.smtp.scheme');

        return new IntegrationSetting([
            'key' => 'email',
            'provider' => 'smtp',
            'status' => 'configured',
            'mode' => 'smtp',
            'is_enabled' => true,
            'settings' => [
                'host' => $host,
                'port' => (int) config('mail.mailers.smtp.port', 587),
                'encryption' => $scheme === 'smtps' ? 'ssl' : 'tls',
                'username' => $username,
                'from_email' => $from,
                'from_name' => (string) config('mail.from.name', config('hotel.brand.name')),
            ],
            'secrets' => ['password' => $password],
        ]);
    }

    public function maskSecret(?string $value): string
    {
        return $value ? 'Configured' : 'Not configured';
    }
}
