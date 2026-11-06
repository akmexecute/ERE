<?php

declare(strict_types=1);

namespace App\Domains\Administration\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = [
        'group',
        'key',
        'value_json',
        'type',
        'is_public',
        'description',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'is_public' => 'boolean',
        ];
    }

    /**
     * Get the typed, validated value of the setting.
     */
    public function getTypedValue(): mixed
    {
        $raw = $this->value_json['value'] ?? null;

        return match ($this->type) {
            'bool', 'boolean' => (bool) $raw,
            'int', 'integer' => (int) $raw,
            'float', 'double' => (float) $raw,
            'json', 'array' => is_array($raw) ? $raw : json_decode((string) $raw, true),
            default => (string) $raw,
        };
    }

    /**
     * Set and validate the typed value.
     */
    public function setTypedValue(mixed $value): void
    {
        $this->validateValueForType($value, $this->type);

        $this->value_json = ['value' => $value];
    }

    /**
     * Enforce strict type validation so settings labelled boolean/int don't accept arbitrary types.
     */
    public function validateValueForType(mixed $value, string $type): void
    {
        switch ($type) {
            case 'bool':
            case 'boolean':
                if (! is_bool($value)) {
                    throw new InvalidArgumentException("Setting '{$this->key}' requires a boolean value.");
                }
                break;

            case 'int':
            case 'integer':
                if (! is_int($value)) {
                    throw new InvalidArgumentException("Setting '{$this->key}' requires an integer value.");
                }
                break;

            case 'float':
            case 'double':
                if (! is_numeric($value)) {
                    throw new InvalidArgumentException("Setting '{$this->key}' requires a numeric float value.");
                }
                break;

            case 'json':
            case 'array':
                if (! is_array($value)) {
                    throw new InvalidArgumentException("Setting '{$this->key}' requires an array or JSON structure.");
                }
                break;
        }
    }
}
