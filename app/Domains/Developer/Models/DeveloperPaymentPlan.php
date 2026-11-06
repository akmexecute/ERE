<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeveloperPaymentPlan extends Model
{
    protected $table = 'developer_payment_plans';

    protected $fillable = [
        'project_id',
        'name_en',
        'name_am',
        'is_default',
        'is_active',
        'version',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'version' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProject::class, 'project_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PaymentPlanInstallment::class, 'payment_plan_id')->orderBy('sequence_number');
    }

    public function totalPercentage(): float
    {
        return (float) $this->installments()->sum('percentage');
    }
}
