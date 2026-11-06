<?php

declare(strict_types=1);

namespace App\Domains\Developer\Enums;

enum ReservationStatus: string
{
    case Requested = 'requested';
    case PendingPayment = 'pending_payment';
    case Reserved = 'reserved';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Reservation Requested',
            self::PendingPayment => 'Awaiting Deposit Payment',
            self::Reserved => 'Confirmed Active Hold',
            self::Expired => 'Hold Expired',
            self::Cancelled => 'Reservation Cancelled',
            self::Converted => 'Converted to Sales Contract',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Requested, self::PendingPayment, self::Reserved], true);
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Requested => in_array($next, [self::PendingPayment, self::Reserved, self::Cancelled, self::Expired], true),
            self::PendingPayment => in_array($next, [self::Reserved, self::Cancelled, self::Expired], true),
            self::Reserved => in_array($next, [self::Converted, self::Cancelled, self::Expired], true),
            self::Expired, self::Cancelled, self::Converted => false,
        };
    }
}
