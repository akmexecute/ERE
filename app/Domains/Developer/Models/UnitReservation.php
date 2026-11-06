<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use App\Domains\Developer\Enums\ReservationStatus;
use App\Domains\Payment\Enums\PaymentStatus;
use App\Domains\Payment\Models\Payment;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class UnitReservation extends Model
{
    protected $table = 'unit_reservations';

    protected $fillable = [
        'uuid',
        'reservation_code',
        'idempotency_key',
        'project_unit_id',
        'project_id',
        'buyer_user_id',
        'agent_user_id',
        'crm_lead_id',
        'payment_plan_id',
        'payment_plan_snapshot_json',
        'agreed_price_etb',
        'deposit_amount_etb',
        'deposit_percentage',
        'status',
        'hold_expires_at',
        'requested_at',
        'reserved_at',
        'converted_at',
        'cancelled_at',
        'expired_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'status' => ReservationStatus::class,
        'agreed_price_etb' => 'decimal:2',
        'deposit_amount_etb' => 'decimal:2',
        'deposit_percentage' => 'decimal:2',
        'hold_expires_at' => 'datetime',
        'requested_at' => 'datetime',
        'reserved_at' => 'datetime',
        'converted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expired_at' => 'datetime',
        'payment_plan_snapshot_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->reservation_code)) {
                $model->reservation_code = 'RES-' . date('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            }
            if (empty($model->requested_at)) {
                $model->requested_at = now();
            }
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProjectUnit::class, 'project_unit_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProject::class, 'project_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    public function contract(): HasOne
    {
        return $this->hasOne(DeveloperSalesContract::class, 'reservation_id');
    }

    public function isExpired(): bool
    {
        return $this->hold_expires_at && $this->hold_expires_at->isPast();
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function calculateTotalPaidEtb(): string
    {
        $successfulPayments = $this->payments()
            ->whereIn('status', [PaymentStatus::Successful->value, PaymentStatus::PartiallyRefunded->value])
            ->get();

        $totalPaid = '0.00';
        foreach ($successfulPayments as $payment) {
            $captured = number_format((float) $payment->total_amount_etb, 2, '.', '');
            $refunded = number_format((float) ($payment->refunded_amount_etb ?? 0.0), 2, '.', '');
            $net = bcsub($captured, $refunded, 2);
            $totalPaid = bcadd($totalPaid, $net, 2);
        }

        return $totalPaid;
    }

    public function validateEligibleForPayment(float|string|null $requestedAmount = null): void
    {
        if ($this->status !== ReservationStatus::Reserved && $this->status !== ReservationStatus::PendingHold) {
            throw new DomainException("Cannot initiate payment: Unit reservation [#{$this->reservation_code}] is in '{$this->status->value}' state.");
        }

        if ($this->isExpired()) {
            throw new DomainException("Cannot initiate payment: Unit reservation [#{$this->reservation_code}] hold has expired.");
        }
    }
}
