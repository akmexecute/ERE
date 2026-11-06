<x-app-layout>
    <div
        x-data="{
            searchQuery: '',
            selectedCategory: 'all',
            showAddModal: false,
            items: @js(array_values($translations)),
            get filteredItems() {
                return this.items.filter(item => {
                    const matchCategory = this.selectedCategory === 'all' || item.page === this.selectedCategory;
                    const q = this.searchQuery.toLowerCase();
                    const matchSearch = !q
                        || item.full_key.toLowerCase().includes(q)
                        || item.en.toLowerCase().includes(q)
                        || item.am.toLowerCase().includes(q)
                        || item.om.toLowerCase().includes(q)
                        || item.page.toLowerCase().includes(q);
                    return matchCategory && matchSearch;
                });
            }
        }"
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6"
    >
        <!-- Top Breadcrumb & Actions -->
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-5">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-teal-800 uppercase tracking-wider">
                    <a href="{{ url('/admin/dashboard') }}" class="hover:underline">Admin Center</a>
                    <span>/</span>
                    <span>System Settings &amp; Localization</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 mt-1">Multi-Language &amp; i18n Translation Management</h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Configure words across the website in English, Amharic (አማርኛ), and Afaan Oromo. Changes apply immediately to all visitor screens.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a
                    href="{{ url('/login') }}"
                    target="_blank"
                    class="px-3.5 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 transition shadow-xs flex items-center gap-1.5"
                >
                    <span>🔐 Preview Login Page</span>
                    <span class="text-slate-400">↗</span>
                </a>
                <button
                    type="button"
                    @click="showAddModal = true"
                    class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold shadow-sm transition flex items-center gap-1.5"
                >
                    <span>+ Add New Word / Phrase</span>
                </button>
            </div>
        </div>

        @if(session('status'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-semibold flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- Filter & Search Toolbar -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs space-y-3">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                <!-- Real-time Live Search Input -->
                <div class="relative w-full sm:w-96">
                    <input
                        type="text"
                        x-model="searchQuery"
                        placeholder="Search words, e.g. 'Send 6-Digit' or 'auth' or 'ባለ 6'..."
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

                <!-- Match Count Feedback -->
                <div class="text-xs text-slate-500 font-medium">
                    Showing <strong class="text-slate-900" x-text="filteredItems.length"></strong> of <strong>{{ count($translations) }}</strong> platform phrases
                </div>
            </div>

            <!-- Page / Category Filter Tabs -->
            <div class="flex items-center gap-1.5 flex-wrap pt-1 border-t border-slate-100 text-xs">
                <span class="text-slate-400 font-medium mr-1 text-[11px] uppercase tracking-wider">Pages:</span>
                <button
                    type="button"
                    @click="selectedCategory = 'all'"
                    :class="selectedCategory === 'all' ? 'bg-teal-700 text-white font-bold' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-medium'"
                    class="px-2.5 py-1 rounded-lg text-xs transition"
                >
                    All Website Pages
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

        <!-- Translation Editor Form -->
        <form method="POST" action="{{ url('/admin/settings/translations') }}" class="space-y-4">
            @csrf

            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Translation Matrix</h2>
                        <span class="text-[11px] text-slate-500">Edit translations directly in any column below and click Save Changes</span>
                    </div>

                    <button
                        type="submit"
                        class="px-5 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-sm transition flex items-center gap-1.5"
                    >
                        <span>💾 Save All Translations</span>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-100/70 border-b border-slate-200 text-slate-600 uppercase font-semibold text-[11px]">
                                <th class="py-3 px-4 w-1/4">Key &amp; Page Context</th>
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
                                <tr class="hover:bg-slate-50/70 transition">
                                    <!-- Key & Page Info -->
                                    <td class="py-3.5 px-4 align-top space-y-1">
                                        <div class="font-mono font-semibold text-slate-800 text-[11px]" x-text="item.full_key"></div>
                                        <span
                                            class="inline-block text-[10px] font-medium px-2 py-0.5 rounded-md bg-teal-50 text-teal-800 border border-teal-200/50"
                                            x-text="item.page"
                                        ></span>
                                    </td>

                                    <!-- English (EN) -->
                                    <td class="py-2.5 px-3 align-top">
                                        <textarea
                                            :name="'translations[' + item.full_key + '][en]'"
                                            x-model="item.en"
                                            rows="2"
                                            class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-teal-600 focus:ring-1 focus:ring-teal-600/30 bg-slate-50/50"
                                        ></textarea>
                                    </td>

                                    <!-- Amharic (AM) -->
                                    <td class="py-2.5 px-3 align-top">
                                        <textarea
                                            :name="'translations[' + item.full_key + '][am]'"
                                            x-model="item.am"
                                            rows="2"
                                            dir="ltr"
                                            class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-teal-600 focus:ring-1 focus:ring-teal-600/30 bg-slate-50/50 font-['Noto_Sans_Ethiopic']"
                                        ></textarea>
                                    </td>

                                    <!-- Afaan Oromo (OM) -->
                                    <td class="py-2.5 px-3 align-top">
                                        <textarea
                                            :name="'translations[' + item.full_key + '][om]'"
                                            x-model="item.om"
                                            rows="2"
                                            class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 focus:border-teal-600 focus:ring-1 focus:ring-teal-600/30 bg-slate-50/50"
                                        ></textarea>
                                    </td>
                                </tr>
                            </template>

                            <tr x-show="filteredItems.length === 0">
                                <td colspan="4" class="py-8 text-center text-slate-400">
                                    No words match your search criteria. Try another keyword or clear filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                    <span class="text-xs text-slate-500">
                        Translations are synchronized into <code class="text-teal-800 font-mono">lang/en</code>, <code class="text-teal-800 font-mono">lang/am</code>, and <code class="text-teal-800 font-mono">lang/om</code>.
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
                class="bg-white rounded-2xl border border-slate-200 max-w-lg w-full p-6 space-y-4 shadow-xl"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Add New Translation Phrase</h3>
                    <button type="button" @click="showAddModal = false" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>

                <form method="POST" action="{{ url('/admin/settings/translations/add') }}" class="space-y-3.5 text-xs">
                    @csrf

                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-1">
                            <label class="block font-semibold text-slate-700 mb-1">Group / Domain</label>
                            <select name="group" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs">
                                <option value="app" selected>app (General / Navigation / Hero / Dashboard)</option>
                                <option value="auth">auth (Login / OTP / Roles)</option>
                                <option value="marketplace">marketplace (Search & Property Details)</option>
                                <option value="stays">stays (Short-Stays & Host Ops)</option>
                                <option value="crm">crm (Agency CRM & Pipeline)</option>
                                <option value="projects">projects (Developments & Inventory)</option>
                                <option value="listings">listings (Post Property Listing)</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block font-semibold text-slate-700 mb-1">Key Name (e.g. submit_btn)</label>
                            <input
                                type="text"
                                name="key"
                                required
                                placeholder="e.g. welcome_banner"
                                class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">🇬🇧 English Text</label>
                        <input
                            type="text"
                            name="en"
                            required
                            placeholder="e.g. Welcome to EthioProperty"
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs"
                        >
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">🇪🇹 Amharic Translation (አማርኛ)</label>
                        <input
                            type="text"
                            name="am"
                            required
                            placeholder="e.g. እንኳን ወደ ኢትዮ ፕሮፐርቲ በደህና መጡ"
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs font-['Noto_Sans_Ethiopic']"
                        >
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">🌳 Afaan Oromo Translation</label>
                        <input
                            type="text"
                            name="om"
                            required
                            placeholder="e.g. Baga nagaan dhuftan gara EthioProperty"
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs"
                        >
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="showAddModal = false"
                            class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 font-semibold"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white font-bold"
                        >
                            Create Translation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
