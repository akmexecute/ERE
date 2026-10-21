<?php

declare(strict_types=1);

namespace App\Support\Traits;

use App\Domains\Administration\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    protected ?string $auditReason = null;

    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $model->writeAuditLog('created', null, $model->sanitizeAuditAttributes($model->getAttributes()));
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

            $model->writeAuditLog(
                'updated',
                $model->sanitizeAuditAttributes($old),
                $model->sanitizeAuditAttributes($dirty)
            );
        });

        static::deleted(function (Model $model): void {
            $model->writeAuditLog('deleted', $model->sanitizeAuditAttributes($model->getOriginal()), null);
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
        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'action' => class_basename($this).'.'.$event,
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => (string) $this->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $customReason ?? $this->auditReason,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent() ? mb_substr(Request::userAgent(), 0, 512) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function sanitizeAuditAttributes(array $attributes): array
    {
        $redactedKeys = ['password', 'remember_token', 'two_factor_secret', 'otp_hash'];
        foreach ($redactedKeys as $key) {
            if (array_key_exists($key, $attributes)) {
                $attributes[$key] = '[REDACTED]';
            }
        }

        return $attributes;
    }
}
