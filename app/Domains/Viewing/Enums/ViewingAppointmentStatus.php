<?php

declare(strict_types=1);

namespace App\Domains\Viewing\Enums;

enum ViewingAppointmentStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Rescheduled = 'rescheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Viewing Requested',
            self::Confirmed => 'Confirmed by Agent',
            self::Rescheduled => 'Rescheduled',
            self::Completed => 'Viewing Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'Visitor No-Show',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::NoShow], true);
    }
}
