<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Enums;

enum MaintenanceCategory: string
{
    case Plumbing = 'plumbing';
    case Electricity = 'electricity';
    case Internet = 'internet';
    case Appliance = 'appliance';
    case Structural = 'structural';
    case Cleaning = 'cleaning';
    case Other = 'other';

    public function label(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return match ($this) {
            self::Plumbing => match ($locale) {
                'am' => 'የቧንቧ / የውሃ ጥገና',
                'om' => 'Supha Ujummoo Bishaanii',
                default => 'Plumbing',
            },
            self::Electricity => match ($locale) {
                'am' => 'የኤሌክትሪክ ጥገና',
                'om' => 'Supha Saanyii / Ibsaa',
                default => 'Electricity',
            },
            self::Internet => match ($locale) {
                'am' => 'የኢንተርኔት / ዋይፋይ ጥገና',
                'om' => 'Supha Interneetii / Wi-Fi',
                default => 'Internet & Wi-Fi',
            },
            self::Appliance => match ($locale) {
                'am' => 'የቤት ውስጥ እቃዎች ጥገና',
                'om' => 'Supha Meeshaalee Manaa',
                default => 'Appliance Repair',
            },
            self::Structural => match ($locale) {
                'am' => 'የህንጻ መዋቅር ጥገና',
                'om' => 'Supha Jaargoolee / Gamoo',
                default => 'Structural',
            },
            self::Cleaning => match ($locale) {
                'am' => 'ጽዳትና ንጽህና',
                'om' => 'Qulqullina',
                default => 'Cleaning & Sanitization',
            },
            self::Other => match ($locale) {
                'am' => 'ሌሎች ጥገናዎች',
                'om' => 'Kan Biroo',
                default => 'Other General Maintenance',
            },
        };
    }
}
