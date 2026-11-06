<x-app-layout>
    <div
        x-data="{
            activeTab: 'residential',
            searchFilter: '',

            // Modals state
            showCategoryModal: false,
            categoryForm: { id: null, name_en: '', name_am: '', name_om: '', icon: '', sort_order: 0, is_active: true },

            showAddCategoryModal: false,
            addCategoryForm: { name_en: '', name_am: '', name_om: '', code: '', slug: '', icon: '', sort_order: 0, is_active: true },

            showAddTypeModal: false,
            addTypeForm: { category_id: null, name_en: '', name_am: '', name_om: '', code: '', slug: '', area_basis: 'building_area', requires_bedrooms: true, requires_floors: true, sort_order: 0, is_active: true },

            showEditTypeModal: false,
            editTypeForm: { id: null, category_id: null, name_en: '', name_am: '', name_om: '', code: '', slug: '', area_basis: 'building_area', requires_bedrooms: true, requires_floors: true, sort_order: 0, is_active: true },

            showAddListingTypeModal: false,
            addListingTypeForm: { code: '', slug: '', transaction_mode: 'BUY', name_en: '', name_am: '', name_om: '', default_price_period: 'total', sort_order: 0, is_phase1_enabled: true },

            showEditListingTypeModal: false,
            editListingTypeForm: { id: null, code: '', slug: '', transaction_mode: 'BUY', name_en: '', name_am: '', name_om: '', default_price_period: 'total', sort_order: 0, is_phase1_enabled: true },

            showAddAmenityModal: false,
            addAmenityForm: { group_key: 'utilities', name_en: '', name_am: '', name_om: '', slug: '', icon: '', sort_order: 0, is_active: true },

            showEditAmenityModal: false,
            editAmenityForm: { id: null, group_key: 'utilities', name_en: '', name_am: '', name_om: '', slug: '', icon: '', sort_order: 0, is_active: true },

            showAddFeatureModal: false,
            addFeatureForm: { name_en: '', name_am: '', name_om: '', slug: '', input_type: 'text', options_json: '', unit: '', applicable_category_codes: ['RES'], sort_order: 0, is_active: true },

            showEditFeatureModal: false,
            editFeatureForm: { id: null, name_en: '', name_am: '', name_om: '', slug: '', input_type: 'text', options_json: '', unit: '', applicable_category_codes: [], sort_order: 0, is_active: true },

            openAddTypeFor(categoryId, defaultAreaBasis = 'building_area') {
                this.addTypeForm.category_id = categoryId;
                this.addTypeForm.area_basis = defaultAreaBasis;
                this.addTypeForm.name_en = '';
                this.addTypeForm.name_am = '';
                this.addTypeForm.name_om = '';
                this.addTypeForm.code = '';
                this.addTypeForm.slug = '';
                this.addTypeForm.sort_order = 0;
                this.addTypeForm.is_active = true;
                this.showAddTypeModal = true;
            },

            openEditType(type) {
                this.editTypeForm.id = type.id;
                this.editTypeForm.category_id = type.category_id;
                this.editTypeForm.name_en = type.name_en || '';
                this.editTypeForm.name_am = type.name_am || '';
                this.editTypeForm.name_om = type.name_om || '';
                this.editTypeForm.code = type.code || '';
                this.editTypeForm.slug = type.slug || '';
                this.editTypeForm.area_basis = type.area_basis || 'building_area';
                this.editTypeForm.requires_bedrooms = Boolean(type.requires_bedrooms);
                this.editTypeForm.requires_floors = Boolean(type.requires_floors);
                this.editTypeForm.sort_order = type.sort_order || 0;
                this.editTypeForm.is_active = Boolean(type.is_active);
                this.showEditTypeModal = true;
            },

            openEditCategory(cat) {
                this.categoryForm.id = cat.id;
                this.categoryForm.name_en = cat.name_en || '';
                this.categoryForm.name_am = cat.name_am || '';
                this.categoryForm.name_om = cat.name_om || '';
                this.categoryForm.icon = cat.icon || '';
                this.categoryForm.sort_order = cat.sort_order || 0;
                this.categoryForm.is_active = Boolean(cat.is_active);
                this.showCategoryModal = true;
            },

            openEditListingType(lt) {
                this.editListingTypeForm.id = lt.id;
                this.editListingTypeForm.code = lt.code || '';
                this.editListingTypeForm.slug = lt.slug || '';
                this.editListingTypeForm.transaction_mode = lt.transaction_mode || 'BUY';
                this.editListingTypeForm.name_en = lt.name_en || '';
                this.editListingTypeForm.name_am = lt.name_am || '';
                this.editListingTypeForm.name_om = lt.name_om || '';
                this.editListingTypeForm.default_price_period = lt.default_price_period || 'total';
                this.editListingTypeForm.sort_order = lt.sort_order || 0;
                this.editListingTypeForm.is_phase1_enabled = Boolean(lt.is_phase1_enabled);
                this.showEditListingTypeModal = true;
            },

            openEditAmenity(am) {
                this.editAmenityForm.id = am.id;
                this.editAmenityForm.group_key = am.group_key || 'utilities';
                this.editAmenityForm.name_en = am.name_en || '';
                this.editAmenityForm.name_am = am.name_am || '';
                this.editAmenityForm.name_om = am.name_om || '';
                this.editAmenityForm.slug = am.slug || '';
                this.editAmenityForm.icon = am.icon || '';
                this.editAmenityForm.sort_order = am.sort_order || 0;
                this.editAmenityForm.is_active = Boolean(am.is_active);
                this.showEditAmenityModal = true;
            },

            openEditFeature(feat) {
                this.editFeatureForm.id = feat.id;
                this.editFeatureForm.name_en = feat.name_en || '';
                this.editFeatureForm.name_am = feat.name_am || '';
                this.editFeatureForm.name_om = feat.name_om || '';
                this.editFeatureForm.slug = feat.slug || '';
                this.editFeatureForm.input_type = feat.input_type || 'text';
                this.editFeatureForm.options_json = Array.isArray(feat.options_json) ? feat.options_json.join(', ') : '';
                this.editFeatureForm.unit = feat.unit || '';
                this.editFeatureForm.applicable_category_codes = Array.isArray(feat.applicable_category_codes) ? [...feat.applicable_category_codes] : [];
                this.editFeatureForm.sort_order = feat.sort_order || 0;
                this.editFeatureForm.is_active = Boolean(feat.is_active);
                this.showEditFeatureModal = true;
            }
        }"
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6"
    >
        <!-- Urbano Header: Architectural Slate-900 Command Bar -->
        <div class="rounded-3xl bg-slate-900 text-white p-6 sm:p-8 border border-slate-800 shadow-xl relative overflow-hidden">
            <div class="relative z-10 flex flex-wrap items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-teal-400">
                        <a href="{{ url('/admin/dashboard') }}" class="hover:underline text-teal-300">Admin Operations</a>
                        <span class="text-slate-600">/</span>
                        <span>Module 03: Unified Classifications</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight mt-1 text-white">
                        Classifications Management
                    </h1>
                    <div class="text-xs font-semibold uppercase tracking-wider text-teal-400 mt-1">
                        Property Taxonomy &amp; Classification Engine
                    </div>
                    <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-2xl">
                        Authoritative national classification system for Ethiopian Real Estate: Manage Principal Property Categories, Types, Commercial Listing Modes, Amenities, and Dynamic Features.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="showAddCategoryModal = true"
                        class="px-4 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-semibold shadow-md transition flex items-center gap-2"
                    >
                        <span>+ Add Property Category</span>
                    </button>
                    <a
                        href="{{ route('listings.create') }}"
                        target="_blank"
                        class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold shadow-md transition flex items-center gap-2"
                    >
                        <span>Preview Posting Wizard ↗</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Notification Alerts -->
        @if(session('success'))
            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2">
                <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm space-y-1">
                <div class="font-bold flex items-center gap-2">
                    <svg class="h-5 w-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>Please correct the following errors:</span>
                </div>
                <ul class="list-disc list-inside text-xs pl-2">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- 8 Navigation Tabs (Section 2 Requirements) -->
        <div class="border-b border-slate-200 overflow-x-auto">
            <nav class="flex space-x-2 pb-px text-xs sm:text-sm font-semibold whitespace-nowrap" aria-label="Classification Sections">
                <button
                    type="button"
                    @click="activeTab = 'residential'"
                    :class="activeTab === 'residential' ? 'border-teal-600 text-teal-700 border-b-2 font-bold bg-teal-50/50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                    class="py-3 px-4 rounded-t-xl transition flex items-center gap-2"
                >
                    <span>🏠</span>
                    <span>1. Residential</span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ $categoryRes?->propertyTypes?->count() ?? 0 }}</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'land'"
                    :class="activeTab === 'land' ? 'border-teal-600 text-teal-700 border-b-2 font-bold bg-teal-50/50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                    class="py-3 px-4 rounded-t-xl transition flex items-center gap-2"
                >
                    <span>🌄</span>
                    <span>2. Land</span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ $categoryLnd?->propertyTypes?->count() ?? 0 }}</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'commercial'"
                    :class="activeTab === 'commercial' ? 'border-teal-600 text-teal-700 border-b-2 font-bold bg-teal-50/50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                    class="py-3 px-4 rounded-t-xl transition flex items-center gap-2"
                >
                    <span>🏢</span>
                    <span>3. Commercial</span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ $categoryCom?->propertyTypes?->count() ?? 0 }}</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'industrial'"
                    :class="activeTab === 'industrial' ? 'border-teal-600 text-teal-700 border-b-2 font-bold bg-teal-50/50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                    class="py-3 px-4 rounded-t-xl transition flex items-center gap-2"
                >
                    <span>🏭</span>
                    <span>4. Industrial</span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ $categoryInd?->propertyTypes?->count() ?? 0 }}</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'hospitality'"
                    :class="activeTab === 'hospitality' ? 'border-teal-600 text-teal-700 border-b-2 font-bold bg-teal-50/50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                    class="py-3 px-4 rounded-t-xl transition flex items-center gap-2"
                >
                    <span>🏨</span>
                    <span>5. Hospitality</span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ $categoryHos?->propertyTypes?->count() ?? 0 }}</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'listing_modes'"
                    :class="activeTab === 'listing_modes' ? 'border-teal-600 text-teal-700 border-b-2 font-bold bg-teal-50/50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                    class="py-3 px-4 rounded-t-xl transition flex items-center gap-2"
                >
                    <span>🏷️</span>
                    <span>6. Listing Modes</span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ count($listingTypes) }}</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'amenities'"
                    :class="activeTab === 'amenities' ? 'border-teal-600 text-teal-700 border-b-2 font-bold bg-teal-50/50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                    class="py-3 px-4 rounded-t-xl transition flex items-center gap-2"
                >
                    <span>⚡</span>
                    <span>7. Amenities</span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ count($amenities) }}</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'features'"
                    :class="activeTab === 'features' ? 'border-teal-600 text-teal-700 border-b-2 font-bold bg-teal-50/50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                    class="py-3 px-4 rounded-t-xl transition flex items-center gap-2"
                >
                    <span>⚙️</span>
                    <span>8. Dynamic Features</span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ count($features) }}</span>
                </button>

                @php
                    $defaultCodes = ['RES', 'LND', 'COM', 'IND', 'HSP'];
                    $customCategories = $categories->reject(fn($c) => in_array($c->code, $defaultCodes));
                @endphp

                @foreach($customCategories as $customCat)
                    <button
                        type="button"
                        @click="activeTab = 'category_{{ $customCat->id }}'"
                        :class="activeTab === 'category_{{ $customCat->id }}' ? 'border-teal-600 text-teal-700 border-b-2 font-bold bg-teal-50/50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                        class="py-3 px-4 rounded-t-xl transition flex items-center gap-2"
                    >
                        <span>{{ $customCat->icon ?: '🏷️' }}</span>
                        <span>{{ $customCat->name_en }}</span>
                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ $customCat->propertyTypes?->count() ?? 0 }}</span>
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Helper Macro / Component for Categories --}}
        @php
            $categorySections = [
                'residential' => ['cat' => $categoryRes, 'title' => 'Residential Classification', 'icon' => '🏠', 'basis' => 'building_area'],
                'land' => ['cat' => $categoryLnd, 'title' => 'Land Classification', 'icon' => '🌄', 'basis' => 'land_area'],
                'commercial' => ['cat' => $categoryCom, 'title' => 'Commercial Classification', 'icon' => '🏢', 'basis' => 'building_area'],
                'industrial' => ['cat' => $categoryInd, 'title' => 'Industrial Classification', 'icon' => '🏭', 'basis' => 'building_area'],
                'hospitality' => ['cat' => $categoryHos, 'title' => 'Hospitality Classification', 'icon' => '🏨', 'basis' => 'building_area'],
            ];
            foreach ($customCategories as $customCat) {
                $categorySections['category_' . $customCat->id] = [
                    'cat' => $customCat,
                    'title' => $customCat->name_en . ' Classification',
                    'icon' => $customCat->icon ?: '🏷️',
                    'basis' => 'building_area',
                ];
            }
        @endphp

        <!-- SECTIONS 1 to 5: PROPERTY CATEGORIES & THEIR PROPERTY TYPES -->
        @foreach($categorySections as $tabKey => $sec)
            @php $currentCat = $sec['cat']; @endphp
            <div x-show="activeTab === '{{ $tabKey }}'" x-cloak class="space-y-6">
                @if($currentCat)
                    <!-- Category Header Card -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <span class="text-3xl p-3 bg-slate-50 border border-slate-100 rounded-2xl">{{ $currentCat->icon ?: $sec['icon'] }}</span>
                            <div>
                                <div class="flex items-center gap-3">
                                    <h2 class="text-lg font-bold text-slate-900">{{ $currentCat->name_en }}</h2>
                                    @if($currentCat->name_am)
                                        <span class="text-xs text-slate-500 font-medium">({{ $currentCat->name_am }})</span>
                                    @endif
                                    <span class="text-xs px-2.5 py-0.5 rounded-full font-medium {{ $currentCat->is_active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $currentCat->is_active ? 'Active Category' : 'Inactive' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-slate-500 mt-1">
                                    <span class="font-mono bg-slate-100 px-2 py-0.5 rounded text-slate-700">Code: {{ $currentCat->code }}</span>
                                    <span>•</span>
                                    <span>Slug: {{ $currentCat->slug }}</span>
                                    <span>•</span>
                                    <span>Order: {{ $currentCat->sort_order }}</span>
                                    <span>•</span>
                                    <span>{{ $currentCat->propertyTypes->count() }} Property Type(s)</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="openEditCategory(@js($currentCat))"
                                class="px-3 py-1.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                            >
                                Edit Category
                            </button>

                            <form method="POST" action="{{ route('admin.taxonomy.categories.toggle', $currentCat->id) }}" class="inline">
                                @csrf
                                <button
                                    type="submit"
                                    class="px-3 py-1.5 rounded-xl border border-slate-300 text-xs font-semibold {{ $currentCat->is_active ? 'text-amber-700 hover:bg-amber-50' : 'text-emerald-700 hover:bg-emerald-50' }} transition"
                                >
                                    {{ $currentCat->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>

                            <button
                                type="button"
                                @click="openAddTypeFor({{ $currentCat->id }}, '{{ $sec['basis'] }}')"
                                class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-sm transition flex items-center gap-1.5"
                            >
                                <span>+ Add {{ $currentCat->name_en }} Type</span>
                            </button>
                        </div>
                    </div>

                    <!-- Property Types Table for this Category -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-slate-900">Valid Property Types in {{ $currentCat->name_en }}</h3>
                            <span class="text-xs text-slate-500">{{ $currentCat->propertyTypes->count() }} registered types</span>
                        </div>

                        @if($currentCat->propertyTypes->isEmpty())
                            <div class="p-8 text-center space-y-3">
                                <p class="text-xs text-slate-500">No property types found for {{ $currentCat->name_en }}.</p>
                                <button
                                    type="button"
                                    @click="openAddTypeFor({{ $currentCat->id }}, '{{ $sec['basis'] }}')"
                                    class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold"
                                >
                                    + Add First Type
                                </button>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-50 text-slate-600 border-b border-slate-200 uppercase tracking-wider text-[11px]">
                                            <th class="py-3 px-6 font-bold">Type Name</th>
                                            <th class="py-3 px-4 font-bold">Code / Slug</th>
                                            <th class="py-3 px-4 font-bold">Area Basis</th>
                                            <th class="py-3 px-4 font-bold">Specs Required</th>
                                            <th class="py-3 px-4 font-bold">Sort Order</th>
                                            <th class="py-3 px-4 font-bold">Status</th>
                                            <th class="py-3 px-6 font-bold text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-slate-800">
                                        @foreach($currentCat->propertyTypes as $pt)
                                            <tr class="hover:bg-slate-50/50 transition">
                                                <td class="py-3.5 px-6">
                                                    <span class="font-bold text-slate-900">{{ $pt->name_en }}</span>
                                                    @if($pt->name_am)
                                                        <span class="text-slate-500 block text-[11px]">{{ $pt->name_am }}</span>
                                                    @endif
                                                </td>
                                                <td class="py-3.5 px-4 font-mono">
                                                    <span class="px-2 py-0.5 rounded bg-slate-100 font-semibold text-slate-800">{{ $pt->code }}</span>
                                                    <span class="text-slate-400 block text-[10px] mt-0.5">{{ $pt->slug }}</span>
                                                </td>
                                                <td class="py-3.5 px-4">
                                                    <span class="px-2 py-0.5 rounded text-[11px] {{ $pt->area_basis === 'land_area' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-blue-50 text-blue-800 border border-blue-200' }}">
                                                        {{ $pt->area_basis === 'land_area' ? 'Land Area (m²)' : 'Building Floor (m²)' }}
                                                    </span>
                                                </td>
                                                <td class="py-3.5 px-4 text-slate-600">
                                                    <span>Beds: {{ $pt->requires_bedrooms ? 'Yes' : 'No' }}</span>
                                                    <span class="text-slate-400">•</span>
                                                    <span>Floors: {{ $pt->requires_floors ? 'Yes' : 'No' }}</span>
                                                </td>
                                                <td class="py-3.5 px-4 font-mono">{{ $pt->sort_order }}</td>
                                                <td class="py-3.5 px-4">
                                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium {{ $pt->is_active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20' : 'bg-slate-100 text-slate-500' }}">
                                                        {{ $pt->is_active ? 'Active' : 'Disabled' }}
                                                    </span>
                                                </td>
                                                <td class="py-3.5 px-6 text-right space-x-2">
                                                    <button
                                                        type="button"
                                                        @click="openEditType(@js($pt))"
                                                        class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-100 font-medium transition"
                                                    >
                                                        Edit
                                                    </button>

                                                    <form method="POST" action="{{ route('admin.taxonomy.property_types.toggle', $pt->id) }}" class="inline">
                                                        @csrf
                                                        <button
                                                            type="submit"
                                                            class="px-2.5 py-1 rounded-lg border border-slate-200 text-xs font-medium {{ $pt->is_active ? 'text-amber-700 hover:bg-amber-50' : 'text-emerald-700 hover:bg-emerald-50' }} transition"
                                                        >
                                                            {{ $pt->is_active ? 'Deactivate' : 'Activate' }}
                                                        </button>
                                                    </form>

                                                    <form method="POST" action="{{ route('admin.taxonomy.property_types.destroy', $pt->id) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete \'{{ $pt->name_en }}\'? If active properties reference it, deletion will be blocked.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button
                                                            type="submit"
                                                            class="px-2.5 py-1 rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50 text-xs font-medium transition"
                                                        >
                                                            Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="p-8 bg-white rounded-2xl border border-slate-200 text-center">
                        <p class="text-sm text-slate-500">Category for {{ $sec['title'] }} not yet initialized.</p>
                    </div>
                @endif
            </div>
        @endforeach

        <!-- SECTION 6: COMMERCIAL LISTING MODES (SRS SECTION 8) -->
        <div x-show="activeTab === 'listing_modes'" x-cloak class="space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Commercial Listing Modes (SRS Section 8)</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Authoritative transaction modes for offers (Sale, Rent, Lease, Short Stay, Investment).</p>
                </div>
                <button
                    type="button"
                    @click="showAddListingTypeModal = true"
                    class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-sm transition flex items-center gap-1.5"
                >
                    <span>+ Add Listing Mode</span>
                </button>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-600 border-b border-slate-200 uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-6 font-bold">Mode Name</th>
                                <th class="py-3 px-4 font-bold">Code / Slug</th>
                                <th class="py-3 px-4 font-bold">Transaction Mode</th>
                                <th class="py-3 px-4 font-bold">Default Price Period</th>
                                <th class="py-3 px-4 font-bold">Sort Order</th>
                                <th class="py-3 px-4 font-bold">Status</th>
                                <th class="py-3 px-6 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-800">
                            @foreach($listingTypes as $lt)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="py-3.5 px-6">
                                        <span class="font-bold text-slate-900">{{ $lt->name_en }}</span>
                                        @if($lt->name_am)
                                            <span class="text-slate-500 block text-[11px]">{{ $lt->name_am }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 font-mono">
                                        <span class="px-2 py-0.5 rounded bg-teal-50 text-teal-900 font-semibold">{{ $lt->code }}</span>
                                        <span class="text-slate-400 block text-[10px] mt-0.5">{{ $lt->slug }}</span>
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-slate-700">{{ $lt->transaction_mode }}</td>
                                    <td class="py-3.5 px-4 text-slate-600 font-mono">{{ $lt->default_price_period }}</td>
                                    <td class="py-3.5 px-4 font-mono">{{ $lt->sort_order }}</td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-medium {{ $lt->is_phase1_enabled ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20' : 'bg-slate-100 text-slate-500' }}">
                                            {{ $lt->is_phase1_enabled ? 'Active / Enabled' : 'Disabled' }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-6 text-right space-x-2">
                                        <button
                                            type="button"
                                            @click="openEditListingType(@js($lt))"
                                            class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-100 font-medium transition"
                                        >
                                            Edit
                                        </button>

                                        <form method="POST" action="{{ route('admin.taxonomy.listing_types.toggle', $lt->id) }}" class="inline">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="px-2.5 py-1 rounded-lg border border-slate-200 text-xs font-medium {{ $lt->is_phase1_enabled ? 'text-amber-700 hover:bg-amber-50' : 'text-emerald-700 hover:bg-emerald-50' }} transition"
                                            >
                                                {{ $lt->is_phase1_enabled ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.taxonomy.listing_types.destroy', $lt->id) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete \'{{ $lt->name_en }}\'? If active listings use this mode, deletion will be safely rejected.');">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="px-2.5 py-1 rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50 text-xs font-medium transition"
                                            >
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SECTION 7: AMENITIES & INFRASTRUCTURE (SRS SECTIONS 16 & 44) -->
        <div x-show="activeTab === 'amenities'" x-cloak class="space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Amenities &amp; Infrastructure (SRS Sections 16 &amp; 44)</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Authoritative property facilities (Electricity, Backup Generator, Water, Parking, Security, Internet).</p>
                </div>
                <button
                    type="button"
                    @click="showAddAmenityModal = true"
                    class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-sm transition flex items-center gap-1.5"
                >
                    <span>+ Add Amenity</span>
                </button>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-600 border-b border-slate-200 uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-6 font-bold">Amenity Name</th>
                                <th class="py-3 px-4 font-bold">Identifier (Slug)</th>
                                <th class="py-3 px-4 font-bold">Category Group</th>
                                <th class="py-3 px-4 font-bold">Icon</th>
                                <th class="py-3 px-4 font-bold">Sort Order</th>
                                <th class="py-3 px-4 font-bold">Status</th>
                                <th class="py-3 px-6 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-800">
                            @foreach($amenities as $am)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="py-3.5 px-6">
                                        <span class="font-bold text-slate-900">{{ $am->name_en }}</span>
                                        @if($am->name_am)
                                            <span class="text-slate-500 block text-[11px]">{{ $am->name_am }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-slate-700">{{ $am->slug }}</td>
                                    <td class="py-3.5 px-4 font-mono">
                                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 font-semibold">{{ $am->group_key }}</span>
                                    </td>
                                    <td class="py-3.5 px-4 text-base">{{ $am->icon ?: '⚡' }}</td>
                                    <td class="py-3.5 px-4 font-mono">{{ $am->sort_order }}</td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-medium {{ $am->is_active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20' : 'bg-slate-100 text-slate-500' }}">
                                            {{ $am->is_active ? 'Active' : 'Disabled' }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-6 text-right space-x-2">
                                        <button
                                            type="button"
                                            @click="openEditAmenity(@js($am))"
                                            class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-100 font-medium transition"
                                        >
                                            Edit
                                        </button>

                                        <form method="POST" action="{{ route('admin.taxonomy.amenities.toggle', $am->id) }}" class="inline">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="px-2.5 py-1 rounded-lg border border-slate-200 text-xs font-medium {{ $am->is_active ? 'text-amber-700 hover:bg-amber-50' : 'text-emerald-700 hover:bg-emerald-50' }} transition"
                                            >
                                                {{ $am->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.taxonomy.amenities.destroy', $am->id) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete \'{{ $am->name_en }}\'? If active properties link this amenity, deletion will be safely rejected.');">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="px-2.5 py-1 rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50 text-xs font-medium transition"
                                            >
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SECTION 8: CATEGORY-SPECIFIC DYNAMIC FEATURES (SRS SECTION 44) -->
        <div x-show="activeTab === 'features'" x-cloak class="space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Category-Specific Dynamic Features (SRS Section 44)</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Configurable specification fields for Categories/Types (Power, Topography, Soil, Zoning, Clearance).</p>
                </div>
                <button
                    type="button"
                    @click="showAddFeatureModal = true"
                    class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-sm transition flex items-center gap-1.5"
                >
                    <span>+ Add Dynamic Feature</span>
                </button>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-600 border-b border-slate-200 uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-6 font-bold">Feature Name</th>
                                <th class="py-3 px-4 font-bold">Slug (Key)</th>
                                <th class="py-3 px-4 font-bold">Input Type</th>
                                <th class="py-3 px-4 font-bold">Unit / Options</th>
                                <th class="py-3 px-4 font-bold">Applicable Categories</th>
                                <th class="py-3 px-4 font-bold">Sort Order</th>
                                <th class="py-3 px-4 font-bold">Status</th>
                                <th class="py-3 px-6 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-800">
                            @foreach($features as $feat)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="py-3.5 px-6">
                                        <span class="font-bold text-slate-900">{{ $feat->name_en }}</span>
                                        @if($feat->name_am)
                                            <span class="text-slate-500 block text-[11px]">{{ $feat->name_am }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-slate-700">{{ $feat->slug }}</td>
                                    <td class="py-3.5 px-4 font-mono font-semibold text-teal-800">{{ $feat->input_type }}</td>
                                    <td class="py-3.5 px-4 text-slate-600">
                                        @if($feat->unit)
                                            <span class="font-semibold text-slate-800">Unit: {{ $feat->unit }}</span>
                                        @endif
                                        @if($feat->options_json)
                                            <span class="block text-[10px] text-slate-500 max-w-xs truncate">Options: {{ implode(', ', $feat->options_json) }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach((array) ($feat->applicable_category_codes ?? []) as $ccode)
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-slate-100 text-slate-800 font-bold">{{ $ccode }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono">{{ $feat->sort_order }}</td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-medium {{ $feat->is_active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20' : 'bg-slate-100 text-slate-500' }}">
                                            {{ $feat->is_active ? 'Active' : 'Disabled' }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-6 text-right space-x-2">
                                        <button
                                            type="button"
                                            @click="openEditFeature(@js($feat))"
                                            class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-100 font-medium transition"
                                        >
                                            Edit
                                        </button>

                                        <form method="POST" action="{{ route('admin.taxonomy.features.toggle', $feat->id) }}" class="inline">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="px-2.5 py-1 rounded-lg border border-slate-200 text-xs font-medium {{ $feat->is_active ? 'text-amber-700 hover:bg-amber-50' : 'text-emerald-700 hover:bg-emerald-50' }} transition"
                                            >
                                                {{ $feat->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.taxonomy.features.destroy', $feat->id) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete \'{{ $feat->name_en }}\'? If active properties store this feature value, deletion will be safely rejected.');">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="px-2.5 py-1 rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50 text-xs font-medium transition"
                                            >
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================================================================= -->
        <!-- ALPINE.JS MODALS FOR ADD & EDIT OPERATIONS -->
        <!-- ========================================================================================= -->

        <!-- 1. MODAL: Add Property Type -->
        <div x-show="showAddTypeModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="showAddTypeModal = false"></div>
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200 space-y-4" @keydown.escape.window="showAddTypeModal = false">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-base font-bold text-slate-900">+ Add New Property Type</h3>
                        <button type="button" @click="showAddTypeModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('admin.taxonomy.property_types.store') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="category_id" x-model="addTypeForm.category_id">

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (English) <span class="text-rose-500">*</span></label>
                                <input type="text" name="name_en" x-model="addTypeForm.name_en" required placeholder="e.g. Duplex Villa" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (Amharic - አማርኛ)</label>
                                <input type="text" name="name_am" x-model="addTypeForm.name_am" placeholder="ለምሳሌ፡ ዱፕሌክስ ቪላ" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Unique Code <span class="text-rose-500">*</span></label>
                                <input type="text" name="code" x-model="addTypeForm.code" required placeholder="e.g. DUPLEX" class="w-full rounded-xl border-slate-300 text-xs uppercase font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Slug</label>
                                <input type="text" name="slug" x-model="addTypeForm.slug" placeholder="e.g. duplex-villa" class="w-full rounded-xl border-slate-300 text-xs font-mono">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Area Basis <span class="text-rose-500">*</span></label>
                                <select name="area_basis" x-model="addTypeForm.area_basis" required class="w-full rounded-xl border-slate-300 text-xs">
                                    <option value="building_area">Building Area (m²)</option>
                                    <option value="land_area">Land Area (m²)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Sort Order</label>
                                <input type="number" name="sort_order" x-model="addTypeForm.sort_order" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="flex items-center gap-6 pt-2">
                            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                <input type="checkbox" name="requires_bedrooms" value="1" x-model="addTypeForm.requires_bedrooms" class="rounded text-teal-700 focus:ring-teal-600">
                                <span>Requires Bedrooms</span>
                            </label>

                            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                <input type="checkbox" name="requires_floors" value="1" x-model="addTypeForm.requires_floors" class="rounded text-teal-700 focus:ring-teal-600">
                                <span>Requires Floors</span>
                            </label>

                            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" x-model="addTypeForm.is_active" class="rounded text-teal-700 focus:ring-teal-600">
                                <span>Active</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showAddTypeModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-sm">Save Property Type</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 2. MODAL: Edit Property Type -->
        <div x-show="showEditTypeModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="showEditTypeModal = false"></div>
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200 space-y-4" @keydown.escape.window="showEditTypeModal = false">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-base font-bold text-slate-900">Edit Property Type</h3>
                        <button type="button" @click="showEditTypeModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                    </div>

                    <form method="POST" :action="'{{ url('/admin/taxonomy/property-types') }}/' + editTypeForm.id" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="category_id" x-model="editTypeForm.category_id">

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (English) <span class="text-rose-500">*</span></label>
                                <input type="text" name="name_en" x-model="editTypeForm.name_en" required class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (Amharic - አማርኛ)</label>
                                <input type="text" name="name_am" x-model="editTypeForm.name_am" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Unique Code <span class="text-rose-500">*</span></label>
                                <input type="text" name="code" x-model="editTypeForm.code" required class="w-full rounded-xl border-slate-300 text-xs uppercase font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Slug</label>
                                <input type="text" name="slug" x-model="editTypeForm.slug" class="w-full rounded-xl border-slate-300 text-xs font-mono">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Area Basis <span class="text-rose-500">*</span></label>
                                <select name="area_basis" x-model="editTypeForm.area_basis" required class="w-full rounded-xl border-slate-300 text-xs">
                                    <option value="building_area">Building Area (m²)</option>
                                    <option value="land_area">Land Area (m²)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Sort Order</label>
                                <input type="number" name="sort_order" x-model="editTypeForm.sort_order" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="flex items-center gap-6 pt-2">
                            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                <input type="checkbox" name="requires_bedrooms" value="1" x-model="editTypeForm.requires_bedrooms" class="rounded text-teal-700 focus:ring-teal-600">
                                <span>Requires Bedrooms</span>
                            </label>

                            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                <input type="checkbox" name="requires_floors" value="1" x-model="editTypeForm.requires_floors" class="rounded text-teal-700 focus:ring-teal-600">
                                <span>Requires Floors</span>
                            </label>

                            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" x-model="editTypeForm.is_active" class="rounded text-teal-700 focus:ring-teal-600">
                                <span>Active</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showEditTypeModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-sm">Update Property Type</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 2B. MODAL: Add Property Category -->
        <div x-show="showAddCategoryModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="showAddCategoryModal = false"></div>
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200 space-y-4" @keydown.escape.window="showAddCategoryModal = false">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-base font-bold text-slate-900">+ Add Property Category</h3>
                        <button type="button" @click="showAddCategoryModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('admin.taxonomy.categories.store') }}" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (English) <span class="text-rose-500">*</span></label>
                                <input type="text" name="name_en" x-model="addCategoryForm.name_en" required placeholder="e.g. Special Purpose" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (Amharic - አማርኛ)</label>
                                <input type="text" name="name_am" x-model="addCategoryForm.name_am" placeholder="ለምሳሌ፡ ልዩ አገልግሎት" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Unique Code <span class="text-rose-500">*</span></label>
                                <input type="text" name="code" x-model="addCategoryForm.code" required placeholder="e.g. SPC" class="w-full rounded-xl border-slate-300 text-xs uppercase font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Slug</label>
                                <input type="text" name="slug" x-model="addCategoryForm.slug" placeholder="e.g. special-purpose" class="w-full rounded-xl border-slate-300 text-xs font-mono">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Display Icon (Emoji or SVG)</label>
                                <input type="text" name="icon" x-model="addCategoryForm.icon" placeholder="e.g. 🏛️" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Sort Order</label>
                                <input type="number" name="sort_order" x-model="addCategoryForm.sort_order" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="pt-2">
                            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" x-model="addCategoryForm.is_active" class="rounded text-teal-700 focus:ring-teal-600">
                                <span>Category Active in Marketplace</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showAddCategoryModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-sm">Save Category</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 3. MODAL: Edit Category Details -->
        <div x-show="showCategoryModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="showCategoryModal = false"></div>
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200 space-y-4" @keydown.escape.window="showCategoryModal = false">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-base font-bold text-slate-900">Edit Classification Category</h3>
                        <button type="button" @click="showCategoryModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                    </div>

                    <form method="POST" :action="'{{ url('/admin/taxonomy/categories') }}/' + categoryForm.id" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (English) <span class="text-rose-500">*</span></label>
                                <input type="text" name="name_en" x-model="categoryForm.name_en" required class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (Amharic - አማርኛ)</label>
                                <input type="text" name="name_am" x-model="categoryForm.name_am" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Display Icon (Emoji or SVG)</label>
                                <input type="text" name="icon" x-model="categoryForm.icon" placeholder="e.g. 🏠" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Sort Order</label>
                                <input type="number" name="sort_order" x-model="categoryForm.sort_order" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="pt-2">
                            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" x-model="categoryForm.is_active" class="rounded text-teal-700 focus:ring-teal-600">
                                <span>Category Active in Marketplace</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showCategoryModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-sm">Update Category</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 4. MODAL: Add Listing Mode -->
        <div x-show="showAddListingTypeModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="showAddListingTypeModal = false"></div>
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200 space-y-4" @keydown.escape.window="showAddListingTypeModal = false">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-base font-bold text-slate-900">+ Add Commercial Listing Mode</h3>
                        <button type="button" @click="showAddListingTypeModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('admin.taxonomy.listing_types.store') }}" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (English) <span class="text-rose-500">*</span></label>
                                <input type="text" name="name_en" x-model="addListingTypeForm.name_en" required placeholder="e.g. Commercial Lease" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (Amharic - አማርኛ)</label>
                                <input type="text" name="name_am" x-model="addListingTypeForm.name_am" placeholder="ለምሳሌ፡ የንግድ ሊዝ" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Code <span class="text-rose-500">*</span></label>
                                <input type="text" name="code" x-model="addListingTypeForm.code" required placeholder="e.g. COM_LEASE" class="w-full rounded-xl border-slate-300 text-xs uppercase font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Transaction Mode <span class="text-rose-500">*</span></label>
                                <select name="transaction_mode" x-model="addListingTypeForm.transaction_mode" required class="w-full rounded-xl border-slate-300 text-xs">
                                    <option value="BUY">BUY (Sale)</option>
                                    <option value="RENT">RENT (Periodic Rental)</option>
                                    <option value="LEASE">LEASE (Commercial Lease)</option>
                                    <option value="STAY">STAY (Short Stay / Hospitality)</option>
                                    <option value="NEW_PROJECTS">NEW_PROJECTS (Development)</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Default Price Period <span class="text-rose-500">*</span></label>
                                <select name="default_price_period" x-model="addListingTypeForm.default_price_period" required class="w-full rounded-xl border-slate-300 text-xs">
                                    <option value="total">Total / One-time</option>
                                    <option value="per_month">Per Month</option>
                                    <option value="per_year">Per Year</option>
                                    <option value="per_night">Per Night</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Sort Order</label>
                                <input type="number" name="sort_order" x-model="addListingTypeForm.sort_order" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="pt-2">
                            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                <input type="checkbox" name="is_phase1_enabled" value="1" x-model="addListingTypeForm.is_phase1_enabled" class="rounded text-teal-700 focus:ring-teal-600">
                                <span>Active &amp; Selectable in Listing Wizard</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showAddListingTypeModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-sm">Save Listing Mode</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 5. MODAL: Edit Listing Mode -->
        <div x-show="showEditListingTypeModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="showEditListingTypeModal = false"></div>
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200 space-y-4" @keydown.escape.window="showEditListingTypeModal = false">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-base font-bold text-slate-900">Edit Commercial Listing Mode</h3>
                        <button type="button" @click="showEditListingTypeModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                    </div>

                    <form method="POST" :action="'{{ url('/admin/taxonomy/listing-types') }}/' + editListingTypeForm.id" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (English) <span class="text-rose-500">*</span></label>
                                <input type="text" name="name_en" x-model="editListingTypeForm.name_en" required class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (Amharic - አማርኛ)</label>
                                <input type="text" name="name_am" x-model="editListingTypeForm.name_am" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Code <span class="text-rose-500">*</span></label>
                                <input type="text" name="code" x-model="editListingTypeForm.code" required class="w-full rounded-xl border-slate-300 text-xs uppercase font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Transaction Mode <span class="text-rose-500">*</span></label>
                                <select name="transaction_mode" x-model="editListingTypeForm.transaction_mode" required class="w-full rounded-xl border-slate-300 text-xs">
                                    <option value="BUY">BUY (Sale)</option>
                                    <option value="RENT">RENT (Periodic Rental)</option>
                                    <option value="LEASE">LEASE (Commercial Lease)</option>
                                    <option value="STAY">STAY (Short Stay / Hospitality)</option>
                                    <option value="NEW_PROJECTS">NEW_PROJECTS (Development)</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Default Price Period <span class="text-rose-500">*</span></label>
                                <select name="default_price_period" x-model="editListingTypeForm.default_price_period" required class="w-full rounded-xl border-slate-300 text-xs">
                                    <option value="total">Total / One-time</option>
                                    <option value="per_month">Per Month</option>
                                    <option value="per_year">Per Year</option>
                                    <option value="per_night">Per Night</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Sort Order</label>
                                <input type="number" name="sort_order" x-model="editListingTypeForm.sort_order" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="pt-2">
                            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                <input type="checkbox" name="is_phase1_enabled" value="1" x-model="editListingTypeForm.is_phase1_enabled" class="rounded text-teal-700 focus:ring-teal-600">
                                <span>Active &amp; Selectable in Listing Wizard</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showEditListingTypeModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-sm">Update Listing Mode</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 6. MODAL: Add Amenity -->
        <div x-show="showAddAmenityModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="showAddAmenityModal = false"></div>
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200 space-y-4" @keydown.escape.window="showAddAmenityModal = false">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-base font-bold text-slate-900">+ Add Amenity &amp; Infrastructure</h3>
                        <button type="button" @click="showAddAmenityModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('admin.taxonomy.amenities.store') }}" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (English) <span class="text-rose-500">*</span></label>
                                <input type="text" name="name_en" x-model="addAmenityForm.name_en" required placeholder="e.g. 24/7 Security" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (Amharic - አማርኛ)</label>
                                <input type="text" name="name_am" x-model="addAmenityForm.name_am" placeholder="ለምሳሌ፡ 24/7 ጥበቃ" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Identifier (Slug) <span class="text-rose-500">*</span></label>
                                <input type="text" name="slug" x-model="addAmenityForm.slug" required placeholder="e.g. security-24-7" class="w-full rounded-xl border-slate-300 text-xs font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Category Group <span class="text-rose-500">*</span></label>
                                <select name="group_key" x-model="addAmenityForm.group_key" required class="w-full rounded-xl border-slate-300 text-xs">
                                    <option value="utilities">Utilities &amp; Power</option>
                                    <option value="security">Security &amp; Safety</option>
                                    <option value="internet">Internet &amp; Telecom</option>
                                    <option value="building">Building &amp; Structure</option>
                                    <option value="lifestyle">Lifestyle &amp; Recreation</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Display Icon</label>
                                <input type="text" name="icon" x-model="addAmenityForm.icon" placeholder="e.g. 🛡️ or ⚡" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Sort Order</label>
                                <input type="number" name="sort_order" x-model="addAmenityForm.sort_order" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="pt-2">
                            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" x-model="addAmenityForm.is_active" class="rounded text-teal-700 focus:ring-teal-600">
                                <span>Active in Property Posting Wizard</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showAddAmenityModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-sm">Save Amenity</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 7. MODAL: Edit Amenity -->
        <div x-show="showEditAmenityModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="showEditAmenityModal = false"></div>
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200 space-y-4" @keydown.escape.window="showEditAmenityModal = false">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-base font-bold text-slate-900">Edit Amenity &amp; Infrastructure</h3>
                        <button type="button" @click="showEditAmenityModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                    </div>

                    <form method="POST" :action="'{{ url('/admin/taxonomy/amenities') }}/' + editAmenityForm.id" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (English) <span class="text-rose-500">*</span></label>
                                <input type="text" name="name_en" x-model="editAmenityForm.name_en" required class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (Amharic - አማርኛ)</label>
                                <input type="text" name="name_am" x-model="editAmenityForm.name_am" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Identifier (Slug) <span class="text-rose-500">*</span></label>
                                <input type="text" name="slug" x-model="editAmenityForm.slug" required class="w-full rounded-xl border-slate-300 text-xs font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Category Group <span class="text-rose-500">*</span></label>
                                <select name="group_key" x-model="editAmenityForm.group_key" required class="w-full rounded-xl border-slate-300 text-xs">
                                    <option value="utilities">Utilities &amp; Power</option>
                                    <option value="security">Security &amp; Safety</option>
                                    <option value="internet">Internet &amp; Telecom</option>
                                    <option value="building">Building &amp; Structure</option>
                                    <option value="lifestyle">Lifestyle &amp; Recreation</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Display Icon</label>
                                <input type="text" name="icon" x-model="editAmenityForm.icon" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Sort Order</label>
                                <input type="number" name="sort_order" x-model="editAmenityForm.sort_order" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="pt-2">
                            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" x-model="editAmenityForm.is_active" class="rounded text-teal-700 focus:ring-teal-600">
                                <span>Active in Property Posting Wizard</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showEditAmenityModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-sm">Update Amenity</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 8. MODAL: Add Category-Specific Dynamic Feature -->
        <div x-show="showAddFeatureModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="showAddFeatureModal = false"></div>
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200 space-y-4" @keydown.escape.window="showAddFeatureModal = false">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-base font-bold text-slate-900">+ Add Dynamic Category Feature</h3>
                        <button type="button" @click="showAddFeatureModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('admin.taxonomy.features.store') }}" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Feature Name (English) <span class="text-rose-500">*</span></label>
                                <input type="text" name="name_en" x-model="addFeatureForm.name_en" required placeholder="e.g. Three-Phase Power" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (Amharic - አማርኛ)</label>
                                <input type="text" name="name_am" x-model="addFeatureForm.name_am" placeholder="ለምሳሌ፡ ባለ 3-ፌዝ መብራት" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Slug (Field Key) <span class="text-rose-500">*</span></label>
                                <input type="text" name="slug" x-model="addFeatureForm.slug" required placeholder="e.g. power_capacity" class="w-full rounded-xl border-slate-300 text-xs font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Input Field Type <span class="text-rose-500">*</span></label>
                                <select name="input_type" x-model="addFeatureForm.input_type" required class="w-full rounded-xl border-slate-300 text-xs">
                                    <option value="text">Text Input</option>
                                    <option value="number">Number (Metric)</option>
                                    <option value="boolean">Boolean (Yes / No)</option>
                                    <option value="select">Single-Select Dropdown</option>
                                    <option value="multi_select">Multi-Select Checkboxes</option>
                                </select>
                            </div>
                        </div>

                        <div x-show="['select', 'multi_select'].includes(addFeatureForm.input_type)">
                            <label class="block text-xs font-medium text-slate-700 mb-1">Select Options (comma-separated)</label>
                            <input type="text" name="options_json" x-model="addFeatureForm.options_json" placeholder="e.g. Flat, Gentle Slope, Steep, Terraced" class="w-full rounded-xl border-slate-300 text-xs">
                            <span class="text-[10px] text-slate-500 mt-0.5 block">Comma-separated allowed option strings.</span>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Unit of Measure (Optional)</label>
                                <input type="text" name="unit" x-model="addFeatureForm.unit" placeholder="e.g. kVA, m, m², years" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Sort Order</label>
                                <input type="number" name="sort_order" x-model="addFeatureForm.sort_order" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1.5">Applicable Categories <span class="text-rose-500">*</span></label>
                            <div class="grid grid-cols-3 gap-2 p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                                @foreach($categories as $cat)
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox" name="applicable_category_codes[]" value="{{ $cat->code }}" x-model="addFeatureForm.applicable_category_codes" class="rounded text-teal-700 focus:ring-teal-600 text-xs">
                                        <span class="font-medium text-slate-800">{{ $cat->name_en }} ({{ $cat->code }})</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="pt-2">
                            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" x-model="addFeatureForm.is_active" class="rounded text-teal-700 focus:ring-teal-600">
                                <span>Active in Wizard Fieldset</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showAddFeatureModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-sm">Save Dynamic Feature</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 9. MODAL: Edit Category-Specific Dynamic Feature -->
        <div x-show="showEditFeatureModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="showEditFeatureModal = false"></div>
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200 space-y-4" @keydown.escape.window="showEditFeatureModal = false">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-base font-bold text-slate-900">Edit Dynamic Category Feature</h3>
                        <button type="button" @click="showEditFeatureModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                    </div>

                    <form method="POST" :action="'{{ url('/admin/taxonomy/features') }}/' + editFeatureForm.id" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Feature Name (English) <span class="text-rose-500">*</span></label>
                                <input type="text" name="name_en" x-model="editFeatureForm.name_en" required class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name (Amharic - አማርኛ)</label>
                                <input type="text" name="name_am" x-model="editFeatureForm.name_am" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Slug (Field Key) <span class="text-rose-500">*</span></label>
                                <input type="text" name="slug" x-model="editFeatureForm.slug" required class="w-full rounded-xl border-slate-300 text-xs font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Input Field Type <span class="text-rose-500">*</span></label>
                                <select name="input_type" x-model="editFeatureForm.input_type" required class="w-full rounded-xl border-slate-300 text-xs">
                                    <option value="text">Text Input</option>
                                    <option value="number">Number (Metric)</option>
                                    <option value="boolean">Boolean (Yes / No)</option>
                                    <option value="select">Single-Select Dropdown</option>
                                    <option value="multi_select">Multi-Select Checkboxes</option>
                                </select>
                            </div>
                        </div>

                        <div x-show="['select', 'multi_select'].includes(editFeatureForm.input_type)">
                            <label class="block text-xs font-medium text-slate-700 mb-1">Select Options (comma-separated)</label>
                            <input type="text" name="options_json" x-model="editFeatureForm.options_json" class="w-full rounded-xl border-slate-300 text-xs">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Unit of Measure (Optional)</label>
                                <input type="text" name="unit" x-model="editFeatureForm.unit" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Sort Order</label>
                                <input type="number" name="sort_order" x-model="editFeatureForm.sort_order" class="w-full rounded-xl border-slate-300 text-xs">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1.5">Applicable Categories <span class="text-rose-500">*</span></label>
                            <div class="grid grid-cols-3 gap-2 p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                                @foreach($categories as $cat)
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox" name="applicable_category_codes[]" value="{{ $cat->code }}" x-model="editFeatureForm.applicable_category_codes" class="rounded text-teal-700 focus:ring-teal-600 text-xs">
                                        <span class="font-medium text-slate-800">{{ $cat->name_en }} ({{ $cat->code }})</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="pt-2">
                            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" x-model="editFeatureForm.is_active" class="rounded text-teal-700 focus:ring-teal-600">
                                <span>Active in Wizard Fieldset</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showEditFeatureModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-sm">Update Dynamic Feature</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
