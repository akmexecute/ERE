<?php

declare(strict_types=1);

namespace App\Domains\Company\Models;

use App\Domains\Portfolio\Models\PropertyManagementMandate;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasUuids;

    protected $table = 'companies';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'owner_user_id',
        'legal_name',
        'trade_name',
        'company_type',
        'tin_number',
        'trade_registration_number',
        'license_number',
        'verification_level',
        'status',
    ];

    protected $casts = [
        'verification_level' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(CompanyBranch::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function mandates(): HasMany
    {
        return $this->hasMany(PropertyManagementMandate::class);
    }

    public function verifications(): MorphMany
    {
        return $this->morphMany(\App\Domains\Verification\Models\Verification::class, 'verifiable');
    }
}
