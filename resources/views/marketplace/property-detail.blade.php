<x-app-layout>
    <x-slot name="title">{{ $property->title_en }} — {{ $property->property_code }} | EthioProperty</x-slot>

    <!-- SEO Schema.org RealEstateListing JSON-LD -->
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "SingleFamilyResidence",
        "name": "{{ addslashes($property->title_en) }}",
        "description": "{{ addslashes($property->description_en ?? '') }}",
        "identifier": "{{ $property->property_code }}",
        "offers": {
            "@@type": "Offer",
            "price": "{{ $listing->price_etb }}",
            "priceCurrency": "ETB",
            "availability": "https://schema.org/InStock"
        },
        @if($property->latitude && $property->longitude)
        "geo": {
            "@@type": "GeoCoordinates",
            "latitude": "{{ $property->latitude }}",
            "longitude": "{{ $property->longitude }}"
        },
        @endif
        "numberOfRooms": {{ $property->bedrooms ?? 1 }}
    }
    </script>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <!-- Breadcrumb & Top Bar -->
        <nav class="flex items-center justify-between text-xs text-slate-500">
            <div class="flex items-center gap-2">
                <a href="{{ url('/search') }}" class="text-teal-700 hover:underline">&larr; Back to Search</a>
                <span>/</span>
                <span>{{ $property->city?->name_en ?? 'Addis Ababa' }}</span>
                <span>/</span>
                <span>{{ $property->subcity?->name_en ?? 'Bole' }}</span>
                <span>/</span>
                <span class="font-mono text-slate-700 font-semibold">{{ $property->property_code }}</span>
            </div>

            <div class="flex items-center gap-2">
                <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 font-medium">
                    {{ ucfirst(strtolower($listing->transaction_mode)) }} ({{ $listing->currency }})
                </span>
                <x-verification.badge :level="$property->verification_level" />
            </div>
        </nav>

        <!-- Header: Title, Location & Price Header -->
        <div class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-start justify-between gap-6">
            <div class="space-y-2 max-w-3xl">
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded bg-teal-50 text-teal-800 text-xs font-semibold uppercase tracking-wider">
                        {{ $property->propertyType?->name_en ?? 'Property' }}
                    </span>
                    <span class="text-xs text-slate-400 font-mono">{{ $property->property_code }}</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 leading-tight">
                    {{ $property->title_en }}
                </h1>
                @if($property->title_am)
                    <p class="text-base text-slate-600 font-medium font-serif">
                        {{ $property->title_am }}
                    </p>
                @endif
                <p class="text-xs text-slate-500 flex items-center gap-1.5 pt-1">
                    <span>📍</span>
                    <span>{{ $property->address_line ?? ($property->subcity?->name_en . ', ' . $property->city?->name_en) }}</span>
                    @if($property->landmark_notes)
                        <span class="text-slate-400">({{ $property->landmark_notes }})</span>
                    @endif
                </p>
            </div>

            <div class="text-left sm:text-right space-y-1">
                <div class="text-xs text-slate-500 font-medium">Asking Price</div>
                <div class="text-2xl sm:text-3xl font-black text-teal-900">
                    {{ \App\Support\Money\EtbFormatter::format($listing->price_etb) }}
                </div>
                @if($listing->price_per_sqm_etb)
                    <div class="text-xs font-semibold text-teal-700">
                        {{ \App\Support\Money\EtbFormatter::format($listing->price_per_sqm_etb) }} / m²
                    </div>
                @endif
                <div class="text-[11px] text-slate-400">
                    {{ $listing->is_negotiable ? 'Price is negotiable' : 'Fixed price' }}
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Left 8 Columns: Photos, Specs, Description & Utilities -->
            <div class="lg:col-span-8 space-y-8">
                <!-- Photo Gallery Grid -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                    @if($property->images->count() > 0)
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            @foreach($property->images->take(6) as $index => $img)
                                <div class="{{ $index === 0 ? 'sm:col-span-2 sm:row-span-2 h-72 sm:h-96' : 'h-44' }} relative rounded-xl overflow-hidden bg-slate-900 group">
                                    <img
                                        src="{{ asset('storage/' . $img->original_sanitized_path) }}"
                                        alt="{{ $img->caption ?? $property->title_en }}"
                                        class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                    >
                                    <span class="absolute bottom-2 left-2 px-2 py-0.5 rounded bg-slate-900/80 text-white text-[10px] uppercase font-semibold">
                                        {{ str_replace('_', ' ', $img->room_tag) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="h-64 rounded-xl bg-slate-100 flex flex-col items-center justify-center text-slate-400 space-y-2">
                            <span class="text-4xl">🏢</span>
                            <span class="text-xs">No photos uploaded for this property</span>
                        </div>
                    @endif
                </div>

                <!-- Physical Specifications Matrix (SRS Section 10) -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider text-teal-800">
                        Physical Specifications & Layout
                    </h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                        <div class="p-3 bg-slate-50 rounded-xl">
                            <span class="text-slate-500 block">Land Area</span>
                            <strong class="text-sm text-slate-900">{{ (float) ($property->land_area_sqm ?? 0) }} m²</strong>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl">
                            <span class="text-slate-500 block">Building Area</span>
                            <strong class="text-sm text-slate-900">{{ (float) ($property->building_area_sqm ?? 0) }} m²</strong>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl">
                            <span class="text-slate-500 block">Bedrooms</span>
                            <strong class="text-sm text-slate-900">{{ $property->bedrooms ?? '—' }}</strong>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl">
                            <span class="text-slate-500 block">Bathrooms</span>
                            <strong class="text-sm text-slate-900">{{ $property->bathrooms ?? '—' }}</strong>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl">
                            <span class="text-slate-500 block">Floors</span>
                            <strong class="text-sm text-slate-900">G+{{ $property->floors ?? '0' }}</strong>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl">
                            <span class="text-slate-500 block">Parking Spaces</span>
                            <strong class="text-sm text-slate-900">{{ $property->parking_spaces }} Cars</strong>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl">
                            <span class="text-slate-500 block">Condition</span>
                            <strong class="text-sm text-slate-900">{{ ucfirst(str_replace('_', ' ', $property->condition)) }}</strong>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl">
                            <span class="text-slate-500 block">Construction</span>
                            <strong class="text-sm text-slate-900">{{ ucfirst(str_replace('_', ' ', $property->construction_status)) }}</strong>
                        </div>
                    </div>
                </div>

                <!-- Utilities & Infrastructure (SRS Section 10) -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider text-teal-800">
                        Critical Utilities & Infrastructure
                    </h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                        <div class="flex items-center gap-2 p-3 rounded-xl border {{ $property->has_generator ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 text-slate-500' }}">
                            <span>⚡</span>
                            <span>Backup Generator: <strong>{{ $property->has_generator ? 'Available' : 'None' }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 text-slate-700">
                            <span>💧</span>
                            <span>Water Supply: <strong>{{ ucfirst($property->water_supply) }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2 p-3 rounded-xl border {{ $property->has_security ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 text-slate-500' }}">
                            <span>🛡️</span>
                            <span>24/7 Security: <strong>{{ $property->has_security ? 'Active' : 'No' }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 text-slate-700">
                            <span>🌐</span>
                            <span>Internet: <strong>{{ ucfirst(str_replace('_', ' ', $property->internet_type)) }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 text-slate-700">
                            <span>🔌</span>
                            <span>Electricity: <strong>{{ ucfirst(str_replace('_', ' ', $property->electricity_type)) }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2 p-3 rounded-xl border {{ $property->has_solar ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 text-slate-500' }}">
                            <span>☀️</span>
                            <span>Solar Power: <strong>{{ $property->has_solar ? 'Installed' : 'No' }}</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Description & Neighborhood Context -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4 leading-relaxed text-sm text-slate-700">
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider text-teal-800">
                        Property Description
                    </h2>
                    <p class="whitespace-pre-line">{{ $property->description_en }}</p>
                    @if($property->description_am)
                        <div class="pt-4 border-t border-slate-100 font-serif text-slate-600">
                            <h3 class="text-xs font-bold text-slate-500 uppercase mb-2">የንብረቱ ዝርዝር መግለጫ (አማርኛ)</h3>
                            <p class="whitespace-pre-line">{{ $property->description_am }}</p>
                        </div>
                    @endif
                </div>

                <!-- Statutory Proclamation Disclaimer (SRS Section 177) -->
                <div class="bg-slate-900 text-slate-200 p-5 rounded-2xl text-xs leading-relaxed space-y-2 border border-slate-800">
                    <div class="flex items-center gap-2 text-emerald-400 font-semibold">
                        <span>⚖️</span>
                        <span>Ethiopian Real Estate Proclamation No. 1357/2024 Compliance</span>
                    </div>
                    <p>
                        All listings on EthioProperty operate under national platform consumer transparency rules.
                        Platform verification badges certify that platform documentation review protocols have been carried out and do NOT constitute government land-registry title deed certification.
                    </p>
                </div>
            </div>

            <!-- Right 4 Columns: Seller Card, Location Pin & Action Vault -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Seller / Publisher Contact Card -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-5 sticky top-24">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-teal-700 text-white font-bold flex items-center justify-center text-base">
                            {{ substr($property->owner->name, 0, 1) }}
                        </div>
                        <div>
                            <div class="text-sm font-bold text-slate-900">{{ $property->owner->name }}</div>
                            <div class="text-xs text-slate-500">Property Owner / Seller</div>
                        </div>
                    </div>

                    <div class="space-y-3 pt-2">
                        @if($listing->phone_privacy_override !== 'hide_phone')
                            <a
                                href="tel:{{ $property->owner->phone }}"
                                class="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-sm font-semibold shadow-xs transition"
                            >
                                <span>📞</span>
                                <span>Call Seller: {{ $property->owner->phone }}</span>
                            </a>
                        @endif

                        <button
                            type="button"
                            @click="window.dispatchEvent(new CustomEvent('notify', { detail: { message: 'Inquiry message request initiated with seller.' } }))"
                            class="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-xs transition"
                        >
                            <span>💬</span>
                            <span>Direct Platform Message</span>
                        </button>
                    </div>

                    <!-- Private Document Vault Status -->
                    <div class="pt-4 border-t border-slate-100 text-xs text-slate-600 space-y-2">
                        <div class="font-semibold text-slate-800 flex items-center gap-1.5">
                            <span>🔒</span>
                            <span>Private Document Vault</span>
                        </div>
                        <p class="text-[11px] text-slate-500">
                            Ownership deeds and site plans are securely vaulted on encrypted storage and available upon verified transaction request.
                        </p>
                    </div>

                    <!-- Report Listing Button (SRS Section 117) -->
                    <div class="pt-3 border-t border-slate-100 text-center">
                        <button
                            type="button"
                            @click="window.dispatchEvent(new CustomEvent('notify', { detail: { message: 'Property report dialog opened.', type: 'info' } }))"
                            class="text-xs text-slate-400 hover:text-red-600 underline"
                        >
                            Report this property (scam / wrong price / duplicate)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
