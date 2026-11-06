<?php

declare(strict_types=1);

namespace App\Domains\Payment\Actions;

use App\Domains\Payment\Enums\PaymentMethod;
use App\Domains\Payment\Enums\PaymentStatus;
use App\Domains\Payment\Enums\PaymentType;
use App\Domains\Payment\Enums\PurchaseAllocationStatus;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Models\PaymentIdempotency;
use App\Domains\Payment\Services\PaymentManager;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InitiatePaymentAction
{
    public function __construct(
        private readonly PaymentManager $paymentManager
    ) {}

    /**
     * Initiate a database-backed idempotent payment intent.
     *
     * @param  array{
     *     payable: Model,
     *     payment_method: PaymentMethod|string,
     *     idempotency_key?: string,
     *     custom_amount_etb?: float|null,
     *     payment_type?: PaymentType|string|null,
     *     notes?: string|null
     * }  $params
     * @return array{
     *     payment: Payment,
     *     provider_response: array<string, mixed>,
     *     is_duplicate_replay: bool
     * }
     */
    public function execute(User $customer, array $params): array
    {
        $payable = $params['payable'];
        $method = $params['payment_method'] instanceof PaymentMethod
            ? $params['payment_method']
            : PaymentMethod::from((string) $params['payment_method']);

        $idempotencyKey = $params['idempotency_key'] ?? (string) Str::uuid();

        // Compute request fingerprint hash
        $requestPayload = [
            'customer_id' => $customer->id,
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'method' => $method->value,
            'custom_amount' => $params['custom_amount_etb'] ?? null,
            'payment_type' => $params['payment_type'] ?? null,
        ];
        $requestHash = hash('sha256', (string) json_encode($requestPayload));

        return DB::transaction(function () use ($customer, $payable, $method, $idempotencyKey, $requestHash, $params) {
            // 1. Check idempotency record with row locking
            $idempotencyRecord = PaymentIdempotency::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($idempotencyRecord) {
                if ($idempotencyRecord->request_hash !== $requestHash) {
                    throw new DomainException("Idempotency conflict: key [{$idempotencyKey}] was already used with a different request payload.");
                }

                if ($idempotencyRecord->payment) {
                    $adapter = $this->paymentManager->adapterFor($idempotencyRecord->payment->payment_method);
                    $providerResponse = $idempotencyRecord->response_payload_json
                        ?? $adapter->initiatePayment($idempotencyRecord->payment);

                    return [
                        'payment' => $idempotencyRecord->payment,
                        'provider_response' => $providerResponse,
                        'is_duplicate_replay' => true,
                    ];
                }
            }

            // 2. Row-level lock on canonical purchase agreement to prevent concurrent over-allocation
            $lockedPayable = match (true) {
                $payable instanceof \App\Domains\Offer\Models\PropertyOffer => \App\Domains\Offer\Models\PropertyOffer::query()->where('id', $payable->id)->lockForUpdate()->firstOrFail(),
                $payable instanceof \App\Domains\Developer\Models\DeveloperSalesContract => \App\Domains\Developer\Models\DeveloperSalesContract::query()->where('id', $payable->id)->lockForUpdate()->firstOrFail(),
                $payable instanceof \App\Domains\Developer\Models\ContractInstallment => tap(\App\Domains\Developer\Models\ContractInstallment::query()->where('id', $payable->id)->lockForUpdate()->firstOrFail(), function ($inst): void {
                    if ($inst->contract_id) {
                        \App\Domains\Developer\Models\DeveloperSalesContract::query()->where('id', $inst->contract_id)->lockForUpdate()->first();
                    }
                }),
                $payable instanceof \App\Domains\Developer\Models\UnitReservation => \App\Domains\Developer\Models\UnitReservation::query()->where('id', $payable->id)->lockForUpdate()->firstOrFail(),
                default => $payable,
            };

            // 3. Validate customer authorization to pay this payable
            $this->validateCustomerAuthorization($customer, $lockedPayable);

            // Pre-payment contract eligibility check and overpayment guard
            if (method_exists($lockedPayable, 'validateEligibleForPayment')) {
                $lockedPayable->validateEligibleForPayment($params['custom_amount_etb'] ?? null, true);
            }

            // 4. Compute server-side authoritative pricing
            $serverPricing = $this->paymentManager->calculateServerAmounts(
                $lockedPayable,
                $params['custom_amount_etb'] ?? null
            );

            $paymentType = isset($params['payment_type'])
                ? ($params['payment_type'] instanceof PaymentType ? $params['payment_type'] : PaymentType::from((string) $params['payment_type']))
                : $serverPricing['suggested_type'];

            // 5. Create Payment record
            $payment = Payment::query()->create([
                'idempotency_key' => $idempotencyKey,
                'customer_user_id' => $customer->id,
                'payable_type' => $lockedPayable->getMorphClass(),
                'payable_id' => $lockedPayable->getKey(),
                'property_id' => $serverPricing['property_id'],
                'payment_type' => $paymentType,
                'principal_amount_etb' => $serverPricing['principal_amount_etb'],
                'fee_amount_etb' => $serverPricing['fee_amount_etb'],
                'tax_amount_etb' => $serverPricing['tax_amount_etb'],
                'total_amount_etb' => $serverPricing['total_amount_etb'],
                'currency' => $serverPricing['currency'],
                'payment_method' => $method,
                'provider' => $method->value,
                'status' => PaymentStatus::Pending,
                'allocation_status' => PurchaseAllocationStatus::Unallocated,
                'allocated_amount_etb' => '0.00',
                'unapplied_amount_etb' => '0.00',
                'reservation_expires_at' => now()->addMinutes(30),
                'metadata_json' => [
                    'initiated_by_user_id' => $customer->id,
                    'notes' => $params['notes'] ?? null,
                ],
            ]);

            // 6. Initiate via adapter
            $adapter = $this->paymentManager->adapterFor($method);
            $providerResponse = $adapter->initiatePayment($payment);

            if (! empty($providerResponse['provider_reference'])) {
                $payment->provider_transaction_reference = $providerResponse['provider_reference'];
                $payment->save();
            }

            // 6. Record or update idempotency tracker
            PaymentIdempotency::query()->updateOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'request_hash' => $requestHash,
                    'customer_user_id' => $customer->id,
                    'payment_id' => $payment->id,
                    'status' => 'completed',
                    'response_payload_json' => $providerResponse,
                ]
            );

            return [
                'payment' => $payment,
                'provider_response' => $providerResponse,
                'is_duplicate_replay' => false,
            ];
        });
    }

    private function validateCustomerAuthorization(User $customer, Model $payable): void
    {
        // Whitelist ownership checks
        $authorized = match (true) {
            $payable instanceof \App\Domains\Booking\Models\Booking => (int) $payable->guest_user_id === $customer->id,
            $payable instanceof \App\Domains\Developer\Models\UnitReservation => (int) $payable->buyer_user_id === $customer->id,
            $payable instanceof \App\Domains\Developer\Models\DeveloperSalesContract => (int) $payable->buyer_user_id === $customer->id,
            $payable instanceof \App\Domains\Developer\Models\ContractInstallment => (int) $payable->contract?->buyer_user_id === $customer->id,
            $payable instanceof \App\Domains\Offer\Models\PropertyOffer => (int) $payable->buyer_user_id === $customer->id,
            $payable instanceof \App\Domains\RentalFinancial\Models\RentSchedule => (int) $payable->lease?->tenant_id === $customer->id,
            $payable instanceof \App\Domains\Lease\Models\Lease => (int) $payable->tenant_id === $customer->id,
            $payable instanceof \App\Domains\Invoice\Models\Invoice => (int) $payable->user_id === $customer->id,
            $payable instanceof \App\Domains\Listing\Models\Listing => (int) $payable->property?->owner_id === $customer->id,
            $payable instanceof \App\Domains\Subscription\Models\UserSubscription => (int) $payable->user_id === $customer->id,
            default => true,
        };

        if (! $authorized && ! $customer->hasRole('super_admin') && ! $customer->hasRole('platform_admin')) {
            throw new DomainException('Unauthorized payment attempt: You do not own this payable obligation.');
        }
    }
}

