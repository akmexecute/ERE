<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Enums;

enum SubscriberType: string
{
    case All = 'all';
    case Seller = 'seller';
    case Agent = 'agent';
    case Company = 'company';
    case Developer = 'developer';
    case Host = 'host';
    case PropertyManager = 'property_manager';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All Users & Entities',
            self::Seller => 'Individual Property Owner / Seller',
            self::Agent => 'Licensed Real Estate Agent / Broker',
            self::Company => 'Real Estate Agency / Corporate Brokerage',
            self::Developer => 'Property Developer',
            self::Host => 'Short-Stay Accommodation Host',
            self::PropertyManager => 'Professional Property Manager',
        };
    }
}

