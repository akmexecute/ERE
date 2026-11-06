<?php

declare(strict_types=1);

namespace App\Domains\Financial\Enums;

enum PayoutStatus: string
{
    case Scheduled = 'scheduled';
    case Eligible = 'eligible';
    case AwaitingApproval = 'awaiting_approval';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Paid, self::Cancelled], true);
    }
}

