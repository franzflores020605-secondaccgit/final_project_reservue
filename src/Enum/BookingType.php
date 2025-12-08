<?php

namespace App\Enum;

enum BookingType: string
{
    case Flight = 'flight';
    case Hotel = 'hotel';
    case Package = 'package';
}
