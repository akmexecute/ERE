<x-app-layout>
    <div
        x-data="{
            searchQuery: '',
            selectedCategory: 'all',
            showMissingOnly: false,
            showAddModal: false,
            showImportModal: false,
            importFile: null,
            importFileName: '',
            clearEmptyCells: false,
            importLoading: false,
            importPreviewData: null,
            importError: null,
            activeTabLocale: 'all',
            items: @js(array_values($translations)),
            get filteredItems() {
                return this.items.filter(item => {
                    const matchCategory = this.selectedCategory === 'all' || item.page === this.selectedCategory;
                    const matchMissing = !this.showMissingOnly || item.has_missing;
                    const q = this.searchQuery.toLowerCase();
                    const matchSearch = !q
                        || item.full_key.toLowerCase().includes(q)
                        || item.en.toLowerCase().includes(q)
                        || item.am.toLowerCase().includes(q)
                        || item.om.toLowerCase().includes(q);
                    return matchCategory && matchMissing && matchSearch;
                });
            },
            async previewImport() {
                const fileInput = document.getElementById('excel_import_file');
                if (!fileInput || !fileInput.files.length) {
                    this.importError = 'Please select a valid .xlsx workbook file.';
                    return;
                }
                this.importLoading = true;
                this.importError = null;
                this.importPreviewData = null;

                const formData = new FormData();
                formData.append('file', fileInput.files[0]);

                try {
                    const resp = await fetch('{{ route('admin.languages.import_preview') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json'
                        },
                        body: formData
                    });
                    const data = await resp.json();
                    if (!resp.ok) {
                        this.importError = data.message || (data.errors && data.errors[0] ? data.errors[0].message : 'Validation failed.');
                        this.importPreviewData = data;
                    } else {
                        this.importPreviewData = data;
                    }
                } catch (err) {
                    this.importError = 'Failed to process Excel workbook: ' + err.message;
                } finally {
                    this.importLoading = false;
                }
            },
            async confirmImport() {
                if (!this.importPreviewData || !this.importPreviewData.preview_token) return;
                this.importLoading = true;
                this.importError = null;

                try {
                    const resp = await fetch('{{ route('admin.languages.import_confirm') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            preview_token: this.importPreviewData.preview_token,
                            clear_empty_cells: this.clearEmptyCells
                        })
                    });
                    const data = await resp.json();
                    if (!resp.ok) {
                        this.importError = data.message || 'Import failed.';
                    } else {
                        this.showImportModal = false;
                        window.location.reload();
                    }
                } catch (err) {
                    this.importError = 'Failed to apply import: ' + err.message;
                } finally {
                    this.importLoading = false;
                }
            }
        }"
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6"
    >
        <!-- Urbano Header: Architectural Slate-900 Bar with Subtle Pills -->
        <div class="rounded-3xl bg-slate-900 text-white p-6 sm:p-8 border border-slate-800 shadow-xl relative overflow-hidden">
            <div class="relative z-10 flex flex-wrap items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-teal-400">
                        <a href="{{ url('/admin/dashboard') }}" class="hover:underline text-teal-300">Admin Operations</a>
                        <span class="text-slate-600">/</span>
                        <span>Module 00: Foundation &amp; i18n</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight mt-1 text-white">
                        Language &amp; Translation Management
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-2xl">
                        Database-driven translation catalog with runtime caching, bulk Excel import/export, and instant fallback propagation across all public and agent interfaces.
                    </p>
                </div>

                <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                    <a
                        :href="`{{ route('admin.languages.export') }}?category=${selectedCategory}&search=${encodeURIComponent(searchQuery)}&missing=${showMissingOnly ? 1 : 0}`"
                        class="px-3.5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-teal-300 border border-slate-700 text-xs font-semibold shadow-md transition flex items-center gap-2"
                        title="Download translation catalog as an Excel (.xlsx) workbook"
                    >
                        <span>📊</span>
                        <span>Export Excel</span>
                    </a>
                    <button
                        type="button"
                        @click="showImportModal = true; importPreviewData = null; importError = null; importFileName = '';"
                        class="px-3.5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold shadow-md transition flex items-center gap-2"
                        title="Upload an Excel workbook to update translations in bulk"
                    >
                        <span>📥</span>
                        <span>Import Excel</span>
                    </button>
                    <button
                        type="button"
                        @click="showAddModal = true"
                        class="px-4 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-semibold shadow-md transition flex items-center gap-2"
                    >
                        <span>+</span>
                        <span>Add New Phrase</span>
                    </button>
                    <a
                        href="{{ url('/admin/settings') }}"
                        class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold transition"
                    >
                        Settings
                    </a>
                </div>
            </div>
        </div>

        @if(session('status'))
            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-950 text-xs font-semibold flex items-center gap-2 shadow-xs">
                <span class="text-emerald-700 text-sm">✓</span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-950 text-xs font-medium space-y-1 shadow-xs">
                <p class="font-bold">Validation Error:</p>
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Language Overview & Coverage Metric Cards (Urbano Minimalist Aesthetics) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @foreach($stats as $code => $lang)
                <div class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-xs relative">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="text-2xl">{{ $lang['flag'] }}</span>
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">{{ $lang['name'] }}</h2>
                                <span class="text-xs text-slate-500 font-mono">{{ $lang['native'] }} ({{ strtoupper($code) }})</span>
                            </div>
                        </div>

                        @if($lang['is_default'])
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-teal-800 border border-teal-200">
                                Default Fallback
                            </span>
                        @else
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                Active Locale
                            </span>
                        @endif
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                        <div class="space-y-0.5">
                            <span class="text-slate-500 text-[11px]">Translated</span>
                            <div class="font-bold text-slate-900">{{ $lang['translated'] }} / {{ $lang['total'] }}</div>
                        </div>

                        <div class="text-right space-y-0.5">
                            <span class="text-slate-500 text-[11px]">Coverage</span>
                            <div class="font-bold text-teal-800">{{ $lang['coverage'] }}%</div>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div class="w-full bg-slate-100 rounded-full h-1.5 mt-3 overflow-hidden">
                        <div
                            class="h-1.5 rounded-full {{ $lang['coverage'] === 100 ? 'bg-teal-600' : 'bg-amber-500' }}"
                            style="width: {{ $lang['coverage'] }}%"
                        ></div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Toolbar: Search, Filters & Missing Toggle -->
        <div class="bg-white rounded-2xl border border-slate-200/90 p-4 shadow-xs space-y-3.5">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                <!-- Search Input -->
                <div class="relative w-full sm:w-96">
                    <input
                        type="text"
                        x-model="searchQuery"
                        placeholder="Search key or text, e.g. 'nav.buy' or 'መኪና'..."
                        class="w-full pl-9 pr-4 py-2 text-xs rounded-xl border border-slate-300 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20"
                    >
                    <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
                    <button
                        x-show="searchQuery"
                        @click="searchQuery = ''"
                        type="button"
                        class="absolute right-3 top-2 text-slate-400 hover:text-slate-600 text-xs"
                    >&times;</button>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Missing Only Filter Toggle -->
                    <button
                        type="button"
                        @click="showMissingOnly = !showMissingOnly"
                        :class="showMissingOnly ? 'bg-amber-500 text-white font-bold' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-medium'"
                        class="px-3 py-1.5 rounded-xl text-xs transition flex items-center gap-1.5 border border-transparent"
                    >
                        <span>⚠️</span>
                        <span>Show Missing Only</span>
                    </button>

                    <span class="text-xs text-slate-500 font-medium">
                        Showing <strong class="text-slate-900" x-text="filteredItems.length"></strong> of <strong>{{ count($translations) }}</strong> phrases
                    </span>
                </div>
            </div>

            <!-- Page / Category Filter Pills -->
            <div class="flex items-center gap-1.5 flex-wrap pt-2 border-t border-slate-100 text-xs">
                <span class="text-slate-400 font-medium mr-1 text-[11px] uppercase tracking-wider">Category:</span>
                <button
                    type="button"
                    @click="selectedCategory = 'all'"
                    :class="selectedCategory === 'all' ? 'bg-teal-700 text-white font-bold' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-medium'"
                    class="px-2.5 py-1 rounded-lg text-xs transition"
                >
                    All Categories
                </button>
                @foreach($categories as $cat)
                    <button
                        type="button"
                        @click="selectedCategory = @js($cat)"
                        :class="selectedCategory === @js($cat) ? 'bg-teal-700 text-white font-bold' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-medium'"
                        class="px-2.5 py-1 rounded-lg text-xs transition"
                    >
                        {{ $cat }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Translation Matrix Form -->
        <form method="POST" action="{{ route('admin.languages.update') }}" class="space-y-4">
            @csrf

            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Translation Matrix</h2>
                        <span class="text-[11px] text-slate-500">Edit translations directly in any column and click Save Changes to persist to database</span>
                    </div>

                    <button
                        type="submit"
                        class="px-5 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-sm transition flex items-center gap-1.5"
                    >
                        <span>💾 Save Translations</span>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-100/70 border-b border-slate-200 text-slate-600 uppercase font-semibold text-[11px]">
                                <th class="py-3 px-4 w-1/4">Key &amp; Category</th>
                                <th class="py-3 px-4 w-1/4">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span>🇬🇧</span>
                                        <span>English (EN)</span>
                                    </span>
                                </th>
                                <th class="py-3 px-4 w-1/4">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span>🇪🇹</span>
                                        <span>Amharic (አማርኛ)</span>
                                    </span>
                                </th>
                                <th class="py-3 px-4 w-1/4">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span>🌳</span>
                                        <span>Afaan Oromo (OM)</span>
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="item in filteredItems" :key="item.full_key">
                                <tr class="hover:bg-slate-50/80 transition" :class="item.has_missing ? 'bg-amber-50/20' : ''">
                                    <!-- Key & Category Context -->
                                    <td class="py-3.5 px-4 align-top space-y-1">
                                        <div class="font-mono font-semibold text-slate-800 text-[11px]" x-text="item.full_key"></div>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span
                                                class="inline-block text-[10px] font-medium px-2 py-0.5 rounded-md bg-teal-50 text-teal-800 border border-teal-200/50"
                                                x-text="item.page"
                                            ></span>
                                            <template x-if="item.has_missing">
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-900">
                                                    Incomplete
                                                </span>
                                            </template>
                                        </div>
                                    </td>

                                    <!-- English -->
                                    <td class="py-2.5 px-3 align-top">
                                        <textarea
                                            :name="'translations[' + item.full_key + '][en]'"
                                            x-model="item.en"
                                            rows="2"
                                            class="w-full px-2.5 py-1.5 text-xs rounded-xl border border-slate-200 focus:border-teal-600 focus:ring-1 focus:ring-teal-600/30 bg-slate-50/60"
                                        ></textarea>
                                    </td>

                                    <!-- Amharic -->
                                    <td class="py-2.5 px-3 align-top">
                                        <textarea
                                            :name="'translations[' + item.full_key + '][am]'"
                                            x-model="item.am"
                                            rows="2"
                                            dir="ltr"
                                            placeholder="[ትርጉም ያስገቡ]"
                                            class="w-full px-2.5 py-1.5 text-xs rounded-xl border border-slate-200 focus:border-teal-600 focus:ring-1 focus:ring-teal-600/30 bg-slate-50/60 font-['Noto_Sans_Ethiopic']"
                                            :class="!item.am ? 'border-amber-300 bg-amber-50/30' : ''"
                                        ></textarea>
                                    </td>

                                    <!-- Afaan Oromo -->
                                    <td class="py-2.5 px-3 align-top">
                                        <textarea
                                            :name="'translations[' + item.full_key + '][om]'"
                                            x-model="item.om"
                                            rows="2"
                                            placeholder="[Hiika galchaa]"
                                            class="w-full px-2.5 py-1.5 text-xs rounded-xl border border-slate-200 focus:border-teal-600 focus:ring-1 focus:ring-teal-600/30 bg-slate-50/60"
                                            :class="!item.om ? 'border-amber-300 bg-amber-50/30' : ''"
                                        ></textarea>
                                    </td>
                                </tr>
                            </template>

                            <tr x-show="filteredItems.length === 0">
                                <td colspan="4" class="py-12 text-center text-slate-400">
                                    No translation keys match your query or filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                    <span class="text-xs text-slate-500">
                        Platform catalog synchronizes to <code class="text-teal-800 font-mono">system_translations</code> table with fallback to file dictionaries.
                    </span>

                    <button
                        type="submit"
                        class="px-5 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-sm transition"
                    >
                        Save All Changes
                    </button>
                </div>
            </div>
        </form>

        <!-- Add New Phrase Modal -->
        <div
            x-show="showAddModal"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div
                @click.away="showAddModal = false"
                class="bg-white rounded-3xl border border-slate-200 max-w-lg w-full p-6 space-y-4 shadow-2xl"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Add New Translation Phrase</h3>
                    <button type="button" @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form method="POST" action="{{ route('admin.languages.add') }}" class="space-y-3.5 text-xs">
                    @csrf

                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-1">
                            <label class="block font-semibold text-slate-700 mb-1">Group</label>
                            <select name="group" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs">
                                <option value="app" selected>app</option>
                                <option value="auth">auth</option>
                                <option value="marketplace">marketplace</option>
                                <option value="stays">stays</option>
                                <option value="crm">crm</option>
                                <option value="projects">projects</option>
                                <option value="listings">listings</option>
                            </select>
                        </div>

                        <div class="col-span-2">
                            <label class="block font-semibold text-slate-700 mb-1">Semantic Key (e.g. "nav.buy")</label>
                            <input
                                type="text"
                                name="key"
                                required
                                placeholder="e.g. nav.pricing"
                                class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs font-mono"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">English Source Text (Default Fallback)</label>
                        <input
                            type="text"
                            name="en"
                            required
                            placeholder="e.g. Pricing"
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs"
                        >
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Amharic (አማርኛ)</label>
                        <input
                            type="text"
                            name="am"
                            placeholder="e.g. የዋጋ ዝርዝር"
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs font-['Noto_Sans_Ethiopic']"
                        >
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Afaan Oromo</label>
                        <input
                            type="text"
                            name="om"
                            placeholder="e.g. Tarree gatii"
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs"
                        >
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showAddModal = false"
                            class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 text-xs font-semibold"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold shadow-sm"
                        >
                            Save Phrase
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Urbano Excel Bulk Import & Dry-Run Preview Modal -->
        <div
            x-show="showImportModal"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div
                @click.outside="if (!importLoading) showImportModal = false"
                class="bg-white rounded-3xl max-w-2xl w-full border border-slate-200 shadow-2xl p-6 sm:p-8 space-y-6 max-h-[90vh] flex flex-col"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-teal-700">Bulk Translation Pipeline</span>
                        <h3 class="text-lg font-bold text-slate-900 mt-0.5">Import Excel (.xlsx) Translations</h3>
                    </div>
                    <button
                        type="button"
                        @click="showImportModal = false"
                        :disabled="importLoading"
                        class="text-slate-400 hover:text-slate-600 text-lg"
                    >&times;</button>
                </div>

                <!-- Error Alert -->
                <div x-show="importError" x-cloak class="p-4 rounded-2xl bg-red-50 border border-red-200 text-xs text-red-900">
                    <p class="font-bold">Import Notice:</p>
                    <p x-text="importError" class="mt-0.5"></p>
                </div>

                <!-- Step 1: Upload & Configure -->
                <div x-show="!importPreviewData" class="space-y-4">
                    <p class="text-xs text-slate-600">
                        Upload an official <strong>.xlsx</strong> translation workbook. Keys are matched by group and key identifiers. Unicode strings (including Ethiopic Amharic and Afaan Oromo) are fully preserved.
                    </p>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Select Excel Workbook (.xlsx)</label>
                        <input
                            type="file"
                            id="excel_import_file"
                            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            @change="importFileName = $event.target.files[0] ? $event.target.files[0].name : ''"
                            class="block w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 cursor-pointer border border-slate-200 rounded-xl p-2"
                        >
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-2">
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input
                                type="checkbox"
                                x-model="clearEmptyCells"
                                class="mt-0.5 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                            >
                            <span class="text-xs text-slate-700">
                                <strong>Clear existing translations if spreadsheet cell is empty</strong>
                                <span class="block text-[11px] text-slate-500 mt-0.5">
                                    Recommended: leave unchecked (Safe Mode). When unchecked, empty Excel cells preserve their current translations without erasure.
                                </span>
                            </span>
                        </label>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                        <a
                            href="{{ route('admin.languages.export') }}"
                            class="text-xs font-semibold text-teal-700 hover:underline flex items-center gap-1.5"
                        >
                            <span>📥</span>
                            <span>Download Current Template (.xlsx)</span>
                        </a>

                        <button
                            type="button"
                            @click="previewImport()"
                            :disabled="importLoading || !importFileName"
                            class="px-5 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 disabled:opacity-50 text-white text-xs font-semibold shadow-xs flex items-center gap-2 transition"
                        >
                            <span x-show="importLoading" class="animate-spin text-sm">⏳</span>
                            <span x-text="importLoading ? 'Validating Workbook...' : 'Preview Spreadsheet &amp; Validate'"></span>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Dry-Run Preview Table & Confirmation -->
                <div x-show="importPreviewData" x-cloak class="space-y-4 flex-1 flex flex-col min-h-0">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                            <div class="text-[10px] uppercase font-bold text-slate-500">Total Rows</div>
                            <div class="text-lg font-bold text-slate-900" x-text="importPreviewData?.total_rows || 0"></div>
                        </div>
                        <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200">
                            <div class="text-[10px] uppercase font-bold text-emerald-700">New Keys</div>
                            <div class="text-lg font-bold text-emerald-900" x-text="'+' + (importPreviewData?.new_count || 0)"></div>
                        </div>
                        <div class="p-3 rounded-xl bg-amber-50 border border-amber-200">
                            <div class="text-[10px] uppercase font-bold text-amber-700">Updates</div>
                            <div class="text-lg font-bold text-amber-900" x-text="'~' + (importPreviewData?.update_count || 0)"></div>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                            <div class="text-[10px] uppercase font-bold text-slate-500">Unchanged</div>
                            <div class="text-lg font-bold text-slate-700" x-text="importPreviewData?.unchanged_count || 0"></div>
                        </div>
                    </div>

                    <!-- Validation Errors List if any -->
                    <template x-if="importPreviewData && importPreviewData.errors && importPreviewData.errors.length > 0">
                        <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-950 space-y-1">
                            <div class="font-bold">Errors found in spreadsheet:</div>
                            <ul class="list-disc list-inside max-h-32 overflow-y-auto space-y-0.5">
                                <template x-for="err in importPreviewData.errors" :key="err.row + err.message">
                                    <li x-text="err.message"></li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    <!-- Preview Sample Rows -->
                    <div class="flex-1 overflow-y-auto border border-slate-200 rounded-xl text-xs">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-slate-50 sticky top-0 border-b border-slate-200 text-[10px] uppercase font-bold text-slate-600">
                                <tr>
                                    <th class="p-2.5">Row</th>
                                    <th class="p-2.5">Translation Key</th>
                                    <th class="p-2.5">English</th>
                                    <th class="p-2.5">Amharic (አማ)</th>
                                    <th class="p-2.5">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="row in (importPreviewData?.rows || []).slice(0, 15)" :key="row.row_num">
                                    <tr class="hover:bg-slate-50">
                                        <td class="p-2.5 font-mono text-slate-400" x-text="row.row_num"></td>
                                        <td class="p-2.5 font-mono font-medium text-slate-800" x-text="row.full_key"></td>
                                        <td class="p-2.5 text-slate-700 truncate max-w-[140px]" x-text="row.values.en || '-'"></td>
                                        <td class="p-2.5 text-slate-700 truncate max-w-[140px] font-['Noto_Sans_Ethiopic']" x-text="row.values.am || '-'"></td>
                                        <td class="p-2.5">
                                            <span x-show="row.is_new" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">New</span>
                                            <span x-show="row.is_updated" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">Update</span>
                                            <span x-show="!row.is_new && !row.is_updated" class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-600">Unchanged</span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                        <button
                            type="button"
                            @click="importPreviewData = null"
                            class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 text-xs font-semibold"
                        >
                            &larr; Back to Upload
                        </button>

                        <button
                            type="button"
                            @click="confirmImport()"
                            :disabled="importLoading || !importPreviewData?.is_valid"
                            class="px-5 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 disabled:opacity-50 text-white text-xs font-semibold shadow-xs flex items-center gap-2 transition"
                        >
                            <span x-show="importLoading" class="animate-spin text-sm">⏳</span>
                            <span x-text="importLoading ? 'Applying Import...' : 'Confirm &amp; Apply Import to Catalog'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
