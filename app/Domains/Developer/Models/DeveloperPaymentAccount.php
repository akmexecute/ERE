<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use App\Domains\Company\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DeveloperPaymentAccount extends Model
{
    protected $table = 'developer_payment_accounts';

    protected $fillable = [
        'uuid',
        'company_id',
        'developer_id',
        'project_id',
        'account_label',
        'institution_name',
        'account_holder_name',
        'account_number',
        'branch_name',
        'swift_code',
        'currency',
        'transfer_instructions',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function developer(): BelongsTo
    {
        return $this->belongsTo(DeveloperProfile::class, 'developer_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProject::class, 'project_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ContractPaymentTransaction::class, 'payment_account_id');
    }
}

