<?php

declare(strict_types=1);

namespace App\Domains\CRM\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmLeadActivity extends Model
{
    protected $table = 'crm_lead_activities';

    public $timestamps = false;

    protected $fillable = [
        'crm_lead_id',
        'actor_user_id',
        'activity_type',
        'from_stage',
        'to_stage',
        'summary',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
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
