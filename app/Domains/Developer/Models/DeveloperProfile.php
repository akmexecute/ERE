<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use App\Domains\Company\Models\Company;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DeveloperProfile extends Model
{
    protected $table = 'developer_profiles';

    protected $fillable = [
        'uuid',
        'developer_code',
        'company_id',
        'user_id',
        'license_number',
        'establishment_year',
        'delivered_projects_count',
        'delivered_units_count',
        'verification_status',
        'verified_at',
        'verified_by_user_id',
        'track_record_json',
        'bio_en',
        'bio_am',
        'bio_om',
    ];

    protected $casts = [
        'track_record_json' => 'array',
        'verified_at' => 'datetime',
        'establishment_year' => 'integer',
        'delivered_projects_count' => 'integer',
        'delivered_units_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->developer_code)) {
                $model->developer_code = 'DEV-ETH-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(DeveloperPaymentAccount::class, 'developer_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(DevelopmentProject::class, 'developer_id');
    }
}
