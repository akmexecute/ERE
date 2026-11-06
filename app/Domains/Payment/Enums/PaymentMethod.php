<?php

declare(strict_types=1);

namespace App\Domains\Payment\Enums;

enum PaymentMethod: string
{
    case Telebirr = 'telebirr';
    case CbeBirr = 'cbe_birr';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case Chapa = 'chapa';

    public function label(): string
    {
        return match ($this) {
            self::Telebirr => 'Telebirr',
            self::CbeBirr => 'CBE Birr',
            self::BankTransfer => 'Bank Transfer (Slip Upload)',
            self::Card => 'Debit / Credit Card (Visa / Mastercard)',
            self::Chapa => 'Chapa Gateway',
        };
    }
}

