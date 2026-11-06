<?php

declare(strict_types=1);

namespace App\Domains\Administration\Services;

use App\Domains\Administration\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\UnauthorizedException;
use InvalidArgumentException;

final class SettingService
{
    private const string CACHE_PREFIX = 'system_setting:';

    /**
     * Retrieve a system setting by key.
     *
     * @param bool $allowPrivate When false, requesting a private setting without permission will return default or throw.
     */
    public function get(string $key, mixed $default = null, bool $allowPrivate = false): mixed
    {
        $setting = Cache::remember(self::CACHE_PREFIX . $key, 3600, function () use ($key) {
            return SystemSetting::where('key', $key)->first();
        });

        if (! $setting) {
            return $default;
        }

        // Enforce boundary protection: anonymous visitors cannot read private/privileged settings
        if (! $setting->is_public && ! $allowPrivate) {
            return $default;
        }

        return $setting->getTypedValue();
    }

    /**
     * Set or update a system setting with type validation and cache invalidation.
     */
    public function set(string $key, mixed $value, ?int $userId = null): SystemSetting
    {
        $setting = SystemSetting::where('key', $key)->first();

        if (! $setting) {
            throw new InvalidArgumentException("Setting with key '{$key}' does not exist. Please define setting schema first.");
        }

        // Validate type constraints
        $setting->validateValueForType($value, $setting->type);

        $setting->setTypedValue($value);
        $setting->updated_by = $userId;
        $setting->save();

        // Invalidate cache immediately
        Cache::forget(self::CACHE_PREFIX . $key);

        return $setting;
    }

    /**
     * Create or register a new system setting definition.
     */
    public function register(
        string $group,
        string $key,
        mixed $defaultValue,
        string $type = 'string',
        bool $isPublic = false,
        ?string $description = null,
        ?int $userId = null
    ): SystemSetting {
        $setting = SystemSetting::firstOrNew(['key' => $key]);

        $setting->group = $group;
        $setting->type = $type;
        $setting->is_public = $isPublic;
        $setting->description = $description;
        $setting->updated_by = $userId;
        $setting->setTypedValue($defaultValue);
        $setting->save();

        Cache::forget(self::CACHE_PREFIX . $key);

        return $setting;
    }

    /**
     * Retrieve all settings belonging to a group.
     *
     * @return array<string, mixed>
     */
    public function getByGroup(string $group, bool $publicOnly = true): array
    {
        $query = SystemSetting::where('group', $group);

        if ($publicOnly) {
            $query->where('is_public', true);
        }

        $results = [];
        foreach ($query->get() as $setting) {
            $results[$setting->key] = $setting->getTypedValue();
        }

        return $results;
    }

    public function has(string $key): bool
    {
        return SystemSetting::where('key', $key)->exists();
    }

    /**
     * Retrieve all settings across all groups.
     *
     * @return array<string, mixed>
     */
    public function all(bool $allowPrivate = false): array
    {
        $query = SystemSetting::query();

        if (! $allowPrivate) {
            $query->where('is_public', true);
        }

        $results = [];
        foreach ($query->get() as $setting) {
            $results[$setting->key] = $setting->getTypedValue();
        }

        return $results;
    }
}
