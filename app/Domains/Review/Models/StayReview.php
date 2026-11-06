<?php

declare(strict_types=1);

namespace App\Domains\Review\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Host\Models\Host;
use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class StayReview extends Model
{
    protected $guarded = ['id'];

    /**
     * SRS Section 70: Private feedback note is strictly between guest/host/staff and never exposed publicly.
     */
    protected $hidden = [
        'private_feedback_note',
    ];

    protected $casts = [
        'overall_rating' => 'integer',
        'communication_rating' => 'integer',
        'cleanliness_rating' => 'integer',
        'accuracy_rating' => 'integer',
        'location_rating' => 'integer',
        'value_rating' => 'integer',
        'property_care_rating' => 'integer',
        'rule_compliance_rating' => 'integer',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewee_user_id');
    }

    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ReviewModerationLog::class);
    }
}
