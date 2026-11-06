<?php

declare(strict_types=1);

namespace App\Domains\Payment\Models;

use App\Domains\Payment\Enums\BankTransferVerificationStatus;
use App\Domains\User\Models\User;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class BankTransferProof extends Model
{
    use Auditable, HasUuids;

    protected $table = 'bank_transfer_proofs';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'payment_id',
        'transfer_reference',
        'bank_name',
        'account_number',
        'account_holder_name',
        'deposited_amount_etb',
        'deposited_at',
        'proof_file_path',
        'notes',
        'verification_status',
        'verified_by_user_id',
        'verified_at',
        'rejection_reason',
        'audit_trail_json',
    ];

    protected function casts(): array
    {
        return [
            'deposited_amount_etb' => 'decimal:2',
            'deposited_at' => 'date',
            'verified_at' => 'immutable_datetime',
            'verification_status' => BankTransferVerificationStatus::class,
            'audit_trail_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function recordAuditEntry(int $officerId, string $action, string $notes): void
    {
        $trail = $this->audit_trail_json ?? [];
        $trail[] = [
            'officer_id' => $officerId,
            'action' => $action,
            'notes' => $notes,
            'timestamp' => now()->toIso8601String(),
        ];
        $this->audit_trail_json = $trail;
    }
}

