<?php

declare(strict_types=1);

namespace App\Domains\Commission\Models;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyBranch;
use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CommissionCalculation extends Model
{
    protected $table = 'commission_calculations';

    public const STATUS_PENDING_SETTLEMENT = 'pending_settlement';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'uuid',
        'commission_rule_id',
        'company_id',
        'branch_id',
        'agent_user_id',
        'property_id',
        'listing_id',
        'transaction_type',
        'transaction_reference',
        'transaction_value_etb',
        'applied_rate_pct',
        'gross_commission_etb',
        'agent_payout_etb',
        'company_share_etb',
        'status',
    ];

    protected $casts = [
        'transaction_value_etb' => 'decimal:2',
        'applied_rate_pct' => 'decimal:3',
        'gross_commission_etb' => 'decimal:2',
        'agent_payout_etb' => 'decimal:2',
        'company_share_etb' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        static::updating(function (self $model): void {
            // Once approved or paid, financial ledger values cannot be mutated
            if (in_array($model->getOriginal('status'), [self::STATUS_APPROVED, self::STATUS_PAID], true)) {
                $dirty = array_keys($model->getDirty());
                $protectedFinancialFields = [
                    'transaction_value_etb',
                    'applied_rate_pct',
                    'gross_commission_etb',
                    'agent_payout_etb',
                    'company_share_etb',
                    'commission_rule_id',
                    'agent_user_id',
                    'property_id',
                    'company_id',
                ];
                if (array_intersect($dirty, $protectedFinancialFields) !== []) {
                    throw new DomainException(
                        'Immutable commission ledger: Settled financial calculation figures cannot be altered.'
                    );
                }
            }
        });

        static::deleting(function (self $model): void {
            throw new DomainException(
                'Immutable commission ledger: Settled commission calculation records cannot be deleted.'
            );
        });
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(CompanyBranch::class, 'branch_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }
}

