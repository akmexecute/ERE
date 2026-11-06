<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Enums;

enum RentalApplicationStatus: string
{
    case Submitted = 'submitted';
    case DocumentReview = 'document_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case ConvertedToLease = 'converted_to_lease';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::DocumentReview => 'Under Document Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
            self::ConvertedToLease => 'Converted to Lease',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Withdrawn, self::ConvertedToLease], true);
    }
}
