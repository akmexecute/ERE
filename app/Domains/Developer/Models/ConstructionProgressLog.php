<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstructionProgressLog extends Model
{
    protected $table = 'construction_progress_logs';

    public $timestamps = false;

    protected $fillable = [
        'milestone_id',
        'project_id',
        'updated_by_user_id',
        'previous_pct',
        'new_pct',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'previous_pct' => 'decimal:2',
        'new_pct' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ConstructionMilestone::class, 'milestone_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
