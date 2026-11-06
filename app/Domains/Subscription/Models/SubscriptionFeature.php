<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Models;

use App\Domains\Subscription\Enums\FeatureValueType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SubscriptionFeature extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'subscription_features';

    protected $fillable = [
        'key',
        'name_en',
        'name_am',
        'name_om',
        'description_en',
        'description_am',
        'description_om',
        'category',
        'value_type',
        'default_value',
        'options_json',
        'is_active',
        'is_public',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'value_type' => FeatureValueType::class,
            'options_json' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
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

    public function planValues(): HasMany
    {
        return $this->hasMany(SubscriptionPlanFeatureValue::class, 'feature_id');
    }
}

