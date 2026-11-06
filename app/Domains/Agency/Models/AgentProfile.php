<?php

declare(strict_types=1);

namespace App\Domains\Agency\Models;

use App\Domains\Company\Models\CompanyBranch;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AgentProfile extends Model
{
    protected $table = 'agent_profiles';

    protected $fillable = [
        'uuid',
        'agent_code',
        'user_id',
        'company_id',
        'branch_id',
        'license_number',
        'specialization',
        'years_experience',
        'languages_json',
        'bio_en',
        'bio_am',
        'bio_om',
        'response_rate_pct',
        'avg_response_minutes',
        'average_rating',
        'reviews_count',
        'active_listings_count',
        'closed_deals_count',
        'is_verified_agent',
        'status',
    ];

    protected $casts = [
        'languages_json' => 'array',
        'is_verified_agent' => 'boolean',
        'average_rating' => 'decimal:2',
        'years_experience' => 'integer',
        'response_rate_pct' => 'integer',
        'avg_response_minutes' => 'integer',
        'reviews_count' => 'integer',
        'active_listings_count' => 'integer',
        'closed_deals_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->agent_code)) {
                $model->agent_code = 'AGT-ETH-' . strtoupper(Str::random(6));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Company\Models\Company::class, 'company_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(CompanyBranch::class, 'branch_id');
    }

    public function serviceLocations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domains\Location\Models\Location::class,
            'agent_service_locations',
            'agent_profile_id',
            'location_id'
        );
    }

    public function listings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domains\Listing\Models\Listing::class, 'published_by_user_id', 'user_id');
    }

    public function crmLeads(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domains\CRM\Models\CrmLead::class, 'assigned_agent_id', 'user_id');
    }
}
