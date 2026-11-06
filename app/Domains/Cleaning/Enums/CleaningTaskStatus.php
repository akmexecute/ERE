<?php

declare(strict_types=1);

namespace App\Domains\Cleaning\Enums;

enum CleaningTaskStatus: string
{
    case Pending = 'pending'; // Created upon Checkout
    case Assigned = 'assigned'; // Cleaner Assigned
    case Cleaning = 'cleaning'; // Cleaning in progress
    case Inspection = 'inspection'; // Awaiting Host/Manager inspection
    case Ready = 'ready'; // Unit ready for next guest check-in

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Assigned, self::Cleaning], true),
            self::Assigned => in_array($next, [self::Cleaning, self::Inspection], true),
            self::Cleaning => in_array($next, [self::Inspection, self::Ready], true),
            self::Inspection => in_array($next, [self::Cleaning, self::Ready], true),
            self::Ready => false,
        };
    }
}
