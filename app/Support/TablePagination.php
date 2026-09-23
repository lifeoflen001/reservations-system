<?php

namespace App\Support;

use Illuminate\Http\Request;

final class TablePagination
{
    /** @return list<int> */
    public static function options(): array
    {
        return [10, 15, 20, 25, 50, 100];
    }

    public static function perPage(Request $request, int $default): int
    {
        $value = $request->integer('per_page', $default);

        return in_array($value, self::options(), true) ? $value : $default;
    }
}
