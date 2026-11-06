<?php

declare(strict_types=1);

namespace App\Domains\Administration\Services;

use App\Domains\Administration\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

final class AuditService
{
    private const array REDACTED_KEYS = [
        'password',
        'remember_token',
        'two_factor_secret',
        'otp_hash',
        'code_hash',
        'token',
        'access_token',
        'secret',
        'api_key',
        'card_number',
        'pin',
        'cvv',
    ];

    /**
     * Centralized audit-event logger.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $action,
        Model|string $auditable,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?int $userId = null
    ): AuditLog {
        $auditableType = is_string($auditable) ? $auditable : $auditable->getMorphClass();
        $auditableId = is_string($auditable) ? '0' : (string) $auditable->getKey();

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $this->redactSensitive($oldValues),
            'new_values' => $this->redactSensitive($newValues),
            'reason' => $reason,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent() ? mb_substr(Request::userAgent(), 0, 512) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Recursively redact sensitive values from arrays and nested JSON structures.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>|null
     */
    public function redactSensitive(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $sanitized = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);
            if (in_array($lowerKey, self::REDACTED_KEYS, true)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->redactSensitive($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
