<?php

namespace App\Enum;

/**
 * Lifecycle for a customer booking request (admin/staff editable).
 */
enum CustomerBookingStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
