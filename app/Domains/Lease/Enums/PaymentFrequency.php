<?php

declare(strict_types=1);

namespace App\Domains\Lease\Enums;

enum PaymentFrequency: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnual = 'semi_annual';
    case Annual = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly (3 Months)',
            self::SemiAnnual => 'Semi-Annual (6 Months)',
            self::Annual => 'Annual (12 Months)',
        };
    }

    public function monthsInterval(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::SemiAnnual => 6,
            self::Annual => 12,
        };
    }

    public function intervalMonths(): int
    {
        return $this->monthsInterval();
    }
}
