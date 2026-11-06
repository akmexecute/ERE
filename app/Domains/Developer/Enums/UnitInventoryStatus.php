<?php

declare(strict_types=1);

namespace App\Domains\Developer\Enums;

enum UnitInventoryStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Contracted = 'contracted';
    case Sold = 'sold';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available for Reservation',
            self::Reserved => 'Reserved (Temporary Hold)',
            self::Contracted => 'Contract Signed / Active Installments',
            self::Sold => 'Sold & Fully Paid',
            self::Blocked => 'Blocked / Developer Retained',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Available => in_array($next, [self::Reserved, self::Blocked], true),
            self::Reserved => in_array($next, [self::Available, self::Contracted, self::Blocked], true),
            self::Contracted => in_array($next, [self::Sold, self::Available, self::Blocked], true),
            self::Sold => false, // Terminal
            self::Blocked => in_array($next, [self::Available], true),
        };
    }
}
