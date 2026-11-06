<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Enums;

enum SubscriptionStatus: string
{
    case PendingPayment = 'pending_payment';
    case Active = 'active';
    case PastDue = 'past_due';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending Payment',
            self::Active => 'Active',
            self::PastDue => 'Past Due',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
            self::PendingPayment => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
            self::PastDue => 'bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-300',
            self::Expired => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
            self::Cancelled => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300',
        };
    }
}

