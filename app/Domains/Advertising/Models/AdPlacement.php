<?php

declare(strict_types=1);

namespace App\Domains\Advertising\Models;

use App\Domains\Advertising\Enums\AdPlacementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AdPlacement extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ad_placements';

    protected $fillable = [
        'placement',
        'name_en',
        'name_am',
        'name_om',
        'description_en',
        'price_etb',
        'duration_days',
        'impression_limit',
        'click_limit',
        'is_active',
        'is_public',
        'eligible_plan_codes',
    ];

    protected function casts(): array
    {
        return [
            'placement' => AdPlacementType::class,
            'price_etb' => 'decimal:2',
            'duration_days' => 'integer',
            'impression_limit' => 'integer',
            'click_limit' => 'integer',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'eligible_plan_codes' => 'array',
        ];
    }

    public function localizedName(?string $locale = null): string
    {
        $loc = $locale ?? app()->getLocale();
        if ($loc === 'am' && ! empty($this->name_am)) {
            return $this->name_am;
        }
        if ($loc === 'om' && ! empty($this->name_om)) {
            return $this->name_om;
        }
        return $this->name_en;
    }

    public function isPlanEligible(?string $planCode): bool
    {
        if (empty($planCode) || in_array(strtolower($planCode), ['free', 'standard'], true)) {
            return false;
        }

        if (empty($this->eligible_plan_codes)) {
            return true;
        }

        return in_array($planCode, $this->eligible_plan_codes, true);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class, 'placement_id');
    }
}

