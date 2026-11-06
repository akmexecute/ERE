<?php

declare(strict_types=1);

namespace App\Domains\Financial\Enums;

enum PayoutSchedule: string
{
    case Immediately = 'immediately';
    case AfterCheckin = 'after_checkin';
    case AfterCheckout = 'after_checkout';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Immediately => 'Immediate Release (On Payment Confirmation)',
            self::AfterCheckin => 'After Check-In (Standard Host Schedule)',
            self::AfterCheckout => 'After Check-Out (Damage Inspection Window)',
            self::Weekly => 'Weekly Batch Settlement',
            self::Monthly => 'Monthly Batch Settlement',
        };
    }
}

