<?php

declare(strict_types=1);

namespace App\Domains\Financial\Enums;

enum LedgerAccountCode: string
{
    case CashAndClearing = '1010';
    case HostPayable = '2010';
    case TaxesPayable = '2020';
    case CustomerEscrowAndDeposits = '2030';
    case UnappliedCustomerFunds = '2040';
    case CustomerRefundsPayable = '2050';
    case PlatformServiceRevenue = '4010';
    case ListingRevenue = '4020';
    case PaymentProcessingExpense = '5010';
}

