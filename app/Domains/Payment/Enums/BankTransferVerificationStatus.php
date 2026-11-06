<?php

declare(strict_types=1);

namespace App\Domains\Payment\Enums;

enum BankTransferVerificationStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case RequiresInformation = 'requires_information';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Slip Submitted',
            self::UnderReview => 'Under Review',
            self::Verified => 'Funds Verified',
            self::Rejected => 'Slip Rejected',
            self::RequiresInformation => 'Additional Info Required',
        };
    }
}

