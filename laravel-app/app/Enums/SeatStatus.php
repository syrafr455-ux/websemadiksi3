<?php

namespace App\Enums;

enum SeatStatus: string
{
    case Available = 'available';
    case ReservedPanitia = 'reserved_panitia';
    case Booked = 'booked';
    case Blocked = 'blocked';
}
