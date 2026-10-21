<x-app-layout>
    <div
        x-data="{
            step: 1,
            draftSavedAt: null,
            form: {
                use_existing_property: false,
                existing_property_id: '',
                transaction_mode: 'BUY',
                listing_type_id: '1',
                category_code: 'RES',
                property_type_code: 'VILLA',
                area_basis: 'land_area',
                title_en: '',
                title_am: '',
                description_en: '',
                region_id: '',
                city_id: '',
                subcity_id: '',
                neighborhood_id: '',
                latitude: 8.9894,
                longitude: 38.7890,
                land_area_sqm: 300,
                building_area_sqm: 240,
                bedrooms: 4,
                bathrooms: 3,
                floors: 2,
                parking_spaces: 2,
                condition: 'excellent',
                has_generator: true,
                has_solar: false,
                has_security: true,
                amenities: ['wifi', 'backup-generator', 'water-tank'],
                photos_count: 5,
                documents_count: 1,
                price_etb: 20000000,
                price_period: 'total',
                is_negotiable: true,
                phone_privacy_override: 'show_phone'
            },

            init() {
                const saved = localStorage.getItem('ep_listing_wizard_draft_v1');
                if (saved) {
                    try {
                        this.form = { ...this.form, ...JSON.parse(saved) };
                        this.draftSavedAt = 'Restored from offline draft';
                    } catch (e) {}
                }
                this.$watch('form', (val) => {
                    localStorage.setItem('ep_listing_wizard_draft_v1', JSON.stringify(val));
                    this.draftSavedAt = new Date().toLocaleTimeString();
                }, { deep: true });
            },

            clearDraft() {
                localStorage.removeItem('ep_listing_wizard_draft_v1');
                this.draftSavedAt = 'Cleared';
            },

            get activeAreaSqm() {
                if (this.form.category_code === 'LND' || this.form.area_basis === 'land_area') {
                    return Number(this.form.land_area_sqm) || Number(this.form.building_area_sqm) || 0;
                }
                return Number(this.form.building_area_sqm) || Number(this.form.land_area_sqm) || 0;
            },

            get pricePerSqm() {
                const area = this.activeAreaSqm;
                const price = Number(this.form.price_etb) || 0;
                if (area <= 0 || price <= 0) return 0;
                return (price / area).toFixed(2);
            },

            get qualityScore() {
                let s = 0;
                if (this.form.photos_count >= 5) s += 25;
                else if (this.form.photos_count >= 1) s += 10;
                if ((this.form.description_en || '').trim().length >= 120) s += 20;
                else if ((this.form.description_en || '').trim().length >= 40) s += 10;
                if (this.form.latitude && this.form.longitude) s += 15;
                if (Number(this.form.price_etb) > 0 && this.activeAreaSqm > 0) s += 15;
                if (this.form.amenities.length >= 3) s += 15;
                if (this.form.documents_count >= 1) s += 10;
                return Math.min(100, s);
            }
        }"
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
    >
        <!-- Header & Offline Draft Status Banner -->
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-teal-700">
                    Property Core & Marketplace Wizard
                </span>
                <h1 class="text-2xl font-bold text-slate-900">Post a Property & Create Listing</h1>
                <p class="text-xs text-slate-500 mt-0.5">Strict asset separation: register the physical property once, attach multiple offers.</p>
            </div>

            <div class="flex items-center gap-3 text-xs">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span>Offline Draft Auto-Save: <strong x-text="draftSavedAt || 'Active'"></strong></span>
                </span>
                <button type="button" @click="clearDraft()" class="text-slate-500 hover:text-red-600 underline">
                    Reset Draft
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Left 8 Columns: 5-Step Wizard Form -->
            <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
                <!-- Step Progress Bar -->
                <div class="grid grid-cols-5 gap-2 mb-8">
                    @foreach([1 => '1. Mode & Type', 2 => '2. Location & GIS', 3 => '3. Specs & Utilities', 4 => '4. Media & Vault', 5 => '5. Price & Publish'] as $sNum => $sLabel)
                        <button
                            type="button"
                            @click="step = {{ $sNum }}"
                            :class="step === {{ $sNum }} ? 'border-teal-700 bg-teal-50 text-teal-900 font-bold' : (step > {{ $sNum }} ? 'border-emerald-500 bg-emerald-50/50 text-emerald-800' : 'border-slate-200 text-slate-500')"
                            class="border-b-2 pb-2 pt-1 px-2 text-left text-xs rounded-t-lg transition"
                        >
                            {{ $sLabel }}
                        </button>
                    @endforeach
                </div>

                <form method="POST" action="{{ url('/listings') }}" enctype="multipart/form-data">
                    @csrf

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
                            <input type="text" x-model="form.existing_property_id" name="existing_property_id" placeholder="Enter Property Code or ID" class="w-full rounded-xl border-slate-300 text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-800 mb-2">Transaction Mode (SRS Section 8)</label>
                            <div class="grid grid-cols-3 gap-3">
                                <button type="button" @click="form.transaction_mode = 'BUY'; form.price_period = 'total'" :class="form.transaction_mode === 'BUY' ? 'border-teal-700 bg-teal-50 text-teal-900 font-bold ring-2 ring-teal-600' : 'border-slate-200 hover:border-slate-300'" class="border rounded-xl p-3 text-sm text-center transition">
                                    🏠 For Sale (BUY)
                                </button>
                                <button type="button" @click="form.transaction_mode = 'RENT'; form.price_period = 'per_month'" :class="form.transaction_mode === 'RENT' ? 'border-teal-700 bg-teal-50 text-teal-900 font-bold ring-2 ring-teal-600' : 'border-slate-200 hover:border-slate-300'" class="border rounded-xl p-3 text-sm text-center transition">
                                    🔑 For Rent (RENT)
                                </button>
                                <button type="button" @click="form.transaction_mode = 'LEASE'; form.price_period = 'per_year'" :class="form.transaction_mode === 'LEASE' ? 'border-teal-700 bg-teal-50 text-teal-900 font-bold ring-2 ring-teal-600' : 'border-slate-200 hover:border-slate-300'" class="border rounded-xl p-3 text-sm text-center transition">
                                    📜 For Lease (LEASE)
                                </button>
                            </div>
                            <input type="hidden" name="transaction_mode" :value="form.transaction_mode">
                            <input type="hidden" name="listing_type_id" :value="form.listing_type_id">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Property Category (SRS Section 6)</label>
                                <select x-model="form.category_code" name="category_code" class="w-full rounded-xl border-slate-300 text-sm">
                                    <option value="RES">Residential (መኖሪያ ቤት)</option>
                                    <option value="LND">Land (መሬት / ቦታ)</option>
                                    <option value="COM">Commercial (የንግድ ንብረት)</option>
                                    <option value="IND">Industrial (ኢንዱስትሪ)</option>
                                    <option value="HSP">Hospitality (እንግዳ ተቀባይነት)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Price/m² Area Calculation Basis</label>
                                <select x-model="form.area_basis" name="area_basis" class="w-full rounded-xl border-slate-300 text-sm">
                                    <option value="land_area">Land Area (m²) — Villas / Compounds / Land</option>
                                    <option value="building_area">Building Floor Area (m²) — Apartments / Offices</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Property Title (English)</label>
                            <input type="text" x-model="form.title_en" name="title_en" placeholder="e.g. Modern 4-Bedroom G+2 Villa in Bole Medhanialem" class="w-full rounded-xl border-slate-300 text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Property Title (Amharic - አማርኛ)</label>
                            <input type="text" x-model="form.title_am" name="title_am" placeholder="ለምሳሌ፡ ዘመናዊ ባለ 4 መኝታ ቪላ በቦሌ መድኃኒዓለም" class="w-full rounded-xl border-slate-300 text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Property Description</label>
                            <textarea rows="4" x-model="form.description_en" name="description_en" placeholder="Describe compound size, asphalt road access, reserve water tanker capacity, backup generator, and neighborhood landmarks..." class="w-full rounded-xl border-slate-300 text-sm"></textarea>
                            <span class="text-[11px] text-slate-500 mt-1 block">Min. 120 characters recommended for high Listing Quality Score.</span>
                        </div>
                    </div>

                    <!-- STEP 2: Location & PostGIS Pin -->
                    <div x-show="step === 2" x-cloak class="space-y-6">
                        <x-location.cascading-picker />
                        <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Latitude (WGS84)</label>
                                <input type="number" step="0.0000001" x-model="form.latitude" name="latitude" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Longitude (WGS84)</label>
                                <input type="number" step="0.0000001" x-model="form.longitude" name="longitude" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
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

                        <div>
                            <label class="block text-xs font-semibold text-slate-800 mb-2">Amenities & Infrastructure</label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                                <label class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50">
                                    <input type="checkbox" value="wifi" x-model="form.amenities" class="rounded text-teal-700 focus:ring-teal-600">
                                    <span>Wi-Fi / Broadband</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50">
                                    <input type="checkbox" value="backup-generator" x-model="form.amenities" class="rounded text-teal-700 focus:ring-teal-600">
                                    <span>Backup Generator</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50">
                                    <input type="checkbox" value="water-tank" x-model="form.amenities" class="rounded text-teal-700 focus:ring-teal-600">
                                    <span>Reserve Water Tanker</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50">
                                    <input type="checkbox" value="24h-security" x-model="form.amenities" class="rounded text-teal-700 focus:ring-teal-600">
                                    <span>24/7 Compound Security</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50">
                                    <input type="checkbox" value="elevator" x-model="form.amenities" class="rounded text-teal-700 focus:ring-teal-600">
                                    <span>Elevator / Lift</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50">
                                    <input type="checkbox" value="solar-power" x-model="form.amenities" class="rounded text-teal-700 focus:ring-teal-600">
                                    <span>Solar Power / Inverter</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4: Media (EXIF-Stripped) & Private Document Vault -->
                    <div x-show="step === 4" x-cloak class="space-y-6">
                        <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 leading-relaxed">
                            <strong>Photo Guidance (SRS Section 201 & 202):</strong> Upload clear photos of the Exterior, Living Room, Bedrooms, Kitchen, and Bathroom. Sensitive GPS/EXIF metadata is automatically stripped on server ingestion for your physical privacy.
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-800 mb-2">Upload Property Photos (WebP / AVIF processed)</label>
                            <input type="file" name="photos[]" multiple accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100">
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
                            <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                        </div>
                    </div>

                    <!-- STEP 5: Price Engine & Phone Privacy -->
                    <div x-show="step === 5" x-cloak class="space-y-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-800 mb-1">Asking Price in ETB (ብር)</label>
                                <input type="number" x-model="form.price_etb" name="price_etb" class="w-full rounded-xl border-slate-300 text-lg font-bold text-teal-900">
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
                                <select x-model="form.is_negotiable" name="is_negotiable" class="w-full rounded-xl border-slate-300 text-sm">
                                    <option :value="true">Yes, price is negotiable</option>
                                    <option :value="false">Fixed price (non-negotiable)</option>
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
                                @click="step++"
                                class="px-5 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-sm font-semibold shadow-sm transition"
                            >
                                Next Step →
                            </button>
                            <button
                                type="submit"
                                x-show="step === 5"
                                class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-sm transition"
                            >
                                Submit Listing for Moderation
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
                            <span>Photos (5+ Recommended)</span>
                            <span x-text="form.photos_count >= 5 ? '✓ Complete' : '△ Incomplete'" :class="form.photos_count >= 5 ? 'text-emerald-700 font-semibold' : 'text-amber-600'"></span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span>Detailed Description (120+ chars)</span>
                            <span x-text="(form.description_en || '').length >= 120 ? '✓ Complete' : '△ Incomplete'" :class="(form.description_en || '').length >= 120 ? 'text-emerald-700 font-semibold' : 'text-amber-600'"></span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span>Map Pin & Location Hierarchy</span>
                            <span class="text-emerald-700 font-semibold">✓ Complete</span>
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
                            <span x-text="form.documents_count >= 1 ? '✓ Uploaded' : '△ Optional'" :class="form.documents_count >= 1 ? 'text-emerald-700 font-semibold' : 'text-slate-400'"></span>
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
