<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Services\SettingService;
use App\Domains\Localization\Services\TranslationManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SettingController extends Controller
{
    public function index(Request $request, TranslationManager $translationManager, SettingService $settingService): View
    {
        $translations = $translationManager->getAllTranslations();
        $activeTab = (string) $request->query('tab', 'translations');

        // Extract available categories
        $categories = array_values(array_unique(array_column($translations, 'page')));
        sort($categories);

        return view('admin.settings', [
            'translations' => $translations,
            'categories' => $categories,
            'activeTab' => $activeTab,
            'settings' => $settingService->all(allowPrivate: true),
        ]);
    }

    public function updateTranslations(Request $request, TranslationManager $translationManager): RedirectResponse
    {
        $validated = $request->validate([
            'translations' => ['required', 'array'],
            'translations.*.en' => ['required', 'string'],
            'translations.*.am' => ['required', 'string'],
            'translations.*.om' => ['required', 'string'],
        ]);

        $translationManager->saveTranslations($validated['translations'], auth()->id());

        return redirect()->to(url('/admin/settings?tab=translations'))
            ->with('status', 'Website translations successfully saved! All pages will now reflect these words.');
    }

    public function addPhrase(Request $request, TranslationManager $translationManager): RedirectResponse
    {
        $validated = $request->validate([
            'group' => ['required', 'string', 'in:app,auth,marketplace,stays,crm,projects,listings'],
            'key' => ['required', 'string', 'regex:/^[a-zA-Z0-9_\.]+$/'],
            'en' => ['required', 'string'],
            'am' => ['required', 'string'],
            'om' => ['required', 'string'],
        ]);

        $fullKey = $validated['group'] . '.' . trim($validated['key']);

        $translationManager->addNewPhrase(
            fullKey: $fullKey,
            en: $validated['en'],
            am: $validated['am'],
            om: $validated['om'],
            adminId: auth()->id()
        );

        return redirect()->to(url('/admin/settings?tab=translations'))
            ->with('status', "New translation phrase [{$fullKey}] added successfully!");
    }
}
