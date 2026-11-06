<?php

declare(strict_types=1);

namespace App\Domains\RentalFinancial\Actions;

use App\Domains\RentalFinancial\Enums\RentalPaymentStatus;
use App\Domains\RentalFinancial\Models\RentalPayment;
use App\Domains\RentalFinancial\Models\RentalReceipt;
use App\Domains\RentalFinancial\Models\RentSchedule;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ConfirmRentalPaymentAction
{
    public function execute(User $confirmer, RentalPayment $payment): RentalReceipt
    {
        $lease = $payment->lease;

        // 1. Authorization: landlord, company representative, finance officer, or admin
        $isLandlord = ($confirmer->id === $lease->landlord_id);
        $isCompany = ($lease->company_id && $confirmer->company_id === $lease->company_id);
        $isStaff = $confirmer->hasAnyRole(['finance_officer', 'property_admin', 'platform_admin', 'super_admin']);

        if (! $isLandlord && ! $isCompany && ! $isStaff) {
            throw new AuthorizationException('You are not authorized to confirm rental payments for this lease.');
        }

        return DB::transaction(function () use ($confirmer, $payment, $lease): RentalReceipt {
            /** @var RentalPayment $lockedPayment */
            $lockedPayment = RentalPayment::query()
                ->where('id', $payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->status === RentalPaymentStatus::Confirmed) {
                // Return existing receipt idempotently
                return $lockedPayment->receipt()->firstOrFail();
            }

            if ($lockedPayment->status !== RentalPaymentStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => ["Cannot confirm a payment with status '{$lockedPayment->status->value}'."],
                ]);
            }

            /** @var RentSchedule $lockedSchedule */
            $lockedSchedule = RentSchedule::query()
                ->where('id', $lockedPayment->rent_schedule_id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Mark payment confirmed
            $lockedPayment->update([
                'status' => RentalPaymentStatus::Confirmed,
                'confirmed_at' => now(),
                'confirmed_by_user_id' => $confirmer->id,
            ]);

            // 3. Reconcile rent schedule financials
            $lockedSchedule->reconcileFinancials();

            // 4. Generate persistent, auditable Rental Receipt
            $receiptNumber = $this->generateReceiptNumber();

            return RentalReceipt::query()->create([
                'uuid' => (string) Str::uuid(),
                'receipt_number' => $receiptNumber,
                'rental_payment_id' => $lockedPayment->id,
                'lease_id' => $lease->id,
                'rent_schedule_id' => $lockedSchedule->id,
                'payer_user_id' => $lockedPayment->tenant_id,
                'recipient_user_id' => $lease->landlord_id,
                'amount_etb' => $lockedPayment->amount_etb,
                'currency' => 'ETB',
                'payment_method' => $lockedPayment->payment_method,
                'issued_at' => now(),
                'receipt_data_json' => [
                    'payment_reference' => $lockedPayment->payment_reference,
                    'lease_code' => $lease->lease_code,
                    'period_label' => $lockedSchedule->period_label,
                    'tenant_name' => $lockedPayment->tenant->name,
                    'tenant_phone' => $lockedPayment->tenant->phone,
                    'landlord_name' => $lease->landlord->name,
                    'property_title' => $lease->property->title_en,
                    'property_code' => $lease->property->property_code,
                    'unit_number' => $lease->projectUnit?->unit_number,
                    'confirmed_by' => $confirmer->name,
                ],
            ]);
        });
    }

    private function generateReceiptNumber(): string
    {
        $year = date('Y');
        $prefix = "RCT-{$year}";
        $driver = DB::getDriverName();

        do {
            if ($driver === 'pgsql' || $driver === 'sqlite') {
                $timestampSql = $driver === 'pgsql' ? 'NOW()' : 'CURRENT_TIMESTAMP';
                $sql = "INSERT INTO property_code_sequences (prefix_key, last_value, updated_at)
                        VALUES (?, 1, {$timestampSql})
                        ON CONFLICT (prefix_key)
                        DO UPDATE SET last_value = property_code_sequences.last_value + 1, updated_at = {$timestampSql}
                        RETURNING last_value";

                $row = DB::selectOne($sql, [$prefix]);
                $seq = (int) ($row->last_value ?? 1);
            } else {
                $seq = (int) DB::table('property_code_sequences')
                    ->where('prefix_key', $prefix)
                    ->increment('last_value');

                if ($seq === 0) {
                    DB::table('property_code_sequences')->insert([
                        'prefix_key' => $prefix,
                        'last_value' => 1,
                        'updated_at' => now(),
                    ]);
                    $seq = 1;
                }
            }

            $candidate = sprintf('RCT-%s-%07d', $year, $seq);
        } while (RentalReceipt::query()->where('receipt_number', $candidate)->exists());

        return $candidate;
    }
}
