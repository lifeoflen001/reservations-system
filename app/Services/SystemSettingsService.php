<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rules\Password;

class SystemSettingsService
{
    private const CACHE_KEY = 'hotel.system-settings';

    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return SystemSetting::query()->get()->mapWithKeys(fn (SystemSetting $setting) => [$setting->key => $this->decode($setting->value, $setting->type)])->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    public function integer(string $key, int $default): int
    {
        return (int) $this->get($key, $default);
    }

    public function set(string $key, mixed $value, ?string $type = null, ?int $actorId = null): SystemSetting
    {
        $type ??= match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'json',
            default => 'string',
        };
        $stored = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
        $setting = SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $stored, 'type' => $type, 'updated_by' => $actorId]);
        Cache::forget(self::CACHE_KEY);

        return $setting;
    }

    public function update(array $values, ?int $actorId = null): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, null, $actorId);
        }
        Cache::forget(self::CACHE_KEY);
    }

    public function passwordRule(): Password
    {
        $rule = Password::min(max(6, $this->integer('password_min_length', 8)));
        if ($this->boolean('password_require_mixed_case')) {
            $rule->mixedCase();
        }
        if ($this->boolean('password_require_numbers')) {
            $rule->numbers();
        }
        if ($this->boolean('password_require_symbols')) {
            $rule->symbols();
        }

        return $rule;
    }

    public function defaults(): array
    {
        return [
            'theme' => 'light',
            'session_timeout' => 120,
            'password_min_length' => 8,
            'password_require_mixed_case' => false,
            'password_require_numbers' => false,
            'password_require_symbols' => false,
            'edition' => 'Pro (Development)',
            'last_update_check' => null,
        ];
    }

    private function decode(?string $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => $value === '1' || $value === 'true',
            'integer' => (int) $value,
            'json' => json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR),
            default => $value,
        };
    }
}
