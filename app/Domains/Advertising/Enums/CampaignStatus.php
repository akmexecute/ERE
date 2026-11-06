<?php

declare(strict_types=1);

namespace App\Domains\Advertising\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Admin Review',
            self::Active => 'Active & Serving',
            self::Paused => 'Paused',
            self::Completed => 'Completed (Ended)',
            self::Rejected => 'Rejected',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
            self::PendingApproval => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
            self::Draft => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
            self::Paused => 'bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-300',
            self::Completed => 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300',
            self::Rejected => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300',
        };
    }
}

