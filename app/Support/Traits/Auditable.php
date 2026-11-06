<?php

declare(strict_types=1);

namespace App\Support\Traits;

use App\Domains\Administration\Models\AuditLog;
use App\Domains\Administration\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Auditable
{
    protected ?string $auditReason = null;

    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $model->writeAuditLog('created', null, $model->getAttributes());
        });

        static::updated(function (Model $model): void {
            $dirty = $model->getDirty();
            unset($dirty['updated_at'], $dirty['remember_token']);

            if ($dirty === []) {
                return;
            }

            $old = [];
            foreach (array_keys($dirty) as $key) {
                $old[$key] = $model->getOriginal($key);
            }

            $model->writeAuditLog('updated', $old, $dirty);
        });

        static::deleted(function (Model $model): void {
            $model->writeAuditLog('deleted', $model->getOriginal(), null);
        });
    }

    public function withAuditReason(string $reason): static
    {
        $this->auditReason = $reason;

        return $this;
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function writeAuditLog(string $event, ?array $oldValues, ?array $newValues, ?string $customReason = null): void
    {
        app(AuditService::class)->log(
            action: class_basename($this).'.'.$event,
            auditable: $this,
            oldValues: $oldValues,
            newValues: $newValues,
            reason: $customReason ?? $this->auditReason
        );
    }
}
