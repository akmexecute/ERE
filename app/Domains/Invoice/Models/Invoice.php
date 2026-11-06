<?php

declare(strict_types=1);

namespace App\Domains\Invoice\Models;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyBranch;
use App\Domains\User\Models\User;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Invoice extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            if ($invoice->getOriginal('payment_status') === 'voided') {
                throw new \DomainException('Voided invoices are strictly immutable and cannot be modified.');
            }
        });

        static::deleting(function (Invoice $invoice): void {
            $status = $invoice->getOriginal('payment_status') ?? $invoice->payment_status;
            if ($status === 'paid' || $status === 'voided' || (float) $invoice->paid_amount_etb > 0.00) {
                throw new \DomainException('Invoices that are paid, voided, or have recorded payments cannot be deleted to protect financial audit trails.');
            }
        });
    }

    public function voidInvoice(User $actor, string $reason): self
    {
        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw new \InvalidArgumentException('A meaningful void reason is required.');
        }

        $isGlobalAdmin = $actor->hasRole('super_admin') || $actor->hasRole('platform_admin');
        if (! $isGlobalAdmin && ! \App\Domains\Portfolio\Services\MultiTenantOrganizationGuard::canAccess($actor, $this)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('You are not authorized to void this invoice.');
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($actor, $cleanReason): self {
            /** @var Invoice $locked */
            $locked = self::query()->lockForUpdate()->findOrFail($this->id);

            if ($locked->payment_status === 'voided') {
                throw new \DomainException('Invoice is already voided.');
            }

            if ($locked->payment_status === 'paid' || (float) $locked->paid_amount_etb >= (float) $locked->total_amount_etb) {
                throw new \DomainException('Fully paid invoices cannot be voided directly; a formal refund or credit note is required.');
            }

            $nowStr = now()->toDateTimeString();
            $paidInfo = (float) $locked->paid_amount_etb > 0.00
                ? ' Note: Previously recorded payment of ETB ' . number_format((float) $locked->paid_amount_etb, 2) . ' remains preserved in ledger records.'
                : '';
            $voidNote = "[VOIDED by {$actor->name} at {$nowStr}]: {$cleanReason}.{$paidInfo}";
            $existingNotes = $locked->notes ? $locked->notes . "\n" : '';

            $locked->payment_status = 'voided';
            $locked->outstanding_balance_etb = 0.00;
            $locked->notes = $existingNotes . $voidNote;
            $locked->save();

            $this->payment_status = 'voided';
            $this->outstanding_balance_etb = 0.00;
            $this->notes = $locked->notes;
            $this->syncOriginal();

            return $this;
        });
    }

    public function recordPayment(float $amount, ?string $reference = null): self
    {
        if ($amount <= 0.00) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($amount, $reference): self {
            /** @var Invoice $locked */
            $locked = self::query()->lockForUpdate()->findOrFail($this->id);

            if ($locked->payment_status === 'voided') {
                throw new \DomainException('Cannot record payment on a voided invoice.');
            }

            if ($locked->payment_status === 'paid' || (float) $locked->outstanding_balance_etb <= 0.00) {
                throw new \DomainException('Invoice is already fully settled.');
            }

            $currentPaid = (float) $locked->paid_amount_etb;
            $totalAmount = (float) $locked->total_amount_etb;
            $newPaid = round(min($totalAmount, $currentPaid + $amount), 2);
            $newOutstanding = round(max(0.00, $totalAmount - $newPaid), 2);
            $newStatus = $newOutstanding <= 0.00 ? 'paid' : 'partially_paid';

            $noteLine = $reference
                ? "[Payment of ETB " . number_format($amount, 2) . " recorded (Ref: {$reference})]"
                : "[Payment of ETB " . number_format($amount, 2) . " recorded]";
            $existingNotes = $locked->notes ? $locked->notes . "\n" : '';

            $locked->paid_amount_etb = $newPaid;
            $locked->outstanding_balance_etb = $newOutstanding;
            $locked->payment_status = $newStatus;
            $locked->notes = $existingNotes . $noteLine;
            $locked->save();

            $this->paid_amount_etb = $newPaid;
            $this->outstanding_balance_etb = $newOutstanding;
            $this->payment_status = $newStatus;
            $this->notes = $locked->notes;
            $this->syncOriginal();

            return $this;
        });
    }

    protected $fillable = [
        'uuid',
        'invoice_number',
        'company_id',
        'branch_id',
        'customer_user_id',
        'customer_name_snapshot',
        'customer_tin_snapshot',
        'service_type',
        'reference_type',
        'reference_id',
        'currency',
        'subtotal_amount_etb',
        'tax_amount_etb',
        'total_amount_etb',
        'paid_amount_etb',
        'outstanding_balance_etb',
        'issue_date',
        'due_date',
        'payment_status',
        'tax_snapshots_json',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_amount_etb' => 'decimal:2',
            'tax_amount_etb' => 'decimal:2',
            'total_amount_etb' => 'decimal:2',
            'paid_amount_etb' => 'decimal:2',
            'outstanding_balance_etb' => 'decimal:2',
            'issue_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'tax_snapshots_json' => 'array',
        ];
    }

    public function getSubtotalEtbAttribute(): float
    {
        return (float) ($this->subtotal_amount_etb ?? 0.0);
    }

    public function getTotalEtbAttribute(): float
    {
        return (float) ($this->total_amount_etb ?? 0.0);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(CompanyBranch::class, 'branch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }
}
