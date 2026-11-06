<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'code' => '1010',
                'name' => 'Cash & Payment Provider Clearing',
                'type' => 'asset',
                'description' => 'Clearing account for funds collected via Telebirr, CBE Birr, Chapa, and Bank Transfers awaiting settlement.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '2010',
                'name' => 'Host & Landlord Payables',
                'type' => 'liability',
                'description' => 'Net funds payable to hosts and landlords for completed stays and rental collections.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '2020',
                'name' => 'Taxes & VAT Payable',
                'type' => 'liability',
                'description' => 'Withholding and 15% VAT collected on behalf of the Ethiopian Ministry of Revenue.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '2030',
                'name' => 'Customer Escrow & Purchase Deposits',
                'type' => 'liability',
                'description' => 'Unearned property purchase deposits, installment escrow, and security deposits held in trust.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '2040',
                'name' => 'Unapplied Customer Funds & Pending Reconciliation',
                'type' => 'liability',
                'description' => 'Unapplied customer funds received after payment reservation expired or exceeding contractual limit, awaiting administrative reconciliation or refund.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '2050',
                'name' => 'Customer Refunds Payable & Disbursement Clearing',
                'type' => 'liability',
                'description' => 'Approved customer refunds and pending payouts awaiting physical banking or payment gateway disbursement.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '4010',
                'name' => 'Platform Service Fee & Commission Revenue',
                'type' => 'revenue',
                'description' => 'Earned revenue from booking service fees, broker commission cuts, and transaction fees.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '4020',
                'name' => 'Listing & Subscription Revenue',
                'type' => 'revenue',
                'description' => 'Earned revenue from premium property listings, featured placement, and agency subscriptions.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '5010',
                'name' => 'Payment Processing & Provider Expense',
                'type' => 'expense',
                'description' => 'Interchange fees, gateway commissions, and provider charges deducted during payout or collection.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($accounts as $account) {
            DB::table('ledger_accounts')->updateOrInsert(
                ['code' => $account['code']],
                $account
            );
        }
    }
}

