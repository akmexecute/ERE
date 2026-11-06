<?php

declare(strict_types=1);

namespace App\Domains\Payment\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Successful = 'successful';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Failed, self::Cancelled, self::Reversed, self::Refunded => true,
            default => false,
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Processing, self::Successful, self::Failed, self::Cancelled], true),
            self::Processing => in_array($target, [self::Successful, self::Failed, self::Cancelled], true),
            self::Successful => in_array($target, [self::PartiallyRefunded, self::Refunded, self::Reversed], true),
            self::PartiallyRefunded => in_array($target, [self::Refunded, self::Reversed], true),
            self::Failed, self::Cancelled, self::Reversed, self::Refunded => false,
        };
    }
}

