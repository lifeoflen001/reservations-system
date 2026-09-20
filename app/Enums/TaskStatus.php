<?php

namespace App\Enums;

enum TaskStatus: string
{
    case New = 'new';
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string { return match ($this) { self::New => 'New', self::Pending => 'Pending', self::InProgress => 'In Progress', self::OnHold => 'On Hold', self::Completed => 'Completed', self::Cancelled => 'Cancelled' }; }
    public function badgeVariant(): string { return match ($this) { self::New => 'info', self::Pending => 'warning', self::InProgress => 'info', self::OnHold => 'neutral', self::Completed => 'success', self::Cancelled => 'danger' }; }
}
