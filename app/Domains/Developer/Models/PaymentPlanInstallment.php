<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentPlanInstallment extends Model
{
    protected $table = 'payment_plan_installments';

    protected $fillable = [
        'payment_plan_id',
        'sequence_number',
        'milestone_label_en',
        'milestone_label_am',
        'percentage',
        'trigger_milestone_code',
        'due_days_after_trigger',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'sequence_number' => 'integer',
        'due_days_after_trigger' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(DeveloperPaymentPlan::class, 'payment_plan_id');
    }
}
