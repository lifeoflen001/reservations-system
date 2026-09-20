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
        $integration = $this->get($key);

        return (bool) ($integration?->is_enabled && $integration->status === 'configured');
    }

    public function maskSecret(?string $value): string
    {
        return $value ? 'Configured' : 'Not configured';
    }
}
