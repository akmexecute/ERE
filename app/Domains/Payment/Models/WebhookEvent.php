<?php

declare(strict_types=1);

namespace App\Domains\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class WebhookEvent extends Model
{
    use HasUuids;

    protected $table = 'webhook_events';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'provider',
        'provider_event_id',
        'event_type',
        'transaction_reference',
        'signature',
        'payload_json',
        'status',
        'failure_reason',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'processed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (is_array($model->payload_json)) {
                $model->payload_json = self::sanitizePayload($model->payload_json);
            }
        });
    }

    /**
     * PCI-DSS & privacy sanitizer: redacts CVVs, card numbers, passwords, PINs, and secret keys.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function sanitizePayload(array $payload): array
    {
        $sensitiveKeys = ['cvv', 'cvc', 'card_number', 'pan', 'password', 'pin', 'secret', 'auth_token', 'private_key'];

        $sanitized = [];
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizePayload($value);
            } elseif (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_string($value) && preg_match('/^\d{13,19}$/', str_replace([' ', '-'], '', $value))) {
                // Mask potential PAN numbers
                $clean = str_replace([' ', '-'], '', $value);
                $sanitized[$key] = str_repeat('*', strlen($clean) - 4) . substr($clean, -4);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}

