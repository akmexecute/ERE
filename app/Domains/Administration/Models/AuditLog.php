<?php

declare(strict_types=1);

namespace App\Domains\Administration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class AuditLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
        'user_agent',
        'device_fingerprint',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function delete(): ?bool
    {
        throw new LogicException('Audit logs are append-only and cannot be deleted.');
    }
}
