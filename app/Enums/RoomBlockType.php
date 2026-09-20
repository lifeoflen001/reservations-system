<?php

namespace App\Enums;

enum RoomBlockType: string
{
    case Blocked = 'blocked';
    case Maintenance = 'maintenance';
}
