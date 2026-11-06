<?php

declare(strict_types=1);

namespace App\Domains\Promotion\Models;

use App\Domains\Listing\Models\Listing;
use App\Domains\Payment\Models\Payment;
use App\Domains\Subscription\Models\UserSubscription;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ListingPromotion extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'listing_promotions';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'listing_id',
        'package_id',
        'user_id',
        'tier',
        'ranking_weight',
        'price_etb',
        'starts_at',
        'expires_at',
        'status',
        'subscription_id',
        'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'ranking_weight' => 'integer',
            'price_etb' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(PromotionTierPackage::class, 'package_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'subscription_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}

