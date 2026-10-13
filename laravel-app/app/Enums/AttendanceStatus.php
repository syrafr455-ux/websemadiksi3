<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Registered = 'registered';
    case CheckedIn = 'checked_in';
    case Absent = 'absent';
}
