<x-app-layout>
    <div
        x-data="{
            step: 1,
            draftSavedAt: null,
            storageKey: 'ep_wizard_draft_u{{ auth()->id() ?? 'guest' }}_v3',
            categories: @js($categories),
            featuresList: @js($features),
            hasErrors: {{ $errors->any() ? 'true' : 'false' }},
            submitting: false,
            photosCount: 0,
            documentsCount: 0,
            form: {
                use_existing_property: {{ old('use_existing_property', $initialUseExisting ?? false) ? 'true' : 'false' }},
                existing_property_id: @js(old('existing_property_id', $initialExistingPropertyId ?? '')),
                transaction_mode: @js(old('transaction_mode', $initialTransactionMode ?? 'BUY')),
                listing_type_id: @js(old('listing_type_id', '1')),
                category_code: @js(old('category_code', $initialCategoryCode ?? '')),
                property_type_code: @js(old('property_type_code', '')),
                area_basis: @js(old('area_basis', ($initialType ?? '') === 'land' ? 'land_area' : 'building_area')),
                title_en: @js(old('title_en', '')),
                title_am: @js(old('title_am', '')),
                description_en: @js(old('description_en', '')),
                region_id: @js(old('region_id', '')),
                city_id: @js(old('city_id', '')),
                subcity_id: @js(old('subcity_id', '')),
                neighborhood_id: @js(old('neighborhood_id', '')),
                latitude: @js(old('latitude', 8.9894)),
                longitude: @js(old('longitude', 38.7890)),
                land_area_sqm: @js(old('land_area_sqm', '')),
                building_area_sqm: @js(old('building_area_sqm', '')),
                bedrooms: @js(old('bedrooms', '')),
                bathrooms: @js(old('bathrooms', '')),
                floors: @js(old('floors', '')),
                parking_spaces: @js(old('parking_spaces', 0)),
                amenities: @js(old('amenities', [])),
                features: @js(old('features', new \stdClass())),
                price_etb: @js(old('price_etb', '')),
                price_period: @js(old('price_period', 'total')),
                is_negotiable: @js((string) ((old('is_negotiable', '1') === '0' || old('is_negotiable', '1') === false || old('is_negotiable', '1') === 0 || old('is_negotiable', '1') === 'false') ? '0' : '1')),
                phone_privacy_override: @js(old('phone_privacy_override', 'show_phone')),
                submit_action: 'moderation'
            },

            init() {
                // If there are server validation errors, keep old input and do not overwrite with stale local draft
                if (sessionStorage.getItem('wizard_submitted_success')) {
                    localStorage.removeItem(this.storageKey);
                    sessionStorage.removeItem('wizard_submitted_success');
                } else if (!this.hasErrors) {
                    const saved = localStorage.getItem(this.storageKey);
                    if (saved) {
                        try {
                            const parsed = JSON.parse(saved);
                            const isExpired = parsed.timestamp && (Date.now() - parsed.timestamp > 7 * 24 * 60 * 60 * 1000);
                            if (isExpired) {
                                localStorage.removeItem(this.storageKey);
                            } else if (parsed && typeof parsed === 'object' && parsed.data) {
                                this.form = { ...this.form, ...parsed.data };
                                this.draftSavedAt = parsed.savedAt ? 'Restored (' + parsed.savedAt + ')' : 'Restored from local draft';
                            }
                        } catch (e) {
                            localStorage.removeItem(this.storageKey);
                        }
                    }
                }

                // If default category is not set, pick the first active category
                if (!this.form.category_code && this.categories.length > 0) {
                    this.form.category_code = this.categories[0].code;
                    this.onCategoryChange();
                }

                this.$watch('form', (val) => {
                    const payload = {
                        version: 3,
                        timestamp: Date.now(),
                        savedAt: new Date().toLocaleTimeString(),
                        data: {
                            use_existing_property: val.use_existing_property,
                            existing_property_id: val.existing_property_id,
                            transaction_mode: val.transaction_mode,
                            listing_type_id: val.listing_type_id,
                            category_code: val.category_code,
                            property_type_code: val.property_type_code,
                            area_basis: val.area_basis,
                            title_en: val.title_en,
                            title_am: val.title_am,
                            description_en: val.description_en,
                            region_id: val.region_id,
                            city_id: val.city_id,
                            subcity_id: val.subcity_id,
                            neighborhood_id: val.neighborhood_id,
                            latitude: val.latitude,
                            longitude: val.longitude,
                            land_area_sqm: val.land_area_sqm,
                            building_area_sqm: val.building_area_sqm,
                            bedrooms: val.bedrooms,
                            bathrooms: val.bathrooms,
                            floors: val.floors,
                            parking_spaces: val.parking_spaces,
                            amenities: val.amenities,
                            features: val.features,
                            price_etb: val.price_etb,
                            price_period: val.price_period,
                            is_negotiable: val.is_negotiable,
                            phone_privacy_override: val.phone_privacy_override
                        }
                    };
                    localStorage.setItem(this.storageKey, JSON.stringify(payload));
                    this.draftSavedAt = payload.savedAt;
                }, { deep: true });
            },

            clearDraft() {
                localStorage.removeItem(this.storageKey);
                this.draftSavedAt = 'Cleared';
                window.location.reload();
            },

            get currentCategory() {
                return this.categories.find(c => c.code === this.form.category_code) || null;
            },

            get currentPropertyTypes() {
                const cat = this.currentCategory;
                if (!cat) return [];
                return cat.property_types || cat.propertyTypes || [];
            },

            get availableFeatures() {
                if (!this.form.category_code) return [];
                return this.featuresList.filter(f => {
                    const cats = f.applicable_category_codes || [];
                    return cats.length === 0 || cats.includes(this.form.category_code);
                });
            },

            onCategoryChange() {
                const types = this.currentPropertyTypes;
                const matches = types.some(t => t.code === this.form.property_type_code);
                if (!matches && types.length > 0) {
                    this.form.property_type_code = types[0].code;
                } else if (types.length === 0) {
                    this.form.property_type_code = '';
                }
                this.updateAreaBasis();

                // Prune dynamic feature values that do not apply to the new category
                const activeSlugs = this.availableFeatures.map(f => f.slug);
                if (this.form.features && typeof this.form.features === 'object') {
                    for (const k of Object.keys(this.form.features)) {
                        if (!activeSlugs.includes(k)) {
                            delete this.form.features[k];
                        }
                    }
                }
            },

            onTypeChange() {
                this.updateAreaBasis();
            },

            updateAreaBasis() {
                if (this.form.category_code === 'LND') {
                    this.form.area_basis = 'land_area';
                    return;
                }
                const currentType = this.currentPropertyTypes.find(t => t.code === this.form.property_type_code);
                if (currentType && currentType.area_basis) {
                    this.form.area_basis = currentType.area_basis;
                }
            },

            get activeAreaSqm() {
                if (this.form.area_basis === 'land_area' || this.form.category_code === 'LND') {
                    return Number(this.form.land_area_sqm) || 0;
                }
                return Number(this.form.building_area_sqm) || 0;
            },

            get pricePerSqm() {
                const area = this.activeAreaSqm;
                const price = Number(this.form.price_etb) || 0;
                if (area <= 0 || price <= 0) return 0;
                return (price / area).toFixed(2);
            },

            get hasValidLocation() {
                return Boolean(this.form.latitude && this.form.longitude && (this.form.city_id || this.form.region_id));
            },

            onPhotosChange(e) {
                this.photosCount = e.target.files ? e.target.files.length : 0;
            },

            onDocumentsChange(e) {
                this.documentsCount = e.target.files ? e.target.files.length : 0;
            },

            canAdvanceFromStep(s) {
                if (s === 1) {
                    if (this.form.use_existing_property) {
                        return Boolean(this.form.existing_property_id);
                    }
                    return Boolean(this.form.title_en && this.form.title_en.trim() && this.form.category_code && this.form.property_type_code);
                }
                if (s === 2) {
                    return Boolean(this.form.latitude && this.form.longitude);
                }
                return true;
            },

            nextStep() {
                if (!this.canAdvanceFromStep(this.step)) {
                    alert('Please complete the required fields in this step before proceeding.');
                    return;
                }
                if (this.step < 5) this.step++;
            },

            goToStep(target) {
                if (target > this.step) {
                    for (let s = this.step; s < target; s++) {
                        if (!this.canAdvanceFromStep(s)) {
                            alert('Please complete step ' + s + ' before advancing.');
                            return;
                        }
                    }
                }
                this.step = target;
            },

            get qualityScore() {
                let s = 0;
                // 1. Photos (20 pts for 5+ photos, +5 pts for tags / variety)
                if (this.photosCount >= 5) {
                    s += 25;
                } else if (this.photosCount >= 1) {
                    s += 10;
                }

                // 2. Description (20 pts for 120+ chars)
                const descLen = (this.form.description_en || '').trim().length;
                if (descLen >= 120) s += 20;
                else if (descLen >= 40) s += 10;

                // 3. Location (15 pts: dual coordinates and hierarchy)
                if (this.hasValidLocation) s += 15;

                // 4. Price & Authoritative Area (15 pts)
                if (Number(this.form.price_etb) > 0 && this.activeAreaSqm > 0) s += 15;

                // 5. Amenities (15 pts for 3+)
                if (this.form.amenities.length >= 3) s += 15;

                // 6. Supporting Documents (10 pts bonus)
                if (this.documentsCount >= 1) s += 10;

                return Math.min(100, s);
            }
        }"
        @location-hierarchy-changed.window="form.region_id = $event.detail.regionId; form.city_id = $event.detail.cityId; form.subcity_id = $event.detail.subcityId; form.neighborhood_id = $event.detail.neighborhoodId"
        @location-selected.window="form.latitude = $event.detail.lat; form.longitude = $event.detail.lng"
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
    >
        <!-- Header & Offline Draft Status Banner -->
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-teal-700">
                    Property Core & Marketplace Wizard
                </span>
                <h1 class="text-2xl font-bold text-slate-900">Post a Property & Create Listing</h1>
                <p class="text-xs text-slate-500 mt-0.5">Strict asset separation: register the physical property once, attach multiple commercial offers.</p>
            </div>

            <div class="flex items-center gap-3 text-xs">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span>Draft Status: <strong x-text="draftSavedAt || 'Auto-Saved Locally'"></strong></span>
                </span>
                <button type="button" @click="clearDraft()" class="text-slate-500 hover:text-red-600 underline">
                    Reset Draft
                </button>
            </div>
        </div>

        @if($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm">
                <div class="font-bold mb-1">Please fix the following submission errors:</div>
                <ul class="list-disc list-inside space-y-1 text-xs">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Left 8 Columns: 5-Step Wizard Form -->
            <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
                <!-- Step Progress Bar -->
                <div class="grid grid-cols-5 gap-2 mb-8">
                    @foreach([1 => '1. Mode & Type', 2 => '2. Location & GIS', 3 => '3. Specs & Utilities', 4 => '4. Media & Vault', 5 => '5. Price & Publish'] as $sNum => $sLabel)
                        <button
                            type="button"
                            @click="goToStep({{ $sNum }})"
                            :class="step === {{ $sNum }} ? 'border-teal-700 bg-teal-50 text-teal-900 font-bold' : (step > {{ $sNum }} ? 'border-emerald-500 bg-emerald-50/50 text-emerald-800' : 'border-slate-200 text-slate-500')"
                            class="border-b-2 pb-2 pt-1 px-2 text-left text-xs rounded-t-lg transition"
                        >
                            {{ $sLabel }}
                        </button>
                    @endforeach
                </div>

                <form method="POST" action="{{ url('/listings') }}" enctype="multipart/form-data" @submit="submitting = true">
                    @csrf
                    <input type="hidden" name="submit_action" x-model="form.submit_action">

                    <!-- STEP 1: Transaction Mode & Property Category -->
                    <div x-show="step === 1" class="space-y-6">
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Already registered this physical property?</p>
                                <p class="text-xs text-slate-600">Attach a new Sale, Rent, or Lease listing to an existing Property ID (e.g. ETH-AA-BOLE-RES-00001245).</p>
                            </div>
                            <input type="checkbox" x-model="form.use_existing_property" name="use_existing_property" value="1" class="rounded border-slate-300 text-teal-700 focus:ring-teal-600 h-5 w-5">
                        </div>

                        <div x-show="form.use_existing_property" x-cloak class="p-4 rounded-xl bg-teal-50 border border-teal-200">
                            <label class="block text-xs font-semibold text-teal-900 mb-1">Select Registered Property</label>
                            @if(isset($userProperties) && count($userProperties) > 0)
                                <select x-model="form.existing_property_id" name="existing_property_id" class="w-full rounded-xl border-slate-300 text-sm">
                                    <option value="">Select one of your registered properties...</option>
                                    @foreach($userProperties as $prop)
                                        <option value="{{ $prop->id }}">[{{ $prop->property_code }}] {{ $prop->title_en }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="text" x-model="form.existing_property_id" name="existing_property_id" placeholder="Enter Property Code or UUID" class="w-full rounded-xl border-slate-300 text-sm">
                                <span class="text-xs text-slate-500 mt-1 block">You currently have no registered properties; enter an authorized property code or uncheck this box to register a new one.</span>
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-800 mb-2">Transaction Mode (SRS Section 8)</label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                <button type="button" @click="form.transaction_mode = 'BUY'; form.price_period = 'total'" :class="form.transaction_mode === 'BUY' ? 'border-teal-700 bg-teal-50 text-teal-900 font-bold ring-2 ring-teal-600' : 'border-slate-200 hover:border-slate-300'" class="border rounded-xl p-2.5 sm:p-3 text-xs sm:text-sm text-center transition">
                                    🏠 For Sale (BUY)
                                </button>
                                <button type="button" @click="form.transaction_mode = 'RENT'; form.price_period = 'per_month'" :class="form.transaction_mode === 'RENT' ? 'border-teal-700 bg-teal-50 text-teal-900 font-bold ring-2 ring-teal-600' : 'border-slate-200 hover:border-slate-300'" class="border rounded-xl p-2.5 sm:p-3 text-xs sm:text-sm text-center transition">
                                    🔑 For Rent (RENT)
                                </button>
                                <button type="button" @click="form.transaction_mode = 'LEASE'; form.price_period = 'per_year'" :class="form.transaction_mode === 'LEASE' ? 'border-teal-700 bg-teal-50 text-teal-900 font-bold ring-2 ring-teal-600' : 'border-slate-200 hover:border-slate-300'" class="border rounded-xl p-2.5 sm:p-3 text-xs sm:text-sm text-center transition">
                                    📜 For Lease (LEASE)
                                </button>
                                <button type="button" @click="form.transaction_mode = 'STAY'; form.price_period = 'per_night'" :class="form.transaction_mode === 'STAY' ? 'border-teal-700 bg-teal-50 text-teal-900 font-bold ring-2 ring-teal-600' : 'border-slate-200 hover:border-slate-300'" class="border rounded-xl p-2.5 sm:p-3 text-xs sm:text-sm text-center transition">
                                    🏨 Short-Stay (STAY)
                                </button>
                            </div>
                            <input type="hidden" name="transaction_mode" :value="form.transaction_mode">
                            <input type="hidden" name="listing_type_id" :value="form.listing_type_id">
                        </div>

                        <!-- Land Plot Notice -->
                        <div x-show="form.category_code === 'LND'" x-cloak class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 flex items-start gap-2.5">
                            <span class="text-base">🌄</span>
                            <div class="space-y-0.5">
                                <p class="font-bold">Land &amp; Plot Listing Mode Active</p>
                                <p class="text-[11px] text-amber-800 leading-relaxed">Bedrooms, bathrooms, and residential room counts are automatically omitted. Authoritative Land Area (m²), zoning, and topography specs will be configured in Step 3.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Property Category <span class="text-rose-500">*</span></label>
                                <select x-model="form.category_code" @change="onCategoryChange()" name="category_code" class="w-full rounded-xl border-slate-300 text-sm" required>
                                    <option value="">Select Category...</option>
                                    <template x-for="cat in categories" :key="cat.code">
                                        <option :value="cat.code" x-text="cat.name_en + (cat.name_am ? ' (' + cat.name_am + ')' : '')"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Property Type <span class="text-rose-500">*</span></label>
                                <select x-model="form.property_type_code" @change="onTypeChange()" name="property_type_code" class="w-full rounded-xl border-slate-300 text-sm" required :disabled="!currentPropertyTypes.length">
                                    <option value="">Select Property Type...</option>
                                    <template x-for="t in currentPropertyTypes" :key="t.code">
                                        <option :value="t.code" x-text="t.name_en + (t.name_am ? ' (' + t.name_am + ')' : '')"></option>
                                    </template>
                                </select>
                                <span x-show="!currentPropertyTypes.length" class="text-xs text-slate-400 mt-1 block">Please select a category first.</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Price/m² Area Basis</label>
                                <select x-model="form.area_basis" name="area_basis" class="w-full rounded-xl border-slate-300 text-sm">
                                    <option value="building_area">Building Floor Area (m²) — Apartments / Offices</option>
                                    <option value="land_area">Land Area (m²) — Villas / Compounds / Land</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Title (Amharic - አማርኛ)</label>
                                <input type="text" x-model="form.title_am" name="title_am" placeholder="ለምሳሌ፡ ዘመናዊ ባለ 4 መኝታ ቪላ በቦሌ" class="w-full rounded-xl border-slate-300 text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Property Title (English) <span class="text-rose-500">*</span></label>
                            <input type="text" x-model="form.title_en" name="title_en" placeholder="e.g. Modern 4-Bedroom G+2 Villa in Bole Medhanialem" class="w-full rounded-xl border-slate-300 text-sm" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Property Description</label>
                            <textarea rows="4" x-model="form.description_en" name="description_en" placeholder="Describe compound size, asphalt road access, reserve water tanker capacity, backup generator, and neighborhood landmarks..." class="w-full rounded-xl border-slate-300 text-sm"></textarea>
                            <span class="text-[11px] text-slate-500 mt-1 block">Min. 120 characters recommended for complete Listing Quality Score.</span>
                        </div>
                    </div>

                    <!-- STEP 2: Location & PostGIS Pin -->
                    <div x-show="step === 2" x-cloak class="space-y-6">
                        <x-location.cascading-picker :include-coordinates="false" />
                        <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Latitude (WGS84)</label>
                                <input type="number" step="0.0000001" x-model="form.latitude" name="latitude" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Longitude (WGS84)</label>
                                <input type="number" step="0.0000001" x-model="form.longitude" name="longitude" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3: Physical Specifications & Utilities -->
                    <div x-show="step === 3" x-cloak class="space-y-6">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Land Area (m²)</label>
                                <input type="number" x-model="form.land_area_sqm" name="land_area_sqm" class="w-full rounded-xl border-slate-300 text-sm">
                            </div>
                            <div x-show="form.category_code !== 'LND'">
                                <label class="block text-xs font-medium text-slate-700 mb-1">Building Area (m²)</label>
                                <input type="number" x-model="form.building_area_sqm" name="building_area_sqm" class="w-full rounded-xl border-slate-300 text-sm">
                            </div>
                            <div x-show="form.category_code === 'RES' || form.category_code === 'HSP'">
                                <label class="block text-xs font-medium text-slate-700 mb-1">Bedrooms</label>
                                <input type="number" x-model="form.bedrooms" name="bedrooms" class="w-full rounded-xl border-slate-300 text-sm">
                            </div>
                            <div x-show="form.category_code !== 'LND'">
                                <label class="block text-xs font-medium text-slate-700 mb-1">Bathrooms</label>
                                <input type="number" x-model="form.bathrooms" name="bathrooms" class="w-full rounded-xl border-slate-300 text-sm">
                            </div>
                            <div x-show="form.category_code !== 'LND'">
                                <label class="block text-xs font-medium text-slate-700 mb-1">Floors (G+)</label>
                                <input type="number" x-model="form.floors" name="floors" class="w-full rounded-xl border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Parking Spaces</label>
                                <input type="number" x-model="form.parking_spaces" name="parking_spaces" class="w-full rounded-xl border-slate-300 text-sm">
                            </div>
                        </div>

                        <!-- Dynamic Category Features (SRS Section 44) -->
                        <div class="pt-4 border-t border-slate-100" x-show="availableFeatures.length > 0">
                            <label class="block text-xs font-semibold text-slate-800 mb-2">Category-Specific Features</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <template x-for="feat in availableFeatures" :key="feat.slug">
                                    <div>
                                        <label class="block text-xs text-slate-600 mb-1" x-text="feat.name_en + (feat.unit ? ' (' + feat.unit + ')' : '')"></label>
                                        <template x-if="feat.input_type === 'select'">
                                            <select :name="'features[' + feat.slug + ']'" x-model="form.features[feat.slug]" class="w-full rounded-xl border-slate-300 text-xs">
                                                <option value="">Select option...</option>
                                                <template x-for="opt in (feat.options_json || [])" :key="opt">
                                                    <option :value="opt" x-text="opt"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="feat.input_type === 'number'">
                                            <input type="number" :name="'features[' + feat.slug + ']'" x-model="form.features[feat.slug]" class="w-full rounded-xl border-slate-300 text-xs">
                                        </template>
                                        <template x-if="feat.input_type === 'text'">
                                            <input type="text" :name="'features[' + feat.slug + ']'" x-model="form.features[feat.slug]" class="w-full rounded-xl border-slate-300 text-xs">
                                        </template>
                                        <template x-if="feat.input_type === 'boolean'">
                                            <select :name="'features[' + feat.slug + ']'" x-model="form.features[feat.slug]" class="w-full rounded-xl border-slate-300 text-xs">
                                                <option value="">Select...</option>
                                                <option value="1">Yes</option>
                                                <option value="0">No</option>
                                            </select>
                                        </template>
                                        <template x-if="feat.input_type === 'multi_select'">
                                            <div class="space-y-1.5 p-2 rounded-xl bg-slate-50 border border-slate-200">
                                                <template x-for="opt in (feat.options_json || [])" :key="opt">
                                                    <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                                        <input type="checkbox" :name="'features[' + feat.slug + '][]'" :value="opt"
                                                            :checked="Array.isArray(form.features[feat.slug]) ? form.features[feat.slug].includes(opt) : false"
                                                            @change="
                                                                if (!Array.isArray(form.features[feat.slug])) { form.features[feat.slug] = []; }
                                                                if ($event.target.checked) {
                                                                    if (!form.features[feat.slug].includes(opt)) form.features[feat.slug].push(opt);
                                                                } else {
                                                                    form.features[feat.slug] = form.features[feat.slug].filter(v => v !== opt);
                                                                }
                                                            "
                                                            class="rounded text-teal-700 focus:ring-teal-600 text-xs">
                                                        <span x-text="opt"></span>
                                                    </label>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-800 mb-2">Amenities & Infrastructure</label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                                @foreach($amenities as $am)
                                    <label class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50">
                                        <input type="checkbox" value="{{ $am->slug }}" x-model="form.amenities" name="amenities[]" class="rounded text-teal-700 focus:ring-teal-600">
                                        <span>{{ $am->name_en }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4: Media (EXIF-Stripped) & Private Document Vault -->
                    <div x-show="step === 4" x-cloak class="space-y-6">
                        <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 leading-relaxed">
                            <strong>Photo Guidance (SRS Section 201 & 202):</strong> Upload clear photos of the Exterior, Living Room, Bedrooms, Kitchen, and Compound. Sensitive GPS/EXIF metadata is automatically stripped on server ingestion for your privacy.
                            <div class="mt-2 text-amber-800 font-medium">
                                <em>Security note: Image and document files are not stored in local browser cache; please select them before submission.</em>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-800 mb-2">Upload Property Photos (WebP / AVIF processed)</label>
                            <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" @change="onPhotosChange($event)" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100">
                            <span x-show="photosCount > 0" class="text-xs text-teal-700 mt-1 block font-medium" x-text="photosCount + ' photo(s) selected'"></span>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-900 text-slate-100 text-xs leading-relaxed space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="text-base">🔒</span>
                                <strong class="text-emerald-400">Private Document Vault (SRS Section 28 & 29)</strong>
                            </div>
                            <p>
                                Uploaded ownership deeds (Carta), site plans, and identification cards default strictly to <code class="text-amber-300">Verification-Only</code> access on encrypted private storage.
                                Documents are never publicly downloadable and are only accessible via 5-minute cryptographic signed URLs with immutable audit logging.
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-800 mb-2">Supporting Documents (Carta / Title Deed / Lease Contract)</label>
                            <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" @change="onDocumentsChange($event)" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                            <span x-show="documentsCount > 0" class="text-xs text-teal-700 mt-1 block font-medium" x-text="documentsCount + ' document(s) selected'"></span>
                        </div>
                    </div>

                    <!-- STEP 5: Price Engine & Phone Privacy -->
                    <div x-show="step === 5" x-cloak class="space-y-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-800 mb-1">Asking Price in ETB (ብር) <span class="text-rose-500">*</span></label>
                                <input type="number" x-model="form.price_etb" name="price_etb" class="w-full rounded-xl border-slate-300 text-lg font-bold text-teal-900" required>
                            </div>
                            <div class="bg-teal-50 border border-teal-200 rounded-xl p-4 flex flex-col justify-center">
                                <span class="text-xs font-medium text-teal-800">Auto-Calculated Price per m² (SRS Section 36)</span>
                                <span class="text-xl font-bold text-teal-950 mt-0.5">
                                    ETB <span x-text="Number(pricePerSqm).toLocaleString()"></span> / m²
                                </span>
                                <span class="text-[11px] text-teal-700">Based on <span x-text="activeAreaSqm"></span> m² (<span x-text="form.area_basis"></span>)</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Price Period</label>
                                <select x-model="form.price_period" name="price_period" class="w-full rounded-xl border-slate-300 text-sm">
                                    <option value="total">Total Price (One-time)</option>
                                    <option value="per_month">Per Month</option>
                                    <option value="per_year">Per Year</option>
                                    <option value="per_night">Per Night</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Negotiable</label>
                                <input type="hidden" name="is_negotiable" value="0">
                                <select x-model="form.is_negotiable" name="is_negotiable" class="w-full rounded-xl border-slate-300 text-sm">
                                    <option value="1">Yes, price is negotiable</option>
                                    <option value="0">Fixed price (non-negotiable)</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Phone Privacy Preference (SRS Section 75)</label>
                            <select x-model="form.phone_privacy_override" name="phone_privacy_override" class="w-full rounded-xl border-slate-300 text-sm">
                                <option value="show_phone">Show Phone Number (Protected by anti-scraper rate limit)</option>
                                <option value="hide_phone">Hide Phone Number</option>
                                <option value="messaging_only">Platform Messaging Only</option>
                            </select>
                        </div>
                    </div>

                    <!-- Wizard Footer Navigation -->
                    <div class="mt-8 pt-5 border-t border-slate-200 flex items-center justify-between">
                        <button
                            type="button"
                            x-show="step > 1"
                            @click="step--"
                            class="px-4 py-2.5 rounded-xl border border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
                        >
                            ← Previous Step
                        </button>
                        <div class="ml-auto flex items-center gap-3">
                            <button
                                type="button"
                                x-show="step < 5"
                                @click="nextStep()"
                                class="px-5 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-sm font-semibold shadow-sm transition"
                            >
                                Next Step →
                            </button>
                            <button
                                type="submit"
                                x-show="step === 5"
                                :disabled="submitting"
                                :class="submitting ? 'opacity-50 cursor-not-allowed' : ''"
                                @click="form.submit_action = 'draft'"
                                class="px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-semibold shadow-sm transition"
                            >
                                <span x-show="!submitting">Save as Draft</span>
                                <span x-show="submitting">Saving...</span>
                            </button>
                            <button
                                type="submit"
                                x-show="step === 5"
                                :disabled="submitting"
                                :class="submitting ? 'opacity-50 cursor-not-allowed' : ''"
                                @click="form.submit_action = 'moderation'"
                                class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-sm transition"
                            >
                                <span x-show="!submitting">Submit Listing for Moderation</span>
                                <span x-show="submitting">Submitting...</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Right 4 Columns: Live Listing Quality Score Assistant (SRS Sections 165 & 203) -->
            <div class="lg:col-span-4 space-y-4">
                <div class="bg-white rounded-2xl border border-slate-200 p-6 sticky top-24 shadow-sm">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-bold text-slate-900">Listing Quality Score</h3>
                        <span class="text-lg font-extrabold text-teal-700"><span x-text="qualityScore"></span>%</span>
                    </div>

                    <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden mb-4">
                        <div class="h-full bg-teal-600 transition-all duration-300" :style="`width: ${qualityScore}%`"></div>
                    </div>

                    <ul class="space-y-2.5 text-xs text-slate-700">
                        <li class="flex items-center justify-between">
                            <span>Photos (5+ Photos)</span>
                            <span x-text="photosCount >= 5 ? '✓ Complete' : '△ Incomplete'" :class="photosCount >= 5 ? 'text-emerald-700 font-semibold' : 'text-amber-600'"></span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span>Detailed Description (120+ chars)</span>
                            <span x-text="(form.description_en || '').length >= 120 ? '✓ Complete' : '△ Incomplete'" :class="(form.description_en || '').length >= 120 ? 'text-emerald-700 font-semibold' : 'text-amber-600'"></span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span>Map Pin & Location Hierarchy</span>
                            <span x-text="hasValidLocation ? '✓ Complete' : '△ Incomplete'" :class="hasValidLocation ? 'text-emerald-700 font-semibold' : 'text-amber-600'"></span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span>Price & Area (ETB/m² Ready)</span>
                            <span x-text="pricePerSqm > 0 ? '✓ Complete' : '△ Incomplete'" :class="pricePerSqm > 0 ? 'text-emerald-700 font-semibold' : 'text-amber-600'"></span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span>Amenities Selected (3+)</span>
                            <span x-text="form.amenities.length >= 3 ? '✓ Complete' : '△ Incomplete'" :class="form.amenities.length >= 3 ? 'text-emerald-700 font-semibold' : 'text-amber-600'"></span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span>Supporting Vault Documents</span>
                            <span x-text="documentsCount >= 1 ? '✓ Uploaded' : '△ Optional'" :class="documentsCount >= 1 ? 'text-emerald-700 font-semibold' : 'text-slate-400'"></span>
                        </li>
                    </ul>

                    <p class="mt-4 pt-3 border-t border-slate-100 text-[11px] text-slate-500">
                        Note: Listing completeness score measures advertisement quality and is separate from legal or identity verification (SRS Section 165).
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
