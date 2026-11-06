<?php

declare(strict_types=1);

namespace App\Domains\Promotion\Models;

use App\Domains\Promotion\Enums\PromotionTier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class PromotionTierPackage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'promotion_tier_packages';

    protected $fillable = [
        'tier',
        'name_en',
        'name_am',
        'name_om',
        'description_en',
        'description_am',
        'description_om',
        'price_etb',
        'duration_days',
        'ranking_weight',
        'badge_label',
        'is_active',
        'is_public',
        'eligible_plan_codes',
    ];

    protected function casts(): array
    {
        return [
            'tier' => PromotionTier::class,
            'price_etb' => 'decimal:2',
            'duration_days' => 'integer',
            'ranking_weight' => 'integer',
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

    public function localizedDescription(?string $locale = null): ?string
    {
        $loc = $locale ?? app()->getLocale();
        if ($loc === 'am' && ! empty($this->description_am)) {
            return $this->description_am;
        }
        if ($loc === 'om' && ! empty($this->description_om)) {
            return $this->description_om;
        }
        return $this->description_en;
    }

    /**
     * Check if a subscription plan code is eligible to purchase this promotion package.
     * Note: Standard/Free plan is always excluded by design.
     */
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

    public function promotions(): HasMany
    {
        return $this->hasMany(ListingPromotion::class, 'package_id');
    }
}

