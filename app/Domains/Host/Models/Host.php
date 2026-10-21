<?php

declare(strict_types=1);

namespace App\Domains\Host\Models;

use App\Domains\Stay\Models\StayListingProfile;
use App\Domains\User\Models\Company;
use App\Domains\User\Models\User;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Host extends Model
{
    use Auditable;

    protected $fillable = [
        'uuid',
        'user_id',
        'company_id',
        'host_type',
        'display_name',
        'bio_en',
        'bio_am',
        'bio_om',
        'response_rate_pct',
        'avg_response_minutes',
        'average_rating',
        'reviews_count',
        'payout_schedule',
        'is_superhost',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'response_rate_pct' => 'integer',
            'avg_response_minutes' => 'integer',
            'average_rating' => 'decimal:2',
            'reviews_count' => 'integer',
            'is_superhost' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function stayProfiles(): HasMany
    {
        return $this->hasMany(StayListingProfile::class);
    }
}
