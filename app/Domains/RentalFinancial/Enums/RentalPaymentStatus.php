<?php

declare(strict_types=1);

namespace App\Domains\RentalFinancial\Enums;

enum RentalPaymentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Confirmation',
            self::Confirmed => 'Confirmed Received',
            self::Failed => 'Payment Failed',
            self::Refunded => 'Refunded',
        };
    }
}
