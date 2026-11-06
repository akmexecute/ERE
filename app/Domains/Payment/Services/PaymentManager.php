<?php

declare(strict_types=1);

namespace App\Domains\Payment\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Developer\Models\ContractInstallment;
use App\Domains\Developer\Models\DeveloperSalesContract;
use App\Domains\Developer\Models\UnitReservation;
use App\Domains\Invoice\Models\Invoice;
use App\Domains\Lease\Models\Lease;
use App\Domains\Listing\Models\Listing;
use App\Domains\Offer\Models\PropertyOffer;
use App\Domains\Payment\Contracts\PaymentProviderAdapterInterface;
use App\Domains\Payment\Enums\PaymentMethod;
use App\Domains\Payment\Enums\PaymentType;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\Adapters\BankTransferAdapter;
use App\Domains\Payment\Services\Adapters\CbeBirrAdapter;
use App\Domains\Payment\Services\Adapters\ChapaAdapter;
use App\Domains\Payment\Services\Adapters\TelebirrAdapter;
use App\Domains\RentalFinancial\Models\RentSchedule;
use DomainException;
use Illuminate\Database\Eloquent\Model;

final class PaymentManager
{
    /**
     * Resolve provider adapter for the selected payment method.
     */
    public function adapterFor(PaymentMethod $method): PaymentProviderAdapterInterface
    {
        return match ($method) {
            PaymentMethod::Telebirr => app(TelebirrAdapter::class),
            PaymentMethod::CbeBirr => app(CbeBirrAdapter::class),
            PaymentMethod::Chapa, PaymentMethod::Card => app(ChapaAdapter::class),
            PaymentMethod::BankTransfer => app(BankTransferAdapter::class),
        };
    }

    /**
     * Resolve provider adapter by provider name string.
     */
    public function adapterForProvider(string $provider): PaymentProviderAdapterInterface
    {
        return match (strtolower($provider)) {
            'telebirr' => app(TelebirrAdapter::class),
            'cbe_birr', 'cbe' => app(CbeBirrAdapter::class),
            'chapa', 'card' => app(ChapaAdapter::class),
            'bank_transfer', 'manual_bank' => app(BankTransferAdapter::class),
            default => throw new DomainException("Unsupported payment provider [{$provider}]."),
        };
    }

    /**
     * Calculate authoritative server-side pricing for a payable business model.
     * Never trusts client-submitted totals or tax calculations.
     *
     * @return array{
     *     principal_amount_etb: float,
     *     fee_amount_etb: float,
     *     tax_amount_etb: float,
     *     total_amount_etb: float,
     *     currency: string,
     *     property_id: ?int,
     *     suggested_type: PaymentType
     * }
     */
    public function calculateServerAmounts(Model $payable, ?float $customAmount = null): array
    {
        return match (true) {
            $payable instanceof Booking => [
                'principal_amount_etb' => (float) $payable->accommodation_net_subtotal_etb,
                'fee_amount_etb' => (float) $payable->platform_fee_etb + (float) $payable->cleaning_fee_etb,
                'tax_amount_etb' => (float) $payable->tax_amount_etb,
                'total_amount_etb' => (float) $payable->total_amount_etb,
                'currency' => $payable->currency ?? 'ETB',
                'property_id' => $payable->property_id,
                'suggested_type' => PaymentType::Booking,
            ],

            $payable instanceof UnitReservation => [
                'principal_amount_etb' => number_format((float) $payable->deposit_amount_etb, 2, '.', ''),
                'fee_amount_etb' => '0.00',
                'tax_amount_etb' => '0.00',
                'total_amount_etb' => number_format((float) $payable->deposit_amount_etb, 2, '.', ''),
                'currency' => 'ETB',
                'property_id' => $payable->unit?->property_id ?? null,
                'suggested_type' => PaymentType::Reservation,
            ],

            $payable instanceof PropertyOffer => [
                // Accepted purchase offer for land or existing property
                'principal_amount_etb' => $customAmount !== null && $customAmount > 0
                    ? number_format((float) $customAmount, 2, '.', '')
                    : $payable->calculateRemainingBalanceEtb(),
                'fee_amount_etb' => '0.00',
                'tax_amount_etb' => '0.00',
                'total_amount_etb' => $customAmount !== null && $customAmount > 0
                    ? number_format((float) $customAmount, 2, '.', '')
                    : $payable->calculateRemainingBalanceEtb(),
                'currency' => 'ETB',
                'property_id' => $payable->property_id,
                'suggested_type' => PaymentType::PropertyPurchase,
            ],

            $payable instanceof DeveloperSalesContract => [
                // Developer off-plan contract
                'principal_amount_etb' => $customAmount !== null && $customAmount > 0
                    ? number_format((float) $customAmount, 2, '.', '')
                    : $payable->calculateRemainingBalanceEtb(),
                'fee_amount_etb' => '0.00',
                'tax_amount_etb' => '0.00',
                'total_amount_etb' => $customAmount !== null && $customAmount > 0
                    ? number_format((float) $customAmount, 2, '.', '')
                    : $payable->calculateRemainingBalanceEtb(),
                'currency' => 'ETB',
                'property_id' => $payable->unit?->property_id ?? null,
                'suggested_type' => PaymentType::PropertyPurchase,
            ],

            $payable instanceof ContractInstallment => [
                'principal_amount_etb' => $customAmount !== null && $customAmount > 0
                    ? number_format((float) $customAmount, 2, '.', '')
                    : $payable->calculateOutstandingBalanceEtb(),
                'fee_amount_etb' => '0.00',
                'tax_amount_etb' => '0.00',
                'total_amount_etb' => $customAmount !== null && $customAmount > 0
                    ? number_format((float) $customAmount, 2, '.', '')
                    : $payable->calculateOutstandingBalanceEtb(),
                'currency' => 'ETB',
                'property_id' => $payable->contract?->unit?->property_id ?? null,
                'suggested_type' => PaymentType::DeveloperInstallment,
            ],

            $payable instanceof RentSchedule => [
                'principal_amount_etb' => (float) $payable->outstanding_balance_etb,
                'fee_amount_etb' => 0.00,
                'tax_amount_etb' => 0.00,
                'total_amount_etb' => (float) $payable->outstanding_balance_etb,
                'currency' => 'ETB',
                'property_id' => $payable->lease?->property_id,
                'suggested_type' => PaymentType::Rent,
            ],

            $payable instanceof Lease => [
                'principal_amount_etb' => (float) $payable->monthly_rent_etb,
                'fee_amount_etb' => 0.00,
                'tax_amount_etb' => 0.00,
                'total_amount_etb' => (float) $payable->monthly_rent_etb,
                'currency' => 'ETB',
                'property_id' => $payable->property_id,
                'suggested_type' => PaymentType::Lease,
            ],

            $payable instanceof Invoice => [
                'principal_amount_etb' => (float) $payable->subtotal_amount_etb,
                'fee_amount_etb' => 0.00,
                'tax_amount_etb' => (float) $payable->tax_amount_etb,
                'total_amount_etb' => (float) $payable->outstanding_balance_etb,
                'currency' => 'ETB',
                'property_id' => $payable->property_id,
                'suggested_type' => PaymentType::Service,
            ],

            $payable instanceof \App\Domains\Subscription\Models\UserSubscription => [
                'principal_amount_etb' => number_format((float) $payable->price_etb, 2, '.', ''),
                'fee_amount_etb' => '0.00',
                'tax_amount_etb' => '0.00',
                'total_amount_etb' => number_format((float) $payable->price_etb, 2, '.', ''),
                'currency' => $payable->currency ?? 'ETB',
                'property_id' => null,
                'suggested_type' => PaymentType::Subscription,
            ],

            $payable instanceof Listing => [
                'principal_amount_etb' => 500.00, // Standard listing fee
                'fee_amount_etb' => 0.00,
                'tax_amount_etb' => 75.00, // 15% VAT
                'total_amount_etb' => 575.00,
                'currency' => 'ETB',
                'property_id' => $payable->property_id,
                'suggested_type' => PaymentType::Listing,
            ],

            default => throw new DomainException('Unsupported payable model for server-calculated pricing.'),
        };
    }
}

