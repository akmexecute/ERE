<?php

declare(strict_types=1);

namespace App\Domains\Financial\Models;

use App\Domains\User\Models\User;
use App\Support\Traits\Auditable;
use DomainException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

final class JournalPostingGroup extends Model
{
    use Auditable, HasUuids;

    protected $table = 'journal_posting_groups';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'posting_reference',
        'source_type',
        'source_id',
        'event_type',
        'description',
        'actor_user_id',
        'total_debit_amount_etb',
        'total_credit_amount_etb',
        'is_balanced',
        'reversed_posting_group_id',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'total_debit_amount_etb' => 'decimal:2',
            'total_credit_amount_etb' => 'decimal:2',
            'is_balanced' => 'boolean',
            'posted_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->posting_reference)) {
                $model->posting_reference = 'JRN-' . date('Y') . '-' . str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT);
            }
        });

        static::updating(function (): void {
            throw new DomainException('Immutable financial ledger: Journal posting groups cannot be updated after posting. Create a reversing journal entry instead.');
        });

        static::deleting(function (): void {
            throw new DomainException('Immutable financial ledger: Journal posting groups cannot be deleted.');
        });
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function reversedPostingGroup(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_posting_group_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'posting_group_id');
    }
}

