<?php

declare(strict_types=1);

namespace App\Domains\Booking\Enums;

enum BookingStatus: string
{
    case Pending = 'pending'; // Request to Book awaiting Host approval
    case AwaitingPayment = 'awaiting_payment'; // Temporary Hold active (e.g. 15 mins)
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Active = 'active';
    case CheckedOut = 'checked_out';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Disputed = 'disputed';
    case NoShow = 'no_show';
    case Expired = 'expired'; // Unpaid hold expired & inventory released

    public function locksInventory(): bool
    {
        return in_array($this, [
            self::AwaitingPayment,
            self::Confirmed,
            self::CheckedIn,
            self::Active,
        ], true);
    }
}
