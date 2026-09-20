<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Language;
use App\Models\Property;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class PropertySettingsService
{
    private const CACHE_KEY = 'hotel.property-settings';
    private const CURRENCIES_CACHE_KEY = 'hotel.active-currencies';
    private const LANGUAGES_CACHE_KEY = 'hotel.active-languages';

    public function current(): ?Property
    {
        if (! Schema::hasTable('properties')) {
            return null;
        }

        return Cache::rememberForever(self::CACHE_KEY, fn () => Property::query()->with('baseCurrency')->first());
    }

    public function value(string $key, mixed $default = null): mixed
    {
        $property = $this->current();

        return $property?->{$key} ?? $default;
    }

    public function name(): string
    {
        return (string) $this->value('name', config('hotel.brand.name', 'HotelDesk'));
    }

    public function email(): ?string
    {
        return $this->value('email');
    }

    public function phone(): ?string
    {
        return $this->value('phone');
    }

    public function address(): string
    {
        $property = $this->current();

        return collect([$property?->address, $property?->city, $property?->country])->filter()->implode(', ') ?: '—';
    }

    public function timezone(): string
    {
        return (string) $this->value('timezone', config('hotel.defaults.timezone', config('app.timezone', 'UTC')));
    }

    public function locale(): string
    {
        return (string) $this->value('default_language', config('app.locale', 'en'));
    }

    public function checkInTime(): string
    {
        return substr((string) $this->value('check_in_time', config('hotel.defaults.check_in_time', '14:00')), 0, 5);
    }

    public function checkOutTime(): string
    {
        return substr((string) $this->value('check_out_time', config('hotel.defaults.check_out_time', '11:00')), 0, 5);
    }

    public function currency(): object
    {
        if ($currency = $this->current()?->baseCurrency) {
            return $currency;
        }

        return Currency::query()->where('code', config('app.currency', 'USD'))->first()
            ?? Currency::query()->where('is_active', true)->orderBy('id')->first()
            ?? (object) ['code' => 'USD', 'symbol' => '$', 'decimal_places' => 2, 'name' => 'US Dollar'];
    }

    public function update(array $values, ?int $actorId = null): Property
    {
        $property = Property::query()->first() ?? new Property;
        $property->fill($values);
        $property->updated_by = $actorId;
        $property->save();
        $this->clearCache();

        return $property->load('baseCurrency');
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CURRENCIES_CACHE_KEY);
        Cache::forget(self::LANGUAGES_CACHE_KEY);
    }

    public function currencies()
    {
        return Cache::rememberForever(self::CURRENCIES_CACHE_KEY, fn () => Currency::query()->where('is_active', true)->orderBy('code')->get());
    }

    public function languages()
    {
        return Cache::rememberForever(self::LANGUAGES_CACHE_KEY, fn () => Language::query()->where('is_active', true)->orderBy('name')->get());
    }
}
