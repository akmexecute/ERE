<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Localization\Services\ExcelTranslationService;
use App\Domains\Localization\Services\TranslationManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class LanguageAdminController extends Controller
{
    public function index(Request $request, TranslationManager $translationManager): View
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('language.view')) {
            abort(403, 'Unauthorized access to language administration.');
        }

        $translations = $translationManager->getAllTranslations();
        $stats = $translationManager->getLanguageStats();
        $locales = $translationManager->getLocales();

        $categories = array_values(array_unique(array_column($translations, 'page')));
        sort($categories);

        return view('admin.languages.index', [
            'translations' => $translations,
            'stats' => $stats,
            'locales' => $locales,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, TranslationManager $translationManager): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('language.manage')) {
            abort(403, 'Unauthorized to modify system translations.');
        }

        $validated = $request->validate([
            'translations' => ['required', 'array'],
            'translations.*.en' => ['nullable', 'string'],
            'translations.*.am' => ['nullable', 'string'],
            'translations.*.om' => ['nullable', 'string'],
        ]);

        $translationManager->saveTranslations($validated['translations'], auth()->id());

        return redirect()->back()->with('status', 'Translations saved successfully to database catalog.');
    }

    public function addPhrase(Request $request, TranslationManager $translationManager): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('language.manage')) {
            abort(403, 'Unauthorized to add system translation phrases.');
        }

        $validated = $request->validate([
            'group' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/i'],
            'key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_\.\-]+$/i'],
            'en' => ['required', 'string', 'max:500'],
            'am' => ['nullable', 'string', 'max:500'],
            'om' => ['nullable', 'string', 'max:500'],
        ]);

        $fullKey = "{$validated['group']}.{$validated['key']}";

        $translationManager->addNewPhrase(
            fullKey: $fullKey,
            en: $validated['en'],
            am: (string) ($validated['am'] ?? ''),
            om: (string) ($validated['om'] ?? ''),
            adminId: auth()->id()
        );

        return redirect()->back()->with('status', "Translation phrase '{$fullKey}' successfully created.");
    }

    /**
     * Export translation catalogue to an Excel (.xlsx) workbook.
     */
    public function exportExcel(Request $request, ExcelTranslationService $excelService): BinaryFileResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('language.export')) {
            abort(403, 'Unauthorized to export system translations.');
        }

        $category = $request->query('category');
        $search = $request->query('search');
        $missingOnly = $request->boolean('missing');

        $export = $excelService->exportTranslations(
            categoryFilter: $category ? (string) $category : null,
            searchQuery: $search ? (string) $search : null,
            missingOnly: $missingOnly
        );

        return response()->download($export['path'], $export['filename'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Preview an uploaded Excel workbook for bulk translation import.
     */
    public function importPreview(Request $request, ExcelTranslationService $excelService): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('language.import')) {
            abort(403, 'Unauthorized to import system translations.');
        }

        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB max
        ]);

        $file = $request->file('file');
        $extension = mb_strtolower((string) $file->getClientOriginalExtension());
        if ($extension !== 'xlsx') {
            return response()->json([
                'is_valid' => false,
                'errors' => [
                    ['row' => 1, 'message' => 'The uploaded file must be an Excel workbook (.xlsx).'],
                ],
            ], 422);
        }

        $preview = $excelService->parseWorkbook($file->getRealPath());

        if (! $preview['is_valid']) {
            return response()->json($preview, 422);
        }

        // Cache the parsed rows temporarily (15 minutes) for one-click confirmation
        $previewToken = 'trans_import_' . bin2hex(random_bytes(16));
        session([$previewToken => $preview['rows']]);

        return response()->json([
            ...$preview,
            'preview_token' => $previewToken,
        ]);
    }

    /**
     * Commit the previewed import rows into the database catalog.
     */
    public function importConfirm(Request $request, ExcelTranslationService $excelService): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('language.import')) {
            abort(403, 'Unauthorized to import system translations.');
        }

        $request->validate([
            'preview_token' => ['required', 'string'],
            'clear_empty_cells' => ['nullable', 'boolean'],
        ]);

        $token = (string) $request->input('preview_token');
        $rows = session($token);

        if (! is_array($rows) || empty($rows)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Import session expired. Please re-upload your Excel workbook.'], 422);
            }
            return redirect()->back()->withErrors(['error' => 'Import session expired. Please re-upload your Excel workbook.']);
        }

        $clearEmpty = $request->boolean('clear_empty_cells');
        $result = $excelService->applyImport($rows, clearEmptyCells: $clearEmpty, adminId: $user->id);

        session()->forget($token);

        $msg = "Excel translation import completed: {$result['imported_count']} phrases processed across [" . implode(', ', $result['affected_languages']) . "].";

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $msg,
                ...$result,
            ]);
        }

        return redirect()->back()->with('status', $msg);
    }
}
