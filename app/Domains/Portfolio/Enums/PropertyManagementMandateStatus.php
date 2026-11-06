<?php

declare(strict_types=1);

namespace App\Domains\Portfolio\Enums;

enum PropertyManagementMandateStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Terminated = 'terminated';

    public function label(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return match ($this) {
            self::Active => match ($locale) {
                'am' => 'ንቁ ውክልና',
                'om' => 'Hojirra Kan Jiru',
                default => 'Active',
            },
            self::Suspended => match ($locale) {
                'am' => 'የታገደ',
                'om' => 'Yeroof Kan Dhaabbate',
                default => 'Suspended',
            },
            self::Expired => match ($locale) {
                'am' => 'ጊዜው ያለፈበት',
                'om' => 'Yeroon Isaa Kan Darbe',
                default => 'Expired',
            },
            self::Terminated => match ($locale) {
                'am' => 'የተቋረጠ',
                'om' => 'Kan Addaan Cite',
                default => 'Terminated',
            },
        };
    }
}
