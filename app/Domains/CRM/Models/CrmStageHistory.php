<?php

declare(strict_types=1);

namespace App\Domains\CRM\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmStageHistory extends Model
{
    protected $table = 'crm_stage_history';

    protected $fillable = [
        'crm_lead_id',
        'actor_user_id',
        'from_stage',
        'to_stage',
        'duration_in_stage_seconds',
        'notes',
        'transitioned_at',
    ];

    protected $casts = [
        'duration_in_stage_seconds' => 'integer',
        'transitioned_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
