<?php

declare(strict_types=1);

namespace App\Domains\Developer\Actions;

use App\Domains\Developer\Enums\ReservationStatus;
use App\Domains\Developer\Enums\SalesContractStatus;
use App\Domains\Developer\Enums\UnitInventoryStatus;
use App\Domains\Developer\Models\ContractInstallment;
use App\Domains\Developer\Models\DeveloperSalesContract;
use App\Domains\Developer\Models\ProjectUnit;
use App\Domains\Developer\Models\UnitReservation;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ConvertReservationToContractAction
{
    public function __construct(
        private CalculateInstallmentScheduleAction $calculator = new CalculateInstallmentScheduleAction()
    ) {
    }

    /**
     * Converts an active confirmed reservation into a legal developer sales contract
     * and generates authoritative installment schedules based on the payment plan snapshot.
     */
    public function execute(
        int $reservationId,
        bool $disclosureAccepted,
        ?string $clientIp = null,
        string $disclosureVersion = 'v1.0-proc1357'
    ): DeveloperSalesContract {
        // Enforce Proclamation No. 1357/2024 statutory disclosure review and consent
        if (! $disclosureAccepted) {
            throw new InvalidArgumentException(
                "Buyer must explicitly review and accept off-plan statutory disclosures per Proclamation No. 1357/2024."
            );
        }

        return DB::transaction(function () use ($reservationId, $clientIp, $disclosureVersion): DeveloperSalesContract {
            /** @var UnitReservation $reservation */
            $reservation = UnitReservation::where('id', $reservationId)
                ->lockForUpdate()
                ->firstOrFail();

            // Validate reservation state
            if ($reservation->status !== ReservationStatus::Reserved) {
                throw new DomainException(sprintf(
                    "Cannot convert reservation %s to a contract because its status is '%s'. Only confirmed 'reserved' holds can be converted.",
                    $reservation->reservation_code,
                    $reservation->status->value
                ));
            }

            // Check if hold has expired
            if ($reservation->isExpired()) {
                throw new DomainException(sprintf(
                    "Reservation %s has expired on %s and cannot be converted.",
                    $reservation->reservation_code,
                    $reservation->hold_expires_at->toIso8601String()
                ));
            }

            // Lock inventory unit
            /** @var ProjectUnit $unit */
            $unit = ProjectUnit::where('id', $reservation->project_unit_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Check idempotency: if contract already exists, return it
            $existingContract = DeveloperSalesContract::where('reservation_id', $reservation->id)->first();
            if ($existingContract) {
                return $existingContract->load('installments');
            }

            $contractPrice = (float) $reservation->agreed_price_etb;
            $depositPaid = (float) $reservation->deposit_amount_etb;
            $balancePayable = round($contractPrice - $depositPaid, 2);

            $planSnapshot = $reservation->payment_plan_snapshot_json ?? [];
            $installmentsTemplate = $planSnapshot['installments'] ?? [
                ['sequence_number' => 1, 'milestone_label_en' => 'Reservation Deposit', 'percentage' => 10.0],
                ['sequence_number' => 2, 'milestone_label_en' => 'Foundation Stage', 'percentage' => 20.0],
                ['sequence_number' => 3, 'milestone_label_en' => 'Structure Stage', 'percentage' => 20.0],
                ['sequence_number' => 4, 'milestone_label_en' => 'Roofing Stage', 'percentage' => 20.0],
                ['sequence_number' => 5, 'milestone_label_en' => 'Finishing Stage', 'percentage' => 20.0],
                ['sequence_number' => 6, 'milestone_label_en' => 'Handover', 'percentage' => 10.0],
            ];

            // Build Immutable Seller Identity Snapshot (SRS Section 4.5 & Proclamation No. 1357/2024)
            $company = $unit->project?->company;
            $agent = $reservation->agent;
            $buyer = $reservation->buyer;
            $contractCode = $reservation->reservation_code ? 'CNT-' . date('Y') . '-' . substr($reservation->reservation_code, -6) : null;

            $sellerUser = $agent ?? $company?->owner ?? $buyer;

            $sellerSnapshot = app(\App\Domains\Identity\Services\SellerIdentitySnapshotService::class)->buildSnapshot(
                seller: $sellerUser,
                propertyOrProject: $unit->project,
                buyer: $buyer,
                agent: $agent,
                company: $company,
                transactionRef: $contractCode,
                agreedPrice: $contractPrice,
                additionalEvidence: [
                    'developer_company_id' => $company?->id,
                    'project_unit_id' => $unit->id,
                    'unit_code' => $unit->unit_number ?? 'UNT-001',
                    'unit_type' => $unit->unit_type,
                    'floor_number' => $unit->floor?->floor_number ?? 1,
                    'deposit_paid_etb' => $depositPaid,
                    'statutory_basis' => 'Proclamation No. 1357/2024 Developer Sales & Real Estate Mandate',
                ]
            );

            // 1. Create Sales Contract
            $contract = DeveloperSalesContract::create([
                'reservation_id' => $reservation->id,
                'project_unit_id' => $unit->id,
                'project_id' => $unit->project_id,
                'buyer_user_id' => $reservation->buyer_user_id,
                'developer_company_id' => $unit->project->company_id,
                'agent_user_id' => $reservation->agent_user_id,
                'contract_price_etb' => $contractPrice,
                'deposit_paid_etb' => $depositPaid,
                'balance_payable_etb' => $balancePayable,
                'payment_plan_snapshot_json' => $planSnapshot,
                'seller_identity_snapshot_json' => $sellerSnapshot,
                'status' => SalesContractStatus::Active,
                'disclosure_version' => $disclosureVersion,
                'disclosure_accepted_by_buyer' => true,
                'disclosure_accepted_at' => now(),
                'disclosure_client_ip' => $clientIp,
                'signed_at' => now(),
            ]);

            // 2. Generate Authoritative Installment Schedule with Cent-Absorption
            $scheduleResult = $this->calculator->execute($contractPrice, $installmentsTemplate);

            foreach ($scheduleResult['schedule'] as $item) {
                // If this is the reservation deposit installment (sequence 1), mark as settled
                $isDepositInstallment = ($item['sequence_number'] === 1);
                $paidAmt = $isDepositInstallment ? $item['amount_etb'] : 0.00;
                $balance = round($item['amount_etb'] - $paidAmt, 2);
                $status = $isDepositInstallment ? 'paid' : 'pending';

                ContractInstallment::create([
                    'contract_id' => $contract->id,
                    'sequence_number' => $item['sequence_number'],
                    'milestone_label_en' => $item['milestone_label_en'],
                    'milestone_label_am' => $item['milestone_label_am'] ?? null,
                    'percentage' => $item['percentage'],
                    'amount_etb' => $item['amount_etb'],
                    'paid_amount_etb' => $paidAmt,
                    'outstanding_balance_etb' => $balance,
                    'status' => $status,
                    'paid_at' => $isDepositInstallment ? now() : null,
                ]);
            }

            // 3. Update Unit Inventory to Contracted
            $unit->update([
                'status' => UnitInventoryStatus::Contracted,
            ]);

            // 4. Update Reservation to Converted
            $reservation->update([
                'status' => ReservationStatus::Converted,
                'converted_at' => now(),
            ]);

            return $contract->load('installments');
        });
    }
}
