<?php

declare(strict_types=1);

namespace App\Domains\Advertising\Enums;

enum AdPlacementType: string
{
    case Homepage = 'homepage';
    case Search = 'search';
    case CityPage = 'city_page';
    case CategoryPage = 'category_page';
    case PropertyPage = 'property_page';
    case Mobile = 'mobile';
    case Newsletter = 'newsletter';

    public function label(): string
    {
        return match ($this) {
            self::Homepage => 'National Homepage Hero Banner',
            self::Search => 'Marketplace Search Top Spot',
            self::CityPage => 'City / Regional Hub Spotlight',
            self::CategoryPage => 'Category-Specific Native Card',
            self::PropertyPage => 'Property Detail Sidebar Banner',
            self::Mobile => 'Mobile Interstitial / Native Feed',
            self::Newsletter => 'Investor Weekly Digest Sponsorship',
        };
    }
}

