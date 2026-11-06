<?php

declare(strict_types=1);

namespace App\Domains\RentalFinancial\Models;

use App\Domains\Lease\Models\Lease;
use App\Domains\RentalFinancial\Enums\RentScheduleStatus;
use App\Support\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RentSchedule extends Model
{
    use Auditable, HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'lease_id',
        'period_sequence',
        'period_label',
        'period_start_date',
        'period_end_date',
        'due_date',
        'base_rent_etb',
        'adjustments_amount_etb',
        'net_amount_due_etb',
        'confirmed_paid_etb',
        'outstanding_balance_etb',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RentScheduleStatus::class,
            'period_sequence' => 'integer',
            'period_start_date' => 'date',
            'period_end_date' => 'date',
            'due_date' => 'date',
            'base_rent_etb' => 'decimal:2',
            'adjustments_amount_etb' => 'decimal:2',
            'net_amount_due_etb' => 'decimal:2',
            'confirmed_paid_etb' => 'decimal:2',
            'outstanding_balance_etb' => 'decimal:2',
            'paid_at' => 'immutable_datetime',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentalPayment::class, 'rent_schedule_id')->orderByDesc('created_at');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(RentalReceipt::class, 'rent_schedule_id')->orderByDesc('issued_at');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(RentalPaymentAdjustment::class, 'rent_schedule_id')->orderByDesc('created_at');
    }

    /**
     * Reconciles the schedule's net amount due, paid amount, balance, and status
     * using exact monetary calculations (SRS Section 153 & Section 189).
     */
    public function reconcileFinancials(?Carbon $referenceClock = null): void
    {
        $now = $referenceClock ?? now();

        // 1. Calculate effective confirmed paid across confirmed payments (SRS Section 189)
        $confirmedPayments = $this->payments()->where('status', 'confirmed')->get();
        $totalEffectivePaid = 0.00;
        foreach ($confirmedPayments as $payment) {
            $totalEffectivePaid += $payment->effectiveAmountEtb();
        }
        $totalEffectivePaid = round(max(0.00, $totalEffectivePaid), 2);

        // 2. Adjustments attached to this schedule
        $periodAdjustments = (float) $this->adjustments()->sum('adjustment_amount_etb');

        $baseRent = (float) $this->base_rent_etb;
        $netDue = round(max(0.00, $baseRent), 2);
        $balance = round(max(0.00, $netDue - $totalEffectivePaid), 2);

        // 3. Derive status
        $status = RentScheduleStatus::Upcoming;
        if ($balance <= 0.00 && $netDue > 0.00 && $totalEffectivePaid >= $netDue) {
            $status = RentScheduleStatus::Paid;
        } elseif ($totalEffectivePaid > 0.00 && $balance > 0.00) {
            $status = RentScheduleStatus::PartiallyPaid;
        } elseif ($now->toDateString() > $this->due_date->toDateString()) {
            $status = RentScheduleStatus::Overdue;
        } elseif ($now->toDateString() >= $this->period_start_date->toDateString()) {
            $status = RentScheduleStatus::Pending;
        }

        $this->update([
            'adjustments_amount_etb' => $periodAdjustments,
            'net_amount_due_etb' => $netDue,
            'confirmed_paid_etb' => $totalEffectivePaid,
            'outstanding_balance_etb' => $balance,
            'status' => $status,
            'paid_at' => $status === RentScheduleStatus::Paid ? ($this->paid_at ?? $now) : null,
        ]);
    }
}
