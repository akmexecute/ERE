<?php

declare(strict_types=1);

namespace App\Domains\CRM\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CrmLead extends Model
{
    protected $table = 'crm_leads';

    protected $fillable = [
        'uuid',
        'lead_code',
        'company_id',
        'branch_id',
        'agent_id',
        'assigned_agent_id',
        'client_id',
        'buyer_user_id',
        'inquiry_id',
        'property_id',
        'listing_id',
        'client_name',
        'client_phone',
        'client_email',
        'lead_source',
        'stage',
        'pipeline_stage',
        'budget_min_etb',
        'budget_max_etb',
        'preferred_location_note',
        'notes',
        'last_contacted_at',
        'next_follow_up_at',
    ];

    protected $casts = [
        'budget_min_etb' => 'decimal:2',
        'budget_max_etb' => 'decimal:2',
        'last_contacted_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->lead_code)) {
                $model->lead_code = 'LEAD-' . date('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            }
            if (empty($model->agent_id) && ! empty($model->assigned_agent_id)) {
                $model->agent_id = $model->assigned_agent_id;
            } elseif (empty($model->assigned_agent_id) && ! empty($model->agent_id)) {
                $model->assigned_agent_id = $model->agent_id;
            }
            if (empty($model->pipeline_stage) && ! empty($model->stage)) {
                $model->pipeline_stage = $model->stage;
            } elseif (empty($model->stage) && ! empty($model->pipeline_stage)) {
                $model->stage = $model->pipeline_stage;
            }
            if (empty($model->client_id) && ! empty($model->buyer_user_id)) {
                $model->client_id = $model->buyer_user_id;
            } elseif (empty($model->buyer_user_id) && ! empty($model->client_id)) {
                $model->buyer_user_id = $model->client_id;
            }
        });
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(CrmStageHistory::class, 'crm_lead_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmLeadActivity::class, 'crm_lead_id');
    }
}
