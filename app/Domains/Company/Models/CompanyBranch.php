<?php

declare(strict_types=1);

namespace App\Domains\Company\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CompanyBranch extends Model
{
    protected $table = 'company_branches';

    protected $fillable = [
        'uuid',
        'company_id',
        'branch_code',
        'name_en',
        'name_am',
        'name_om',
        'is_head_office',
        'manager_user_id',
        'region_id',
        'city_id',
        'subcity_id',
        'phone',
        'email',
        'address_line',
        'is_active',
    ];

    protected $casts = [
        'is_head_office' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (self $model): void {
            if ($model->is_head_office && $model->is_active && $model->company_id) {
                // Database model-level guarantee: exactly one active head office per company
                static::query()
                    ->where('company_id', $model->company_id)
                    ->when($model->id, fn ($q) => $q->where('id', '!=', $model->id))
                    ->where('is_head_office', true)
                    ->update(['is_head_office' => false]);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Location\Models\Location::class, 'region_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Location\Models\Location::class, 'city_id');
    }

    public function subcity(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Location\Models\Location::class, 'subcity_id');
    }
}
