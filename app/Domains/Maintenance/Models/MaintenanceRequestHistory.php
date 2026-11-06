<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MaintenanceRequestHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'maintenance_request_id',
        'actor_user_id',
        'from_status',
        'to_status',
        'notes',
        'cost_snapshot_etb',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'cost_snapshot_etb' => 'decimal:2',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \DomainException('Maintenance request history entries are strictly immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new \DomainException('Maintenance request history entries are strictly immutable and cannot be deleted.');
        });
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
