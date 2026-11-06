<?php

declare(strict_types=1);

namespace App\Domains\RentalFinancial\Actions;

use App\Domains\Lease\Models\Lease;
use App\Domains\RentalFinancial\Enums\RentalPaymentStatus;
use App\Domains\RentalFinancial\Models\RentalPayment;
use App\Domains\RentalFinancial\Models\RentSchedule;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RecordRentalPaymentAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(User $tenant, array $payload): RentalPayment
    {
        $idempotencyKey = $payload['idempotency_key'] ?? (string) Str::uuid();

        // 1. Idempotency Check: prevent duplicate payment creation
        /** @var RentalPayment|null $existing */
        $existing = RentalPayment::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        /** @var RentSchedule $schedule */
        $schedule = RentSchedule::query()->findOrFail($payload['rent_schedule_id']);
        /** @var Lease $lease */
        $lease = Lease::query()->findOrFail($payload['lease_id']);

        // 2. Strict entity matching: schedule must belong to lease
        if ((int) $schedule->lease_id !== (int) $lease->id) {
            throw ValidationException::withMessages([
                'rent_schedule_id' => ['The specified rent schedule does not belong to the given lease.'],
            ]);
        }

        // 3. Tenant matching
        if ((int) $lease->tenant_id !== (int) $tenant->id && ! $tenant->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'tenant_id' => ['Only the designated tenant can submit rental payments for this lease.'],
            ]);
        }

        $amount = round((float) $payload['amount_etb'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount_etb' => ['Payment amount must be greater than zero.'],
            ]);
        }

        $paymentRef = $this->generatePaymentReference();

        return DB::transaction(function () use (
            $paymentRef,
            $idempotencyKey,
            $lease,
            $schedule,
            $tenant,
            $amount,
            $payload
        ): RentalPayment {
            return RentalPayment::query()->create([
                'uuid' => (string) Str::uuid(),
                'payment_reference' => $paymentRef,
                'idempotency_key' => $idempotencyKey,
                'lease_id' => $lease->id,
                'rent_schedule_id' => $schedule->id,
                'tenant_id' => $tenant->id,
                'amount_etb' => $amount,
                'payment_method' => $payload['payment_method'] ?? 'bank_transfer',
                'provider_reference' => $payload['provider_reference'] ?? null,
                'status' => RentalPaymentStatus::Pending, // Safe default (Master Prompt Section 10.2)
                'proof_document_id' => $payload['proof_document_id'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ]);
        });
    }

    private function generatePaymentReference(): string
    {
        $year = date('Y');
        $prefix = "PAY-{$year}";
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

            $candidate = sprintf('PAY-%s-%07d', $year, $seq);
        } while (RentalPayment::query()->where('payment_reference', $candidate)->exists());

        return $candidate;
    }
}
