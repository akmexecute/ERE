<x-app-layout>
    <x-slot name="title">{{ $property->title_en }} — {{ $property->property_code }} | EthioProperty</x-slot>

    @php
        $canonicalUrl = url('/listings/' . $listing->slug);
        $metaDescription = mb_substr(strip_tags($property->description_en ?: ($property->title_en . ' located in ' . ($property->subcity?->name_en ?? 'Addis Ababa') . ', Ethiopia.')), 0, 160);
        $coverImage = $property->images->firstWhere('is_cover', true) ?? $property->images->first();
        $coverImageUrl = $coverImage ? asset('storage/' . $coverImage->original_sanitized_path) : null;

        $schemaData = [
            '@context' => 'https://schema.org',
            '@type' => 'RealEstateListing',
            'name' => $property->title_en,
            'description' => $metaDescription,
            'url' => $canonicalUrl,
            'identifier' => $property->property_code,
            'offers' => [
                '@type' => 'Offer',
                'price' => (string) $listing->price_etb,
                'priceCurrency' => 'ETB',
                'availability' => 'https://schema.org/InStock',
            ],
            'numberOfRooms' => (int) ($property->bedrooms ?? 1),
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $property->subcity?->name_en ?? 'Addis Ababa',
                'addressRegion' => $property->city?->name_en ?? 'Addis Ababa',
                'addressCountry' => 'ET',
            ],
        ];

        if ($property->latitude && $property->longitude) {
            $schemaData['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $property->latitude,
                'longitude' => (float) $property->longitude,
            ];
        }

        if ($coverImageUrl) {
            $schemaData['image'] = $coverImageUrl;
        }
    @endphp

    <x-slot name="meta">
        <meta name="description" content="{{ $metaDescription }}">
        <link rel="canonical" href="{{ $canonicalUrl }}">

        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:title" content="{{ $property->title_en }} — {{ $property->property_code }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        @if($coverImageUrl)
            <meta property="og:image" content="{{ $coverImageUrl }}">
        @endif

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:url" content="{{ $canonicalUrl }}">
        <meta name="twitter:title" content="{{ $property->title_en }} — {{ $property->property_code }}">
        <meta name="twitter:description" content="{{ $metaDescription }}">
        @if($coverImageUrl)
            <meta name="twitter:image" content="{{ $coverImageUrl }}">
        @endif

        <!-- JSON-LD Structured Data (SRS Section 18) -->
        <script type="application/ld+json">
            {!! json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}
        </script>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <!-- Breadcrumb & Top Bar -->
        <nav class="flex items-center justify-between text-xs text-slate-500">
            <div class="flex items-center gap-2">
                <a href="{{ url('/search') }}" class="text-teal-700 hover:underline">{{ __('marketplace.details.back_to_search') }}</a>
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
                <div class="text-xs text-slate-500 font-medium">{{ __('marketplace.details.asking_price') }}</div>
                <div class="text-2xl sm:text-3xl font-black text-teal-900">
                    {{ \App\Support\Money\EtbFormatter::format($listing->price_etb) }}
                </div>
                @if($listing->price_per_sqm_etb)
                    <div class="text-xs font-semibold text-teal-700">
                        {{ \App\Support\Money\EtbFormatter::format($listing->price_per_sqm_etb) }} / m²
                    </div>
                @endif
                <div class="text-[11px] text-slate-400">
                    {{ $listing->is_negotiable ? __('marketplace.details.price_negotiable') : __('marketplace.details.fixed_price') }}
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
                            <span class="text-xs">{{ __('marketplace.details.no_photos') }}</span>
                        </div>
                    @endif
                </div>

                <!-- 360° Interactive Virtual Tour (Module 34.1) -->
                @if($property->virtualTour && $property->virtualTour->is_published)
                    <x-virtual-tour-viewer :tour="$property->virtualTour" />
                @endif

                <!-- Physical Specifications Matrix (SRS Section 10) -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider text-teal-800">
                        {{ __('marketplace.details.specs_title') }}
                    </h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                        <div class="p-3 bg-slate-50 rounded-xl">
                            <span class="text-slate-500 block">{{ __('marketplace.details.land_area') }}</span>
                            <strong class="text-sm text-slate-900">{{ (float) ($property->land_area_sqm ?? 0) }} m²</strong>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl">
                            <span class="text-slate-500 block">{{ __('marketplace.details.building_area') }}</span>
                            <strong class="text-sm text-slate-900">{{ (float) ($property->building_area_sqm ?? 0) }} m²</strong>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl">
                            <span class="text-slate-500 block">{{ __('marketplace.details.bedrooms') }}</span>
                            <strong class="text-sm text-slate-900">{{ $property->bedrooms ?? '—' }}</strong>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl">
                            <span class="text-slate-500 block">{{ __('marketplace.details.bathrooms') }}</span>
                            <strong class="text-sm text-slate-900">{{ $property->bathrooms ?? '—' }}</strong>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl">
                            <span class="text-slate-500 block">{{ __('marketplace.details.floors') }}</span>
                            <strong class="text-sm text-slate-900">G+{{ $property->floors ?? '0' }}</strong>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl">
                            <span class="text-slate-500 block">{{ __('marketplace.details.parking_spaces') }}</span>
                            <strong class="text-sm text-slate-900">{{ $property->parking_spaces }} {{ __('marketplace.details.cars') }}</strong>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl">
                            <span class="text-slate-500 block">{{ __('marketplace.details.condition') }}</span>
                            <strong class="text-sm text-slate-900">{{ ucfirst(str_replace('_', ' ', $property->condition)) }}</strong>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl">
                            <span class="text-slate-500 block">{{ __('marketplace.details.construction') }}</span>
                            <strong class="text-sm text-slate-900">{{ ucfirst(str_replace('_', ' ', $property->construction_status)) }}</strong>
                        </div>
                    </div>
                </div>

                <!-- Utilities & Infrastructure (SRS Section 10) -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider text-teal-800">
                        {{ __('marketplace.details.utilities_title') }}
                    </h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                        <div class="flex items-center gap-2 p-3 rounded-xl border {{ $property->has_generator ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 text-slate-500' }}">
                            <span>⚡</span>
                            <span>{{ __('marketplace.details.generator') }}: <strong>{{ $property->has_generator ? __('marketplace.details.available') : __('marketplace.details.none') }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 text-slate-700">
                            <span>💧</span>
                            <span>{{ __('marketplace.details.water') }}: <strong>{{ ucfirst($property->water_supply) }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2 p-3 rounded-xl border {{ $property->has_security ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 text-slate-500' }}">
                            <span>🛡️</span>
                            <span>{{ __('marketplace.details.security') }}: <strong>{{ $property->has_security ? __('marketplace.details.active') : __('marketplace.details.none') }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 text-slate-700">
                            <span>🌐</span>
                            <span>{{ __('marketplace.details.internet') }}: <strong>{{ ucfirst(str_replace('_', ' ', $property->internet_type)) }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 text-slate-700">
                            <span>🔌</span>
                            <span>{{ __('marketplace.details.electricity') }}: <strong>{{ ucfirst(str_replace('_', ' ', $property->electricity_type)) }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2 p-3 rounded-xl border {{ $property->has_solar ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 text-slate-500' }}">
                            <span>☀️</span>
                            <span>{{ __('marketplace.details.solar') }}: <strong>{{ $property->has_solar ? __('marketplace.details.installed') : __('marketplace.details.none') }}</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Description & Neighborhood Context -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4 leading-relaxed text-sm text-slate-700">
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider text-teal-800">
                        {{ __('marketplace.details.description_title') }}
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
                        <span>{{ __('marketplace.details.proclamation_title') }}</span>
                    </div>
                    <p>
                        {{ __('marketplace.details.proclamation_desc') }}
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
                            <div class="text-xs text-slate-500">{{ __('marketplace.details.seller_title') }}</div>
                        </div>
                    </div>

                    <!-- Seller Contact & Engagement Controls (SRS Sections 73-75 & Module 10) -->
                    <div x-data="{
                        revealedPhone: null,
                        revealing: false,
                        privacyNotice: null,
                        inquiryModalOpen: false,
                        priceAlertModalOpen: false,
                        async revealPhone() {
                            this.revealing = true;
                            this.privacyNotice = null;
                            try {
                                const res = await fetch('{{ route('listings.reveal-phone', $listing->id) }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    }
                                });
                                const data = await res.json();
                                if (res.ok && data.phone) {
                                    this.revealedPhone = data.phone;
                                } else {
                                    this.privacyNotice = data.message || 'Phone number is hidden by seller privacy settings.';
                                }
                            } catch (e) {
                                this.privacyNotice = 'Unable to reveal contact phone at this moment.';
                            } finally {
                                this.revealing = false;
                            }
                        }
                    }" class="space-y-3 pt-2">

                        <!-- Authorized Phone Reveal Button (SRS Section 75 - Fail-Closed & Audited) -->
                        <template x-if="!revealedPhone">
                            <div>
                                <button
                                    type="button"
                                    @click="revealPhone()"
                                    :disabled="revealing"
                                    class="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-sm font-semibold shadow-xs transition disabled:opacity-50"
                                >
                                    <span>📞</span>
                                    <span x-text="revealing ? 'Authorizing...' : 'Reveal Seller Phone'"></span>
                                </button>
                                <template x-if="privacyNotice">
                                    <div class="mt-2 p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-[11px] leading-tight" x-text="privacyNotice"></div>
                                </template>
                            </div>
                        </template>

                        <template x-if="revealedPhone">
                            <a
                                :href="'tel:' + revealedPhone"
                                class="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-sm font-semibold shadow-xs transition"
                            >
                                <span>📞</span>
                                <span x-text="'Call ' + revealedPhone"></span>
                            </a>
                        </template>

                        <!-- Direct Message / Inquiry Button -->
                        <button
                            type="button"
                            @click="inquiryModalOpen = true"
                            class="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-xs transition"
                        >
                            <span>💬</span>
                            <span>{{ __('marketplace.details.direct_message') }}</span>
                        </button>

                        <!-- Favorite & Price Alert Quick Actions -->
                        <div class="grid grid-cols-2 gap-2 pt-1">
                            @auth
                                @php
                                    $isFavorited = auth()->user()->favorites()->where('listing_id', $listing->id)->exists();
                                @endphp
                                @if($isFavorited)
                                    <form method="POST" action="{{ route('favorites.destroy', $listing->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-full py-2 px-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold hover:bg-rose-100 flex items-center justify-center gap-1.5 transition">
                                            <span>❤️</span>
                                            <span>Saved</span>
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('favorites.store') }}">
                                        @csrf
                                        <input type="hidden" name="listing_id" value="{{ $listing->id }}">
                                        <button type="submit" class="w-full py-2 px-3 rounded-lg bg-slate-50 border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-100 flex items-center justify-center gap-1.5 transition">
                                            <span>🤍</span>
                                            <span>Favorite</span>
                                        </button>
                                    </form>
                                @endif
                            @else
                                <a href="{{ route('login') }}" class="w-full py-2 px-3 rounded-lg bg-slate-50 border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-100 flex items-center justify-center gap-1.5 transition">
                                    <span>🤍</span>
                                    <span>Favorite</span>
                                </a>
                            @endauth

                            <a href="{{ route('marketplace.compare', ['ids' => [$property->id]]) }}" class="w-full py-2 px-3 rounded-lg bg-slate-50 border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-100 flex items-center justify-center gap-1.5 transition text-center">
                                <span>⚖️</span>
                                <span>Compare</span>
                            </a>
                        </div>

                        <!-- Price & Availability Alert Trigger -->
                        <button
                            type="button"
                            @click="priceAlertModalOpen = true"
                            class="w-full py-2 px-3 rounded-lg bg-sky-50 border border-sky-200 text-sky-800 text-xs font-medium hover:bg-sky-100 flex items-center justify-center gap-1.5 transition"
                        >
                            <span>🔔</span>
                            <span>Get Price &amp; Status Alerts</span>
                        </button>

                        <!-- Inquiry Interactive Modal -->
                        <div
                            x-show="inquiryModalOpen"
                            x-cloak
                            @keydown.escape.window="inquiryModalOpen = false"
                            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
                        >
                            <div
                                @click.away="inquiryModalOpen = false"
                                class="bg-white rounded-2xl max-w-lg w-full p-6 text-left shadow-2xl border border-slate-200 space-y-4"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-base font-bold text-slate-900 flex items-center gap-1.5">
                                            <span>💬</span>
                                            <span>Contact Seller / Property Inquiry</span>
                                        </h3>
                                        <p class="text-xs text-slate-500 mt-0.5">Regarding {{ $property->title_en }} ({{ $property->property_code }})</p>
                                    </div>
                                    <button type="button" @click="inquiryModalOpen = false" class="text-slate-400 hover:text-slate-700 text-lg">✕</button>
                                </div>

                                @auth
                                    <form method="POST" action="{{ route('inquiries.store') }}" class="space-y-4 text-xs">
                                        @csrf
                                        <input type="hidden" name="listing_id" value="{{ $listing->id }}">
                                        <input type="hidden" name="lead_source" value="website">

                                        <div>
                                            <label class="block font-semibold text-slate-700 mb-1">Your Message or Question</label>
                                            <textarea
                                                name="initial_message"
                                                rows="4"
                                                required
                                                minlength="5"
                                                maxlength="2000"
                                                placeholder="Selam! I am interested in this property. Is it available for viewing this week?"
                                                class="w-full rounded-xl border-slate-300 text-xs p-3 focus:border-teal-700 focus:ring-teal-700"
                                            ></textarea>
                                        </div>

                                        <div class="flex items-center justify-end gap-2 pt-2">
                                            <button type="button" @click="inquiryModalOpen = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold">
                                                Cancel
                                            </button>
                                            <button type="submit" class="px-5 py-2 rounded-xl bg-teal-800 hover:bg-teal-900 text-white font-semibold">
                                                Send Inquiry
                                            </button>
                                        </div>
                                    </form>
                                @else
                                    <div class="text-center py-6 space-y-3">
                                        <p class="text-sm text-slate-600">Please sign in to send inquiries and message the verified seller directly.</p>
                                        <a href="{{ route('login') }}" class="inline-block px-5 py-2.5 rounded-xl bg-teal-800 text-white text-xs font-bold hover:bg-teal-900">
                                            Sign In to Inquire
                                        </a>
                                    </div>
                                @endauth
                            </div>
                        </div>

                        <!-- Price Alert Interactive Modal -->
                        <div
                            x-show="priceAlertModalOpen"
                            x-cloak
                            @keydown.escape.window="priceAlertModalOpen = false"
                            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
                        >
                            <div
                                @click.away="priceAlertModalOpen = false"
                                class="bg-white rounded-2xl max-w-md w-full p-6 text-left shadow-2xl border border-slate-200 space-y-4"
                            >
                                <div class="flex items-center justify-between">
                                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-1.5">
                                        <span>🔔</span>
                                        <span>Track Price &amp; Status</span>
                                    </h3>
                                    <button type="button" @click="priceAlertModalOpen = false" class="text-slate-400 hover:text-slate-700 text-lg">✕</button>
                                </div>
                                <p class="text-xs text-slate-500">
                                    Current Asking Price: <strong class="text-slate-900">{{ \App\Support\Money\EtbFormatter::format($listing->price_etb) }}</strong>
                                </p>

                                @auth
                                    <form method="POST" action="{{ route('price-alerts.store') }}" class="space-y-4 text-xs">
                                        @csrf
                                        <input type="hidden" name="listing_id" value="{{ $listing->id }}">

                                        <div class="space-y-2">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" name="notify_price_drop" value="1" checked class="rounded border-slate-300 text-teal-700 focus:ring-teal-700">
                                                <span class="text-slate-700">Notify me immediately if the price drops</span>
                                            </label>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" name="notify_status_change" value="1" checked class="rounded border-slate-300 text-teal-700 focus:ring-teal-700">
                                                <span class="text-slate-700">Notify me if the property status changes (e.g. sold, reserved)</span>
                                            </label>
                                        </div>

                                        <div class="flex items-center justify-end gap-2 pt-2">
                                            <button type="button" @click="priceAlertModalOpen = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold">
                                                Cancel
                                            </button>
                                            <button type="submit" class="px-5 py-2 rounded-xl bg-teal-800 hover:bg-teal-900 text-white font-semibold">
                                                Activate Alert
                                            </button>
                                        </div>
                                    </form>
                                @else
                                    <div class="text-center py-4 space-y-3">
                                        <p class="text-sm text-slate-600">Please sign in to set price alerts.</p>
                                        <a href="{{ route('login') }}" class="inline-block px-5 py-2.5 rounded-xl bg-teal-800 text-white text-xs font-bold hover:bg-teal-900">
                                            Sign In
                                        </a>
                                    </div>
                                @endauth
                            </div>
                        </div>

                        @if(in_array(strtoupper((string) $listing->transaction_mode), ['RENT', 'LEASE']))
                            <a
                                href="{{ route('rental-applications.create', ['property_id' => $property->id, 'listing_id' => $listing->id]) }}"
                                class="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl bg-purple-700 hover:bg-purple-800 text-white text-sm font-semibold shadow-xs transition"
                            >
                                <span>📝</span>
                                <span>Apply to Rent / Lease</span>
                            </a>
                        @endif
                    </div>

                    <!-- Private Document Vault Status -->
                    <div class="pt-4 border-t border-slate-100 text-xs text-slate-600 space-y-2">
                        <div class="font-semibold text-slate-800 flex items-center gap-1.5">
                            <span>🔒</span>
                            <span>{{ __('marketplace.details.vault_title') }}</span>
                        </div>
                        <p class="text-[11px] text-slate-500">
                            {{ __('marketplace.details.vault_desc') }}
                        </p>
                    </div>

                    <!-- Report Listing Button (SRS Section 117) -->
                    <div x-data="{ reportModalOpen: false }" class="pt-3 border-t border-slate-100 text-center">
                        <button
                            type="button"
                            @click="reportModalOpen = true"
                            class="text-xs text-slate-400 hover:text-red-600 underline"
                        >
                            {{ __('marketplace.details.report_property') }}
                        </button>

                        <!-- Report Property Interactive Modal -->
                        <div
                            x-show="reportModalOpen"
                            x-cloak
                            @keydown.escape.window="reportModalOpen = false"
                            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
                        >
                            <div
                                @click.away="reportModalOpen = false"
                                class="bg-white rounded-2xl max-w-md w-full p-6 text-left shadow-2xl border border-slate-200 space-y-4"
                            >
                                <div class="flex items-center justify-between">
                                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-1.5">
                                        <span>🚩</span>
                                        <span>Report this Listing</span>
                                    </h3>
                                    <button type="button" @click="reportModalOpen = false" class="text-slate-400 hover:text-slate-700 text-lg">✕</button>
                                </div>

                                <p class="text-xs text-slate-500 leading-relaxed">
                                    Our trust &amp; safety officers will investigate your report. False reports or abuse are subject to account restrictions.
                                </p>

                                <form method="POST" action="{{ route('listings.report.store', $listing) }}" class="space-y-4 text-xs">
                                    @csrf
                                    <div>
                                        <label class="block font-semibold text-slate-700 mb-1">Reason for Report</label>
                                        <select name="reason_code" required class="w-full rounded-xl border-slate-300 text-xs py-2">
                                            <option value="scam">Suspected Scam or Fraud</option>
                                            <option value="fake_property">Fake / Non-Existent Property</option>
                                            <option value="duplicate">Duplicate Listing</option>
                                            <option value="wrong_price">Inaccurate or Misleading Price</option>
                                            <option value="unavailable">Property is Already Sold / Rented</option>
                                            <option value="misleading">Misleading Photos or Description</option>
                                            <option value="suspicious_documents">Suspicious / Altered Documents</option>
                                            <option value="inappropriate_content">Inappropriate or Offensive Content</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block font-semibold text-slate-700 mb-1">Additional Details (Optional)</label>
                                        <textarea name="description" rows="3" placeholder="Provide any details to assist our moderation investigation..." class="w-full rounded-xl border-slate-300 text-xs p-2.5"></textarea>
                                    </div>

                                    <div class="flex items-center justify-end gap-2 pt-2">
                                        <button type="button" @click="reportModalOpen = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold">
                                            Cancel
                                        </button>
                                        <button type="submit" class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white font-semibold">
                                            Submit Report
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
