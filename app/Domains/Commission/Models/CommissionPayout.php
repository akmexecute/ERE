<?php

declare(strict_types=1);

namespace App\Domains\Commission\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CommissionPayout extends Model
{
    protected $table = 'commission_payouts';

    protected $fillable = [
        'uuid',
        'company_id',
        'branch_id',
        'agent_user_id',
        'property_id',
        'listing_id',
        'sale_price',
        'commission_percentage',
        'total_commission',
        'company_earnings',
        'agent_earnings',
        'status',
        'payout_date',
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'commission_percentage' => 'decimal:3',
        'total_commission' => 'decimal:2',
        'company_earnings' => 'decimal:2',
        'agent_earnings' => 'decimal:2',
        'payout_date' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
