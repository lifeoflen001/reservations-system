<?php

namespace App\Enums;

enum HousekeepingStatus: string
{
    case Clean = 'clean';
    case Dirty = 'dirty';
}
