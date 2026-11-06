<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConstructionMilestone extends Model
{
    protected $table = 'construction_milestones';

    protected $fillable = [
        'project_id',
        'stage_code',
        'name_en',
        'name_am',
        'order_index',
        'progress_weight',
        'completion_percentage',
        'is_verified',
        'verified_by_user_id',
        'verified_at',
        'evidence_notes',
    ];

    protected $casts = [
        'progress_weight' => 'decimal:2',
        'completion_percentage' => 'decimal:2',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'order_index' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProject::class, 'project_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ConstructionProgressLog::class, 'milestone_id')->latest('created_at');
    }
}
