<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubscriptionPlanFeatureValue extends Model
{
    use HasFactory;

    protected $table = 'subscription_plan_feature_values';

    protected $fillable = [
        'plan_id',
        'feature_id',
        'value',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(SubscriptionFeature::class, 'feature_id');
    }
}

