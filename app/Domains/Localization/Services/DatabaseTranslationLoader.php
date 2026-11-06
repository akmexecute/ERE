<?php

declare(strict_types=1);

namespace App\Domains\Localization\Services;

use App\Domains\Localization\Models\SystemTranslation;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class DatabaseTranslationLoader implements Loader
{
    public function __construct(
        private readonly Loader $fileLoader
    ) {}

    public function load($locale, $group, $namespace = null): array
    {
        $fileLines = $this->fileLoader->load($locale, $group, $namespace);

        // Namespaces (like vendor packages) pass directly to file loader
        if ($namespace !== null && $namespace !== '*') {
            return $fileLines;
        }

        try {
            if (! Schema::hasTable('system_translations')) {
                return $fileLines;
            }

            $cacheKey = "system_translations:{$locale}:{$group}";
            /** @var array<string, string> $dbLines */
            $dbLines = Cache::remember($cacheKey, 3600, function () use ($locale, $group): array {
                return SystemTranslation::query()
                    ->where('locale', $locale)
                    ->where('group', $group)
                    ->pluck('value', 'key')
                    ->all();
            });

            if (empty($dbLines)) {
                return $fileLines;
            }

            $nestedDbLines = [];
            foreach ($dbLines as $dotKey => $val) {
                Arr::set($nestedDbLines, (string) $dotKey, (string) $val);
            }

            return array_replace_recursive($fileLines, $nestedDbLines);
        } catch (Throwable) {
            return $fileLines;
        }
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->fileLoader->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->fileLoader->addJsonPath($path);
    }

    public function namespaces(): array
    {
        return $this->fileLoader->namespaces();
    }
}
