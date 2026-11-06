<?php

declare(strict_types=1);

namespace App\Domains\Financial\Models;

use DomainException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class LedgerEntry extends Model
{
    use HasUuids;

    protected $table = 'ledger_entries';

    public $timestamps = false;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'posting_group_id',
        'ledger_account_id',
        'entry_type',
        'amount_etb',
        'currency',
        'description',
        'source_reference',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_etb' => 'decimal:2',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
            if (! in_array($model->entry_type, ['debit', 'credit'], true)) {
                throw new DomainException("Invalid entry type [{$model->entry_type}]. Must be 'debit' or 'credit'.");
            }
            if ((float) $model->amount_etb <= 0.0) {
                throw new DomainException('Ledger entry amount must be strictly positive.');
            }
        });

        static::updating(function (): void {
            throw new DomainException('Append-only financial violation: Ledger entries are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new DomainException('Append-only financial violation: Ledger entries cannot be deleted.');
        });
    }

    public function postingGroup(): BelongsTo
    {
        return $this->belongsTo(JournalPostingGroup::class, 'posting_group_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->account();
    }
}

