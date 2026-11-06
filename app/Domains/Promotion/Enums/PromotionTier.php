<?php

declare(strict_types=1);

namespace App\Domains\Promotion\Enums;

enum PromotionTier: string
{
    case Standard = 'standard';
    case Featured = 'featured';
    case Premium = 'premium';
    case Homepage = 'homepage';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard (Organic Listing)',
            self::Featured => 'Featured Listing (Search Boost)',
            self::Premium => 'Premium Placement (Top Tier)',
            self::Homepage => 'Homepage Showcase',
            self::Urgent => 'Urgent Sale Ribbon',
        };
    }

    public function defaultWeight(): int
    {
        return match ($this) {
            self::Standard => 0,
            self::Featured => 25,
            self::Premium => 50,
            self::Homepage => 75,
            self::Urgent => 100,
        };
    }
}

