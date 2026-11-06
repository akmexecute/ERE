<?php

declare(strict_types=1);

namespace App\Domains\Developer\Enums;

enum SalesContractStatus: string
{
    case Draft = 'draft';
    case AwaitingSignature = 'awaiting_signature';
    case Signed = 'signed';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft Contract',
            self::AwaitingSignature => 'Awaiting Buyer / Developer Signature',
            self::Signed => 'Signed by Parties',
            self::Active => 'Active Construction Installment Schedule',
            self::Completed => 'Fully Paid & Handover Complete',
            self::Cancelled => 'Contract Cancelled',
            self::Terminated => 'Contract Terminated / Rescinded',
        };
    }
}
