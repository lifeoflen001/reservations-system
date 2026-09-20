<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function blocksAvailability(): bool
    {
        return match ($this) {
            self::Pending, self::Confirmed, self::CheckedIn => true,
            self::CheckedOut, self::Cancelled, self::NoShow => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::CheckedIn => 'Checked In',
            self::CheckedOut => 'Checked Out',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No Show',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed, self::CheckedOut => 'info',
            self::CheckedIn => 'success',
            self::Cancelled => 'danger',
            self::NoShow => 'warning',
        };
    }
}
