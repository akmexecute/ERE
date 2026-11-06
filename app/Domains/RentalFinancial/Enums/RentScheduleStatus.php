<?php

declare(strict_types=1);

namespace App\Domains\RentalFinancial\Enums;

enum RentScheduleStatus: string
{
    case Upcoming = 'upcoming';
    case Pending = 'pending';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Upcoming',
            self::Pending => 'Pending Due',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Paid in Full',
            self::Overdue => 'Overdue',
        };
    }
}
