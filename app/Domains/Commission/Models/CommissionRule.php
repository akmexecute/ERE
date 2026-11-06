<?php

declare(strict_types=1);

namespace App\Domains\Commission\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model
{
    protected $table = 'commission_rules';

    protected $fillable = [
        'name',
        'company_id',
        'property_type_id',
        'agent_user_id',
        'transaction_type',
        'min_property_value',
        'max_property_value',
        'commission_rate_pct',
        'base_commission_percentage',
        'flat_bonus_etb',
        'agent_split_pct',
        'agent_split_percentage',
        'company_split_pct',
        'company_split_percentage',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'min_property_value' => 'decimal:2',
        'max_property_value' => 'decimal:2',
        'commission_rate_pct' => 'decimal:3',
        'base_commission_percentage' => 'decimal:3',
        'flat_bonus_etb' => 'decimal:2',
        'agent_split_pct' => 'decimal:2',
        'agent_split_percentage' => 'decimal:2',
        'company_split_pct' => 'decimal:2',
        'company_split_percentage' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function getBaseCommissionPercentageAttribute(?float $value): float
    {
        return (float) ($value ?? $this->attributes['commission_rate_pct'] ?? 2.0);
    }

    public function getCompanySplitPercentageAttribute(?float $value): float
    {
        return (float) ($value ?? $this->attributes['company_split_pct'] ?? 50.0);
    }

    public function getAgentSplitPercentageAttribute(?float $value): float
    {
        return (float) ($value ?? $this->attributes['agent_split_pct'] ?? 50.0);
    }
}
