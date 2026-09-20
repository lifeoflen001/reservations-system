<?php

namespace App\Services;

use App\Models\ReservationSequence;
use Illuminate\Support\Facades\DB;

class ReservationCodeGenerator
{
    public function generate(): string
    {
        return DB::transaction(function (): string {
            $sequence = ReservationSequence::query()->lockForUpdate()->findOrFail(1);
            $number = $sequence->next_number;
            $sequence->update(['next_number' => $number + 1]);

            return sprintf('%s-%04d', config('hotel.reservation_code_prefix', 'WSX'), $number);
        });
    }
}
