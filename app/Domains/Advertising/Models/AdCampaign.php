<?php

declare(strict_types=1);

namespace App\Domains\Advertising\Models;

use App\Domains\Advertising\Enums\CampaignStatus;
use App\Domains\Company\Models\Company;
use App\Domains\Payment\Models\Payment;
use App\Domains\Subscription\Models\UserSubscription;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AdCampaign extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'ad_campaigns';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'user_id',
        'company_id',
        'placement_id',
        'placement',
        'title',
        'target_url',
        'banner_image_path',
        'price_etb',
        'budget_etb',
        'starts_at',
        'expires_at',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'impressions_count',
        'clicks_count',
        'subscription_id',
        'payment_id',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'price_etb' => 'decimal:2',
            'budget_etb' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'impressions_count' => 'integer',
            'clicks_count' => 'integer',
            'metadata_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(AdPlacement::class, 'placement_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'subscription_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function isServing(): bool
    {
        if ($this->status !== CampaignStatus::Active) {
            return false;
        }

        $now = now();
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function recordImpression(): void
    {
        $this->increment('impressions_count');
    }

    public function recordClick(): void
    {
        $this->increment('clicks_count');
    }
}

