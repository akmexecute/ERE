<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use App\Domains\Developer\Enums\ProjectStatus;
use App\Domains\Location\Models\Location;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DevelopmentProject extends Model
{
    use SoftDeletes;

    protected $table = 'development_projects';

    protected $fillable = [
        'uuid',
        'project_code',
        'slug',
        'developer_id',
        'company_id',
        'region_id',
        'city_id',
        'subcity_id',
        'woreda_id',
        'address_line',
        'latitude',
        'longitude',
        'title_en',
        'title_am',
        'title_om',
        'description_en',
        'description_am',
        'description_om',
        'project_type',
        'status',
        'land_area_sqm',
        'building_permit_number',
        'expected_completion_date',
        'overall_progress_pct',
        'facilities_json',
        'site_plan_url',
        'site_plan_document_id',
        'site_plan_description',
    ];

    protected $casts = [
        'status' => ProjectStatus::class,
        'land_area_sqm' => 'decimal:2',
        'overall_progress_pct' => 'decimal:2',
        'expected_completion_date' => 'date',
        'facilities_json' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->project_code)) {
                $model->project_code = 'PRJ-ETH-' . date('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            }
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->title_en) . '-' . Str::random(6);
            }
        });
    }

    public function developer(): BelongsTo
    {
        return $this->belongsTo(DeveloperProfile::class, 'developer_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id');
    }

    public function subcity(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'subcity_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'city_id');
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(ProjectBuilding::class, 'project_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProjectUnit::class, 'project_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ConstructionMilestone::class, 'project_id')->orderBy('order_index');
    }

    public function paymentPlans(): HasMany
    {
        return $this->hasMany(DeveloperPaymentPlan::class, 'project_id');
    }

    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(DeveloperPaymentAccount::class, 'project_id');
    }

    public function defaultPaymentPlan(): ?DeveloperPaymentPlan
    {
        return $this->paymentPlans()->where('is_default', true)->first()
            ?? $this->paymentPlans()->first();
    }
}
