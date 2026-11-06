<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Invoice\Models\TaxRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class EthiopianTaxRulesSeeder extends Seeder
{
    /**
     * Seeds standard Ethiopian tax rules idempotently based on national revenue & tax proclamations.
     */
    public function run(): void
    {
        $rules = [
            [
                'tax_code' => 'VAT',
                'name_en' => 'Value Added Tax (VAT 15%)',
                'name_am' => 'የተጨማሪ እሴት ታክስ (15%)',
                'name_om' => 'Taaksii Dabalata Qabeenyaa (15%)',
                'service_type' => 'all',
                'location_id' => null,
                'rate_pct' => 15.000,
                'is_compound' => false,
                'is_inclusive' => false,
                'is_withholding' => false,
                'calculation_order' => 10,
                'effective_from' => '2024-01-01',
                'effective_to' => null,
                'is_active' => true,
            ],
            [
                'tax_code' => 'WITHHOLDING',
                'name_en' => 'Withholding Tax (2%)',
                'name_am' => 'ቅድመ ግብር ተቀናሽ (2%)',
                'name_om' => 'Taaksii Qabamu (2%)',
                'service_type' => 'all',
                'location_id' => null,
                'rate_pct' => 2.000,
                'is_compound' => false,
                'is_inclusive' => false,
                'is_withholding' => true,
                'calculation_order' => 20,
                'effective_from' => '2024-01-01',
                'effective_to' => null,
                'is_active' => true,
            ],
            [
                'tax_code' => 'TOURISM_TAX',
                'name_en' => 'Tourism & Hospitality Service Fee (5%)',
                'name_am' => 'የቱሪዝም እና መስተንግዶ አገልግሎት ክፍያ (5%)',
                'name_om' => 'Kaffaltii Tajaajila Turiizimii (5%)',
                'service_type' => 'short_stay',
                'location_id' => null,
                'rate_pct' => 5.000,
                'is_compound' => false,
                'is_inclusive' => false,
                'is_withholding' => false,
                'calculation_order' => 15,
                'effective_from' => '2024-01-01',
                'effective_to' => null,
                'is_active' => true,
            ],
            [
                'tax_code' => 'TOT',
                'name_en' => 'Turnover Tax (TOT 10%)',
                'name_am' => 'የተርን ኦቨር ታክስ (10%)',
                'name_om' => 'Taaksii Galii (10%)',
                'service_type' => 'commission',
                'location_id' => null,
                'rate_pct' => 10.000,
                'is_compound' => false,
                'is_inclusive' => false,
                'is_withholding' => false,
                'calculation_order' => 10,
                'effective_from' => '2024-01-01',
                'effective_to' => null,
                'is_active' => true,
            ],
        ];

        foreach ($rules as $rule) {
            TaxRule::query()->updateOrCreate(
                [
                    'tax_code' => $rule['tax_code'],
                    'service_type' => $rule['service_type'],
                    'location_id' => $rule['location_id'],
                    'effective_from' => $rule['effective_from'],
                ],
                array_merge($rule, [
                    'uuid' => (string) Str::uuid(),
                ])
            );
        }
    }
}
