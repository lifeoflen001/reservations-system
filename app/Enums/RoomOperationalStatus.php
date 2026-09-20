<?php

namespace App\Enums;

enum RoomOperationalStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Occupied = 'occupied';
    case MustClean = 'must_clean';
    case Maintenance = 'maintenance';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available', self::Reserved => 'Reserved', self::Occupied => 'Occupied',
            self::MustClean => 'Must clean', self::Maintenance => 'Maintenance', self::Blocked => 'Blocked',
        };
    }
}
