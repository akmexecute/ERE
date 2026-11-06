<?php

declare(strict_types=1);

namespace App\Domains\Payment\Enums;

enum PurchaseAllocationStatus: string
{
    case Unallocated = 'unallocated';
    case Allocated = 'allocated';
    case PartiallyAllocated = 'partially_allocated';
    case ReconciliationRequired = 'reconciliation_required';
    case Refunded = 'refunded';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Unallocated => 'Unallocated',
            self::Allocated => 'Allocated to Purchase',
            self::PartiallyAllocated => 'Partially Allocated',
            self::ReconciliationRequired => 'Reconciliation Required',
            self::Refunded => 'Refunded',
            self::Reversed => 'Reversed',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Refunded, self::Reversed => true,
            default => false,
        };
    }
}

