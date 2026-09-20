<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Voided = 'voided';

    public function countsTowardsPaid(): bool
    {
        return $this === self::Paid;
    }

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Pending => 'Pending',
            self::Failed => 'Failed',
            self::Refunded => 'Refunded',
            self::Voided => 'Voided',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Pending => 'warning',
            self::Failed, self::Voided => 'danger',
            self::Refunded => 'neutral',
        };
    }
}
