<?php

declare(strict_types=1);

namespace App\Domains\Payment\Enums;

enum PaymentType: string
{
    case Booking = 'booking';
    case Reservation = 'reservation';
    case Listing = 'listing';
    case PremiumListing = 'premium_listing';
    case Subscription = 'subscription';
    case Rent = 'rent';
    case Lease = 'lease';
    case DeveloperInstallment = 'developer_installment';
    case Service = 'service';
    case Commission = 'commission';
    case PropertyPurchase = 'property_purchase';

    public function label(): string
    {
        return match ($this) {
            self::Booking => 'Short-Stay Booking',
            self::Reservation => 'Property Reservation',
            self::Listing => 'Standard Listing Fee',
            self::PremiumListing => 'Premium Listing Promotion',
            self::Subscription => 'Platform Subscription',
            self::Rent => 'Rental Payment',
            self::Lease => 'Lease Payment',
            self::DeveloperInstallment => 'Developer Installment',
            self::Service => 'Marketplace Service',
            self::Commission => 'Agent/Broker Commission',
            self::PropertyPurchase => 'Property / Land Purchase',
        };
    }
}

