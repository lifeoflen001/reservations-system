<?php

namespace App\Support;

use App\Services\PropertySettingsService;

class CurrencyFormatter
{
    public function format(float|int|string|null $amount): string
    {
        $currency = app(PropertySettingsService::class)->currency();

        return ($currency->symbol ?? '').number_format((float) $amount, (int) ($currency->decimal_places ?? 2));
    }
}
