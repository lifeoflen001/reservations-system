<?php

namespace App\Enums;

enum TaskPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string { return ucfirst($this->value); }
    public function badgeVariant(): string { return $this === self::Urgent ? 'danger' : ($this === self::High ? 'info' : 'neutral'); }
}
