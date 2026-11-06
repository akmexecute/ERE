<?php

declare(strict_types=1);

namespace App\Domains\Localization\Services;

use App\Domains\Administration\Services\AuditService;
use App\Domains\Localization\Models\SystemTranslation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use SimpleXMLElement;
use Throwable;
use ZipArchive;

final class ExcelTranslationService
{
    private const array SUPPORTED_LOCALES = [
        'en' => 'English',
        'am' => 'Amharic',
        'om' => 'Afaan Oromo',
    ];

    public function __construct(
        private readonly TranslationManager $translationManager,
        private readonly AuditService $auditService
    ) {}

    /**
     * Export translation catalogue as an Excel (.xlsx) file.
     *
     * @return array{path: string, filename: string}
     */
    public function exportTranslations(
        ?string $categoryFilter = null,
        ?string $searchQuery = null,
        bool $missingOnly = false
    ): array {
        $all = $this->translationManager->getAllTranslations();

        // Apply optional filtering
        $filtered = array_filter($all, function (array $item) use ($categoryFilter, $searchQuery, $missingOnly): bool {
            if ($categoryFilter && $categoryFilter !== 'all' && ($item['page'] ?? '') !== $categoryFilter) {
                return false;
            }
            if ($missingOnly && empty($item['has_missing'])) {
                return false;
            }
            if ($searchQuery) {
                $q = mb_strtolower($searchQuery);
                $match = str_contains(mb_strtolower($item['full_key']), $q)
                    || str_contains(mb_strtolower($item['en'] ?? ''), $q)
                    || str_contains(mb_strtolower($item['am'] ?? ''), $q)
                    || str_contains(mb_strtolower($item['om'] ?? ''), $q);
                if (! $match) {
                    return false;
                }
            }
            return true;
        });

        $tempPath = tempnam(sys_get_temp_dir(), 'ere_trans_') . '.xlsx';
        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new InvalidArgumentException('Could not create temporary Excel archive.');
        }

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Translations" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><name val="Calibri"/><sz val="11"/></font>
    <font><b/><name val="Calibri"/><sz val="11"/></font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
  </fills>
  <borders count="1"><border/></borders>
  <cellStyleXfs count="1"><xf/></cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0"/>
  </cellXfs>
</styleSheet>';

        // Build sheet1 XML
        $rowsXml = '';
        $rowNum = 1;

        // Header Row: Group | Key | English (en) | Amharic (am) | Afaan Oromo (om)
        $rowsXml .= "<row r=\"{$rowNum}\" s=\"1\">";
        $rowsXml .= '<c r="A1" t="inlineStr"><is><t>Group</t></is></c>';
        $rowsXml .= '<c r="B1" t="inlineStr"><is><t>Key</t></is></c>';
        $rowsXml .= '<c r="C1" t="inlineStr"><is><t>English (en)</t></is></c>';
        $rowsXml .= '<c r="D1" t="inlineStr"><is><t>Amharic (am)</t></is></c>';
        $rowsXml .= '<c r="E1" t="inlineStr"><is><t>Afaan Oromo (om)</t></is></c>';
        $rowsXml .= '</row>';

        foreach ($filtered as $item) {
            $rowNum++;
            $group = $this->sanitizeForSpreadsheet((string) ($item['group'] ?? 'app'));
            $key = $this->sanitizeForSpreadsheet((string) ($item['full_key'] ?? ''));
            $en = $this->sanitizeForSpreadsheet((string) ($item['en'] ?? ''));
            $am = $this->sanitizeForSpreadsheet((string) ($item['am'] ?? ''));
            $om = $this->sanitizeForSpreadsheet((string) ($item['om'] ?? ''));

            $rowsXml .= "<row r=\"{$rowNum}\">";
            $rowsXml .= "<c r=\"A{$rowNum}\" t=\"inlineStr\"><is><t>{$this->escapeXml($group)}</t></is></c>";
            $rowsXml .= "<c r=\"B{$rowNum}\" t=\"inlineStr\"><is><t>{$this->escapeXml($key)}</t></is></c>";
            $rowsXml .= "<c r=\"C{$rowNum}\" t=\"inlineStr\"><is><t>{$this->escapeXml($en)}</t></is></c>";
            $rowsXml .= "<c r=\"D{$rowNum}\" t=\"inlineStr\"><is><t>{$this->escapeXml($am)}</t></is></c>";
            $rowsXml .= "<c r=\"E{$rowNum}\" t=\"inlineStr\"><is><t>{$this->escapeXml($om)}</t></is></c>";
            $rowsXml .= '</row>';
        }

        $sheet1 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>' . $rowsXml . '</sheetData>
</worksheet>';

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet1);
        $zip->close();

        $filename = 'ere_translations_' . date('Y-m-d_His') . '.xlsx';

        return [
            'path' => $tempPath,
            'filename' => $filename,
        ];
    }

    /**
     * Inspect and parse an uploaded Excel workbook, generating a dry-run preview with validation errors.
     *
     * @return array{
     *     is_valid: bool,
     *     total_rows: int,
     *     new_count: int,
     *     update_count: int,
     *     unchanged_count: int,
     *     blank_count: int,
     *     languages_affected: array<string>,
     *     errors: array<array{row: int, message: string}>,
     *     rows: array<int, array{row_num: int, group: string, key: string, full_key: string, is_new: bool, is_updated: bool, values: array<string, string>}>
     * }
     */
    public function parseWorkbook(string $filePath): array
    {
        if (! file_exists($filePath) || filesize($filePath) === 0) {
            return $this->buildErrorResult(1, 'The uploaded file is empty or missing.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return $this->buildErrorResult(1, 'Invalid Excel workbook. The uploaded file is not a valid OpenXML (.xlsx) archive.');
        }

        // Parse shared strings table if present (standard Microsoft Excel / Google Sheets format)
        $sharedStrings = [];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            $sharedXmlStr = $zip->getFromName('xl/sharedStrings.xml');
            $sharedXml = simplexml_load_string((string) $sharedXmlStr);
            if ($sharedXml && isset($sharedXml->si)) {
                foreach ($sharedXml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $txt = '';
                        foreach ($si->r as $r) {
                            $txt .= (string) $r->t;
                        }
                        $sharedStrings[] = $txt;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // Locate worksheet (prefer sheet1.xml)
        $sheetPath = 'xl/worksheets/sheet1.xml';
        if ($zip->locateName($sheetPath) === false) {
            $zip->close();
            return $this->buildErrorResult(1, 'Worksheet not found. Expected a sheet named Translations.');
        }

        $sheetXmlStr = $zip->getFromName($sheetPath);
        $zip->close();

        $sheetXml = simplexml_load_string((string) $sheetXmlStr);
        if (! $sheetXml || ! isset($sheetXml->sheetData->row)) {
            return $this->buildErrorResult(1, 'The worksheet contains no readable data rows.');
        }

        $currentTranslations = $this->translationManager->getAllTranslations();
        $errors = [];
        $rows = [];
        $seenKeys = [];
        $colHeaders = [];
        $languagesAffected = [];

        $newCount = 0;
        $updateCount = 0;
        $unchangedCount = 0;
        $blankCount = 0;

        foreach ($sheetXml->sheetData->row as $rowElem) {
            $rowNum = (int) $rowElem['r'];
            $cellValues = [];

            foreach ($rowElem->c as $c) {
                $ref = (string) $c['r']; // e.g. "A1", "B2"
                $colLetter = preg_replace('/[0-9]/', '', $ref);
                $type = (string) $c['t'];

                $val = '';
                if ($type === 's') {
                    // Shared string index
                    $idx = (int) $c->v;
                    $val = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                    $val = (string) $c->is->t;
                } elseif (isset($c->v)) {
                    $val = (string) $c->v;
                }

                // Strip leading formula-neutralizing single quote
                if (str_starts_with($val, "'") && strlen($val) > 1) {
                    $leadChar = $val[1];
                    if (in_array($leadChar, ['=', '+', '-', '@'], true)) {
                        $val = substr($val, 1);
                    }
                }

                $cellValues[$colLetter] = trim($val);
            }

            // Row 1: Header parsing
            if ($rowNum === 1) {
                foreach ($cellValues as $col => $header) {
                    $norm = mb_strtolower(trim($header));
                    if (str_contains($norm, 'group')) {
                        $colHeaders[$col] = 'group';
                    } elseif (str_contains($norm, 'key')) {
                        $colHeaders[$col] = 'key';
                    } elseif (str_contains($norm, '(en)') || $norm === 'en' || str_contains($norm, 'english')) {
                        $colHeaders[$col] = 'en';
                        $languagesAffected['en'] = true;
                    } elseif (str_contains($norm, '(am)') || $norm === 'am' || str_contains($norm, 'amharic')) {
                        $colHeaders[$col] = 'am';
                        $languagesAffected['am'] = true;
                    } elseif (str_contains($norm, '(om)') || $norm === 'om' || str_contains($norm, 'oromo')) {
                        $colHeaders[$col] = 'om';
                        $languagesAffected['om'] = true;
                    } else {
                        // Check if it's an unrecognized locale column
                        if (! empty($header)) {
                            $errors[] = [
                                'row' => 1,
                                'message' => "Unrecognized column header '{$header}' at column {$col}. Only supported locales (en, am, om) are permitted.",
                            ];
                        }
                    }
                }

                if (! in_array('key', $colHeaders, true)) {
                    $errors[] = [
                        'row' => 1,
                        'message' => 'Missing required column "Key" in header row.',
                    ];
                }
                continue;
            }

            // Data rows:
            // Check if entire row is empty
            if (empty(array_filter($cellValues))) {
                continue;
            }

            $groupVal = '';
            $keyVal = '';
            $localeValues = [];

            foreach ($colHeaders as $col => $field) {
                $val = $cellValues[$col] ?? '';
                if ($field === 'group') {
                    $groupVal = $val;
                } elseif ($field === 'key') {
                    $keyVal = $val;
                } elseif (isset(self::SUPPORTED_LOCALES[$field])) {
                    $localeValues[$field] = $val;
                    if ($val === '') {
                        $blankCount++;
                    }
                }
            }

            // If key contains dot, extract group if not explicitly provided
            if ($groupVal === '' && str_contains($keyVal, '.')) {
                $parts = explode('.', $keyVal, 2);
                $groupVal = $parts[0];
            } elseif ($groupVal === '') {
                $groupVal = 'app';
            }

            if ($keyVal === '') {
                $errors[] = [
                    'row' => $rowNum,
                    'message' => "Row {$rowNum}: Missing translation key.",
                ];
                continue;
            }

            $fullKey = str_contains($keyVal, '.') ? $keyVal : "{$groupVal}.{$keyVal}";

            // Duplicate key validation within imported file
            if (isset($seenKeys[$fullKey])) {
                $errors[] = [
                    'row' => $rowNum,
                    'message' => "Row {$rowNum}: Duplicate translation key '{$fullKey}' (already defined on row {$seenKeys[$fullKey]}).",
                ];
                continue;
            }
            $seenKeys[$fullKey] = $rowNum;

            // Check against existing database catalogue
            $existing = $currentTranslations[$fullKey] ?? null;
            $isNew = ($existing === null);
            $isUpdated = false;

            if ($isNew) {
                $newCount++;
            } else {
                foreach ($localeValues as $loc => $v) {
                    if ($v !== '' && $v !== ($existing[$loc] ?? '')) {
                        $isUpdated = true;
                        break;
                    }
                }

                if ($isUpdated) {
                    $updateCount++;
                } else {
                    $unchangedCount++;
                }
            }

            $rows[] = [
                'row_num' => $rowNum,
                'group' => $groupVal,
                'key' => str_contains($keyVal, '.') ? explode('.', $keyVal, 2)[1] : $keyVal,
                'full_key' => $fullKey,
                'is_new' => $isNew,
                'is_updated' => $isUpdated,
                'values' => $localeValues,
            ];
        }

        return [
            'is_valid' => empty($errors),
            'total_rows' => count($rows),
            'new_count' => $newCount,
            'update_count' => $updateCount,
            'unchanged_count' => $unchangedCount,
            'blank_count' => $blankCount,
            'languages_affected' => array_keys($languagesAffected),
            'errors' => $errors,
            'rows' => $rows,
        ];
    }

    /**
     * Commit validated translation rows into database catalogue inside a safe transaction.
     *
     * @param  array<int, array{row_num: int, group: string, key: string, full_key: string, values: array<string, string>}>  $rows
     * @return array{imported_count: int, affected_languages: array<string>}
     */
    public function applyImport(array $rows, bool $clearEmptyCells = false, ?int $adminId = null): array
    {
        if (empty($rows)) {
            return [
                'imported_count' => 0,
                'affected_languages' => [],
            ];
        }

        return DB::transaction(function () use ($rows, $clearEmptyCells, $adminId): array {
            $importedCount = 0;
            $affectedLocales = [];
            $affectedGroups = [];
            $appliedKeys = [];

            foreach ($rows as $item) {
                $group = (string) $item['group'];
                $subKey = (string) $item['key'];
                $values = (array) ($item['values'] ?? []);

                $affectedGroups[$group] = true;

                foreach ($values as $locale => $val) {
                    $affectedLocales[$locale] = true;
                    $strVal = trim((string) $val);

                    // Safe handling of blank cells: do NOT overwrite unless explicitly requested
                    if ($strVal === '' && ! $clearEmptyCells) {
                        continue;
                    }

                    if (Schema::hasTable('system_translations')) {
                        SystemTranslation::query()->updateOrCreate(
                            [
                                'group' => $group,
                                'key' => $subKey,
                                'locale' => $locale,
                            ],
                            [
                                'value' => $strVal,
                                'is_reviewed' => true,
                                'updated_by' => $adminId,
                            ]
                        );
                    }
                }

                $appliedKeys[] = "{$group}.{$subKey}";
                $importedCount++;
            }

            // Invalidate cache tags
            foreach (array_keys($affectedLocales) as $locale) {
                foreach (array_keys($affectedGroups) as $group) {
                    Cache::forget("system_translations:{$locale}:{$group}");
                }
            }

            // Record immutable audit trail
            $this->auditService->log(
                action: 'translations.excel_imported',
                auditable: 'Translation',
                oldValues: null,
                newValues: [
                    'imported_rows' => $importedCount,
                    'locales' => array_keys($affectedLocales),
                    'groups' => array_keys($affectedGroups),
                ],
                reason: 'Admin bulk imported translations via Excel workbook',
                userId: $adminId
            );

            return [
                'imported_count' => $importedCount,
                'affected_languages' => array_keys($affectedLocales),
            ];
        });
    }

    /**
     * Prevent spreadsheet formula injection by prepending single-quote if text starts with dangerous characters.
     */
    private function sanitizeForSpreadsheet(string $val): string
    {
        $val = trim($val);
        if ($val === '') {
            return '';
        }

        $firstChar = $val[0];
        if (in_array($firstChar, ['=', '+', '-', '@'], true)) {
            return "'" . $val;
        }

        return $val;
    }

    /**
     * XML entity encoding with Unicode preservation.
     */
    private function escapeXml(string $val): string
    {
        return htmlspecialchars($val, ENT_XML1, 'UTF-8');
    }

    /**
     * Helper to return standard error result.
     */
    private function buildErrorResult(int $row, string $message): array
    {
        return [
            'is_valid' => false,
            'total_rows' => 0,
            'new_count' => 0,
            'update_count' => 0,
            'unchanged_count' => 0,
            'blank_count' => 0,
            'languages_affected' => [],
            'errors' => [
                ['row' => $row, 'message' => $message],
            ],
            'rows' => [],
        ];
    }
}
