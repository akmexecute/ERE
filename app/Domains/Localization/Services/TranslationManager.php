<?php

declare(strict_types=1);

namespace App\Domains\Localization\Services;

use App\Domains\Administration\Services\AuditService;
use App\Domains\Localization\Models\SystemTranslation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class TranslationManager
{
    private const array CONFIGURED_LOCALES = [
        'en' => [
            'code' => 'en',
            'name' => 'English',
            'native' => 'English',
            'flag' => '🇬🇧',
            'is_default' => true,
            'is_enabled' => true,
            'direction' => 'ltr',
        ],
        'am' => [
            'code' => 'am',
            'name' => 'Amharic',
            'native' => 'አማርኛ',
            'flag' => '🇪🇹',
            'is_default' => false,
            'is_enabled' => true,
            'direction' => 'ltr',
        ],
        'om' => [
            'code' => 'om',
            'name' => 'Afaan Oromo',
            'native' => 'Afaan Oromoo',
            'flag' => '🌳',
            'is_default' => false,
            'is_enabled' => true,
            'direction' => 'ltr',
        ],
    ];

    private array $supportedLocales = ['en', 'am', 'om'];

    public function __construct(
        private ?AuditService $auditService = null
    ) {
        $this->auditService = $auditService ?? app(AuditService::class);
    }

    public function getLocales(): array
    {
        return self::CONFIGURED_LOCALES;
    }

    private function getLangPath(string $subpath = ''): string
    {
        return base_path('lang' . ($subpath ? '/' . ltrim($subpath, '/') : ''));
    }

    /**
     * Get all managed translations across all supported locales, grouped by key.
     * Merges file-based defaults with database overrides.
     *
     * @return array<string, array{
     *     full_key: string,
     *     group: string,
     *     sub_key: string,
     *     page: string,
     *     en: string,
     *     am: string,
     *     om: string,
     *     has_missing: bool
     * }>
     */
    public function getAllTranslations(): array
    {
        $translations = [];

        // Dynamically discover all translation file groups
        $enPath = $this->getLangPath('en');
        $discovered = File::isDirectory($enPath)
            ? array_map(fn($f) => $f->getBasename('.php'), File::files($enPath))
            : [];
        $groups = array_values(array_unique(array_merge(
            ['app', 'auth', 'marketplace', 'stays', 'crm', 'projects', 'listings'],
            $discovered
        )));

        // Load database translations if table exists
        $dbTranslations = [];
        if (Schema::hasTable('system_translations')) {
            try {
                $rows = SystemTranslation::query()->get();
                foreach ($rows as $row) {
                    $dotKey = "{$row->group}.{$row->key}";
                    $dbTranslations[$dotKey][$row->locale] = (string) $row->value;
                }
            } catch (Throwable) {
                // In early migrations or testing fallback cleanly
            }
        }

        foreach ($groups as $group) {
            $data = [];
            foreach ($this->supportedLocales as $locale) {
                $path = $this->getLangPath("{$locale}/{$group}.php");
                $data[$locale] = File::exists($path) ? (array) (require $path) : [];
            }

            $flattened = $this->flattenGroup($data['en'], $group);

            foreach ($flattened as $dotKey => $enVal) {
                $parts = explode('.', $dotKey);
                $subKey = implode('.', array_slice($parts, 1));

                $amVal = $dbTranslations[$dotKey]['am']
                    ?? $this->getNestedValue($data['am'], $subKey)
                    ?? '';

                $omVal = $dbTranslations[$dotKey]['om']
                    ?? $this->getNestedValue($data['om'], $subKey)
                    ?? '';

                $resolvedEn = $dbTranslations[$dotKey]['en']
                    ?? (string) $enVal;

                $pageCategory = $this->categorizeKey($dotKey);

                $hasMissing = empty(trim((string) $amVal)) || empty(trim((string) $omVal));

                $translations[$dotKey] = [
                    'full_key' => $dotKey,
                    'group' => $group,
                    'sub_key' => $subKey,
                    'page' => $pageCategory,
                    'en' => (string) $resolvedEn,
                    'am' => (string) $amVal,
                    'om' => (string) $omVal,
                    'has_missing' => $hasMissing,
                ];
            }
        }

        // Also append any database-only keys that were not present in file definitions
        foreach ($dbTranslations as $dotKey => $locales) {
            if (! isset($translations[$dotKey])) {
                $parts = explode('.', $dotKey, 2);
                $group = $parts[0];
                $subKey = $parts[1] ?? '';

                $en = $locales['en'] ?? '';
                $am = $locales['am'] ?? '';
                $om = $locales['om'] ?? '';

                $translations[$dotKey] = [
                    'full_key' => $dotKey,
                    'group' => $group,
                    'sub_key' => $subKey,
                    'page' => $this->categorizeKey($dotKey),
                    'en' => $en,
                    'am' => $am,
                    'om' => $om,
                    'has_missing' => empty(trim($am)) || empty(trim($om)),
                ];
            }
        }

        return $translations;
    }

    /**
     * Compute statistics and coverage reports for all supported languages.
     *
     * @return array<string, array{
     *     code: string,
     *     name: string,
     *     native: string,
     *     flag: string,
     *     total: int,
     *     translated: int,
     *     missing: int,
     *     coverage: int
     * }>
     */
    public function getLanguageStats(): array
    {
        $all = $this->getAllTranslations();
        $total = count($all);

        $stats = [];
        foreach (self::CONFIGURED_LOCALES as $code => $meta) {
            $translated = 0;
            foreach ($all as $item) {
                $val = trim((string) ($item[$code] ?? ''));
                if ($val !== '') {
                    $translated++;
                }
            }

            $missing = max(0, $total - $translated);
            $coverage = $total > 0 ? (int) round(($translated / $total) * 100) : 100;

            $stats[$code] = [
                ...$meta,
                'total' => $total,
                'translated' => $translated,
                'missing' => $missing,
                'coverage' => $coverage,
            ];
        }

        return $stats;
    }

    /**
     * Save updated translations into database catalogue, invalidate cache, and synchronize to disk.
     */
    public function saveTranslations(array $submittedTranslations, ?int $adminId = null): void
    {
        $oldTranslations = $this->getAllTranslations();
        $affectedLocales = [];
        $affectedGroups = [];

        foreach ($submittedTranslations as $fullKey => $vals) {
            $parts = explode('.', $fullKey, 2);
            $group = $parts[0] ?? 'app';
            $subKey = $parts[1] ?? $parts[0];
            $affectedGroups[$group] = true;

            foreach ($this->supportedLocales as $locale) {
                if (! isset($vals[$locale])) {
                    continue;
                }

                $value = trim((string) $vals[$locale]);
                $affectedLocales[$locale] = true;

                if (Schema::hasTable('system_translations')) {
                    SystemTranslation::query()->updateOrCreate(
                        [
                            'group' => $group,
                            'key' => $subKey,
                            'locale' => $locale,
                        ],
                        [
                            'value' => $value,
                            'is_reviewed' => true,
                            'updated_by' => $adminId,
                        ]
                    );
                }
            }
        }

        // Invalidate runtime database caches
        foreach (array_keys($affectedLocales) as $locale) {
            foreach (array_keys($affectedGroups) as $group) {
                Cache::forget("system_translations:{$locale}:{$group}");
            }
        }

        // Synchronize to files on disk when directory is writable
        try {
            $this->syncToPhpLangFiles($submittedTranslations);
        } catch (Throwable) {
            // In read-only container environments, database persistence is sufficient
        }

        // Audit the translation change
        $this->auditService->log(
            action: 'settings.translations.updated',
            auditable: 'Translation',
            oldValues: ['count' => count($oldTranslations)],
            newValues: ['updated_keys' => array_keys($submittedTranslations)],
            reason: 'Admin updated i18n website translations',
            userId: $adminId
        );
    }

    /**
     * Add a brand-new translation phrase into database catalog and dictionary files.
     */
    public function addNewPhrase(string $fullKey, string $en, string $am, string $om, ?int $adminId = null): void
    {
        $parts = explode('.', $fullKey, 2);
        $group = $parts[0] ?: 'app';
        $subKey = $parts[1] ?? $parts[0];

        $payload = [
            'en' => trim($en),
            'am' => trim($am),
            'om' => trim($om),
        ];

        foreach ($payload as $locale => $val) {
            if (Schema::hasTable('system_translations')) {
                SystemTranslation::query()->updateOrCreate(
                    [
                        'group' => $group,
                        'key' => $subKey,
                        'locale' => $locale,
                    ],
                    [
                        'value' => $val,
                        'is_reviewed' => true,
                        'updated_by' => $adminId,
                    ]
                );
            }
            Cache::forget("system_translations:{$locale}:{$group}");
        }

        try {
            foreach ($payload as $locale => $val) {
                $path = $this->getLangPath("{$locale}/{$group}.php");
                $currentData = File::exists($path) ? (array) (require $path) : [];
                $this->setNestedValue($currentData, $subKey, $val);
                $this->writePhpLangFile($path, $currentData);
            }
        } catch (Throwable) {
            // Safe fallback in read-only setups
        }

        $this->auditService->log(
            action: 'settings.translations.phrase_added',
            auditable: 'Translation',
            oldValues: null,
            newValues: ['key' => $fullKey, 'en' => $en, 'am' => $am, 'om' => $om],
            reason: 'Admin created a new i18n translation key',
            userId: $adminId
        );
    }

    private function categorizeKey(string $dotKey): string
    {
        return match (true) {
            str_starts_with($dotKey, 'auth.') => '🔐 Login & Authentication',
            str_starts_with($dotKey, 'app.nav.') || str_starts_with($dotKey, 'app.footer.') => '🧭 Navigation, Header & Footer',
            str_starts_with($dotKey, 'app.hero.') || str_starts_with($dotKey, 'app.calendar_widget.') || str_starts_with($dotKey, 'app.gis_section.') || str_starts_with($dotKey, 'app.features.') => '🌟 Home Hero & Landing',
            str_starts_with($dotKey, 'app.dashboard.') => '👤 User Profile & Dashboard',
            str_starts_with($dotKey, 'marketplace.details.') || str_starts_with($dotKey, 'marketplace.negotiation.') => '🏠 Property Details & Legal Vault',
            str_starts_with($dotKey, 'marketplace.') => '🔍 Marketplace Search & Map',
            str_starts_with($dotKey, 'stays.') => '🏨 Short-Stays & Host Ops',
            str_starts_with($dotKey, 'crm.') => '💼 Agency CRM & Pipeline',
            str_starts_with($dotKey, 'projects.') => '🏗️ Developer Projects & Units',
            str_starts_with($dotKey, 'listings.') => '📝 Post Property & Listings',
            str_starts_with($dotKey, 'app.status.') => '🏷️ Property Statuses',
            str_starts_with($dotKey, 'app.roles.') => '👥 Roles & Personas',
            default => '🌐 General Platform Words',
        };
    }

    private function syncToPhpLangFiles(array $submittedTranslations): void
    {
        $enPath = $this->getLangPath('en');
        $discovered = File::isDirectory($enPath)
            ? array_map(fn($f) => $f->getBasename('.php'), File::files($enPath))
            : [];
        $groups = array_values(array_unique(array_merge(
            ['app', 'auth', 'marketplace', 'stays', 'crm', 'projects', 'listings'],
            $discovered
        )));

        foreach ($groups as $group) {
            foreach ($this->supportedLocales as $locale) {
                $path = $this->getLangPath("{$locale}/{$group}.php");
                $currentData = File::exists($path) ? (array) (require $path) : [];

                foreach ($submittedTranslations as $fullKey => $vals) {
                    if (! str_starts_with($fullKey, "{$group}.")) {
                        continue;
                    }

                    $subKey = substr($fullKey, strlen("{$group}."));
                    $val = $vals[$locale] ?? null;

                    if ($val !== null) {
                        $this->setNestedValue($currentData, $subKey, trim((string) $val));
                    }
                }

                $this->writePhpLangFile($path, $currentData);
            }
        }
    }

    private function flattenGroup(array $array, string $prefix): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = $prefix . '.' . $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenGroup($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    private function getNestedValue(array $array, string $dotKey): ?string
    {
        $keys = explode('.', $dotKey);
        $current = $array;

        foreach ($keys as $k) {
            if (! is_array($current) || ! array_key_exists($k, $current)) {
                return null;
            }
            $current = $current[$k];
        }

        return is_string($current) ? $current : null;
    }

    private function setNestedValue(array &$array, string $dotKey, string $value): void
    {
        $keys = explode('.', $dotKey);
        $current = &$array;

        foreach ($keys as $k) {
            if (! isset($current[$k]) || ! is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current = $value;
    }

    private function writePhpLangFile(string $path, array $data): void
    {
        $dir = dirname($path);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $exported = var_export($data, true);
        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn {$exported};\n";

        File::put($path, $content);
    }
}
