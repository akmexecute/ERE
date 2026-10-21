<x-app-layout>
    <div
        x-data="{
            mobileView: 'list',
            hoveredListingId: null,
            searchAsMapMoves: true,
            naturalQuery: '{{ addslashes($rawQuery ?? request('q', '')) }}',
            transactionMode: '{{ request('transaction_mode', 'BUY') }}',
            sortBy: '{{ request('sort', 'relevance') }}',
            verifiedOnly: {{ request()->boolean('verified_only') ? 'true' : 'false' }},
            listings: {{ !empty($listingsJson) ? \Illuminate\Support\Js::from($listingsJson) : \Illuminate\Support\Js::from([
                [
                    'id' => 1245,
                    'code' => 'ETH-AA-BOLE-RES-00001245',
                    'title' => 'Modern 4-Bedroom G+2 Villa — Bole Medhanialem',
                    'subcity' => 'Bole, Addis Ababa',
                    'price_formatted' => 'ETB 25,000,000',
                    'compact_price' => 'ETB 25M',
                    'price_per_sqm' => 'ETB 66,667 / m²',
                    'bedrooms' => 4,
                    'bathrooms' => 4,
                    'area_sqm' => 375,
                    'verification_level' => 3,
                    'lat' => 8.9972,
                    'lng' => 38.7868,
                    'url' => url('/search'),
                    'image_url' => null,
                ],
                [
                    'id' => 1246,
                    'code' => 'ETH-AA-YEKA-RES-00001246',
                    'title' => '3-Bedroom Luxury Apartment — Yeka CMC',
                    'subcity' => 'Yeka (CMC), Addis Ababa',
                    'price_formatted' => 'ETB 11,500,000',
                    'compact_price' => 'ETB 11.5M',
                    'price_per_sqm' => 'ETB 71,875 / m²',
                    'bedrooms' => 3,
                    'bathrooms' => 2,
                    'area_sqm' => 160,
                    'verification_level' => 2,
                    'lat' => 9.0226,
                    'lng' => 38.8415,
                    'url' => url('/search'),
                    'image_url' => null,
                ],
                [
                    'id' => 1247,
                    'code' => 'ETH-AA-KIRKOS-COM-00001247',
                    'title' => 'Grade-A Commercial Office Floor — Kazanchis',
                    'subcity' => 'Kirkos (Kazanchis), Addis Ababa',
                    'price_formatted' => 'ETB 180,000 / mo',
                    'compact_price' => 'ETB 180K/mo',
                    'price_per_sqm' => 'ETB 720 / m²',
                    'bedrooms' => 0,
                    'bathrooms' => 2,
                    'area_sqm' => 250,
                    'verification_level' => 4,
                    'lat' => 9.0152,
                    'lng' => 38.7712,
                    'url' => url('/search'),
                    'image_url' => null,
                ]
            ]) }},

            initMap() {
                if (!window.L || !document.getElementById('ep-split-map')) return;

                // Center on Addis Ababa
                const map = window.L.map('ep-split-map', {
                    center: [9.0192, 38.7525],
                    zoom: 12,
                    zoomControl: false
                });

                window.L.control.zoom({ position: 'bottomright' }).addTo(map);

                window.L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap © CARTO'
                }).addTo(map);

                const markers = {};

                this.listings.forEach(item => {
                    if (!item.lat || !item.lng) return;

                    const icon = window.L.divIcon({
                        className: 'custom-price-pin',
                        html: `<div id='pin-${item.id}' class='px-2.5 py-1 rounded-full bg-teal-800 text-white text-xs font-bold shadow-md border-2 border-white cursor-pointer transition transform hover:scale-110 hover:bg-amber-600'>${item.compact_price}</div>`,
                        iconSize: [80, 32],
                        iconAnchor: [40, 16]
                    });

                    const marker = window.L.marker([item.lat, item.lng], { icon }).addTo(map);
                    marker.on('click', () => {
                        window.location.href = item.url;
                    });
                    markers[item.id] = marker;
                });

                this.$watch('hoveredListingId', (id) => {
                    document.querySelectorAll('.custom-price-pin div').forEach(el => {
                        el.classList.remove('bg-amber-600', 'scale-125', 'ring-4', 'ring-amber-300');
                        el.classList.add('bg-teal-800');
                    });
                    if (id && markers[id]) {
                        const targetPin = document.getElementById(`pin-${id}`);
                        if (targetPin) {
                            targetPin.classList.remove('bg-teal-800');
                            targetPin.classList.add('bg-amber-600', 'scale-125', 'ring-4', 'ring-amber-300');
                        }
                    }
                });
            }
        }"
        x-init="$nextTick(() => {
            const checkMap = setInterval(() => {
                if (window.L) {
                    clearInterval(checkMap);
                    initMap();
                }
            }, 100);
        })"
        class="flex flex-col h-[calc(100vh-6.5rem)] overflow-hidden"
    >
        <!-- Top Search & Faceted Filter Bar (SRS Sections 15, 16, 17) -->
        <div class="bg-white border-b border-slate-200 px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3 z-20 shadow-xs">
            <form method="GET" action="{{ url('/search') }}" class="flex flex-wrap items-center gap-2.5 flex-1">
                <!-- Transaction Mode Pills -->
                <div class="inline-flex rounded-xl bg-slate-100 p-1">
                    @foreach(['BUY' => 'Buy', 'RENT' => 'Rent', 'LEASE' => 'Lease'] as $modeKey => $modeLabel)
                        <button
                            type="button"
                            @click="transactionMode = '{{ $modeKey }}'; $el.form.submit()"
                            :class="transactionMode === '{{ $modeKey }}' ? 'bg-teal-700 text-white font-semibold shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                            class="px-3 py-1 rounded-lg text-xs transition"
                        >
                            {{ $modeLabel }}
                        </button>
                    @endforeach
                    <input type="hidden" name="transaction_mode" :value="transactionMode">
                </div>

                <!-- Natural Language Search Input (SRS Section 15 & 131) -->
                <div class="relative flex-1 min-w-[260px]">
                    <input
                        type="text"
                        name="q"
                        x-model="naturalQuery"
                        placeholder="Try: '4-bedroom villa in Bole under 30 million' or 'Office in Kazanchis'..."
                        class="w-full rounded-xl border-slate-300 pl-3.5 pr-20 py-2 text-sm focus:border-teal-600 focus:ring-teal-600"
                    >
                    <button
                        type="submit"
                        class="absolute right-1.5 top-1.5 bottom-1.5 px-3 rounded-lg bg-teal-700 text-white text-xs font-semibold hover:bg-teal-800 transition"
                    >
                        Search
                    </button>
                </div>

                <!-- Verified Filter Toggle -->
                <label class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-700 cursor-pointer hover:bg-slate-50 transition">
                    <input
                        type="checkbox"
                        name="verified_only"
                        value="1"
                        x-model="verifiedOnly"
                        @change="$el.form.submit()"
                        class="rounded border-slate-300 text-teal-700 focus:ring-teal-600"
                    >
                    <span>✓ Verified Only</span>
                </label>

                <!-- Sorting Selector (SRS Section 17) -->
                <select name="sort" x-model="sortBy" @change="$el.form.submit()" class="rounded-xl border-slate-300 text-xs py-2 focus:border-teal-600 focus:ring-teal-600">
                    <option value="relevance">Sort: Relevance</option>
                    <option value="newest">Newest First</option>
                    <option value="price_asc">Lowest Price</option>
                    <option value="price_desc">Highest Price</option>
                    <option value="largest">Largest Area (m²)</option>
                    <option value="price_per_sqm_asc">Lowest Price / m²</option>
                </select>
            </form>
        </div>

        <!-- SRS Section 14: Desktop 50/50 Split View (Property Results Left | Interactive Map Right) -->
        <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 overflow-hidden relative">

            <!-- Left Pane: Property Results Grid -->
            <div
                :class="mobileView === 'map' ? 'hidden lg:block' : 'block'"
                class="lg:col-span-6 xl:col-span-6 overflow-y-auto p-4 sm:p-6 space-y-4 bg-slate-50 border-r border-slate-200"
            >
                <div class="flex items-center justify-between text-xs text-slate-600">
                    <span>Showing <strong x-text="listings.length"></strong> verified properties across Ethiopia</span>
                    <span class="text-teal-700 font-medium">Prices in ETB (ብር)</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <template x-for="item in listings" :key="item.id">
                        <article
                            @mouseenter="hoveredListingId = item.id"
                            @mouseleave="hoveredListingId = null"
                            :class="hoveredListingId === item.id ? 'ring-2 ring-teal-600 shadow-md' : 'border-slate-200 shadow-xs'"
                            class="bg-white rounded-2xl border overflow-hidden transition flex flex-col justify-between"
                        >
                            <div>
                                <!-- Image Preview & Badges -->
                                <div class="relative h-44 bg-gradient-to-tr from-slate-800 to-slate-700 flex items-end p-3 justify-between overflow-hidden">
                                    <template x-if="item.image_url">
                                        <img :src="item.image_url" :alt="item.title" class="absolute inset-0 w-full h-full object-cover">
                                    </template>
                                    <template x-if="!item.image_url">
                                        <div class="absolute inset-0 flex items-center justify-center text-slate-400 text-3xl">
                                            🏢
                                        </div>
                                    </template>
                                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-transparent to-transparent"></div>

                                    <div class="relative z-10 flex items-center justify-between w-full">
                                        <span class="px-2 py-0.5 rounded bg-slate-900/80 text-white text-[11px] font-mono tracking-tight" x-text="item.code"></span>
                                        <x-verification.badge ::level="item.verification_level" />
                                    </div>
                                </div>

                                <div class="p-4">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <span class="text-lg font-extrabold text-slate-900" x-text="item.price_formatted"></span>
                                        <span class="text-xs font-semibold text-teal-700" x-text="item.price_per_sqm"></span>
                                    </div>

                                    <h2 class="mt-1 text-sm font-bold text-slate-800 line-clamp-1">
                                        <a :href="item.url" class="hover:text-teal-700 transition" x-text="item.title"></a>
                                    </h2>
                                    <p class="text-xs text-slate-500 mt-0.5 line-clamp-1" x-text="item.subcity"></p>

                                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center gap-4 text-xs text-slate-600">
                                        <span>🛏 <strong x-text="item.bedrooms"></strong> Beds</span>
                                        <span>🚿 <strong x-text="item.bathrooms"></strong> Baths</span>
                                        <span>📐 <strong x-text="item.area_sqm"></strong> m²</span>
                                    </div>
                                </div>
                            </div>

                            <div class="px-4 pb-4 pt-1">
                                <a
                                    :href="item.url"
                                    class="w-full block text-center py-2 px-3 rounded-xl bg-slate-100 hover:bg-teal-700 hover:text-white text-xs font-semibold text-slate-800 transition"
                                >
                                    View Asset & Legal Vault &rarr;
                                </a>
                            </div>
                        </article>
                    </template>
                </div>
            </div>

            <!-- Right Pane: Interactive GIS Map with Price Pins & Viewport Filter -->
            <div
                :class="mobileView === 'list' ? 'hidden lg:block' : 'block'"
                class="lg:col-span-6 xl:col-span-6 relative bg-slate-100 h-full"
            >
                <!-- Map Controls Overlay -->
                <div class="absolute top-4 left-4 right-4 z-10 flex items-center justify-between pointer-events-none">
                    <label class="pointer-events-auto inline-flex items-center gap-2 bg-white/95 backdrop-blur px-3.5 py-2 rounded-xl shadow-md border border-slate-200 text-xs font-semibold text-slate-800">
                        <input type="checkbox" x-model="searchAsMapMoves" class="rounded border-slate-300 text-teal-700 focus:ring-teal-600">
                        <span>Search as I move the map</span>
                    </label>
                </div>

                <!-- Map Canvas Container (Bound to MapAdapter / Leaflet) -->
                <div id="ep-split-map" class="w-full h-full z-0"></div>
            </div>

            <!-- Mobile Sticky Bottom Pill Toggle: [ Map View | List View ] -->
            <div class="lg:hidden fixed bottom-6 inset-x-0 flex justify-center z-30 pointer-events-none">
                <div class="pointer-events-auto inline-flex rounded-full bg-slate-900 text-white p-1 shadow-xl border border-slate-700">
                    <button
                        type="button"
                        @click="mobileView = 'list'"
                        :class="mobileView === 'list' ? 'bg-teal-600 text-white' : 'text-slate-300'"
                        class="px-4 py-1.5 rounded-full text-xs font-bold transition"
                    >
                        ☰ List View
                    </button>
                    <button
                        type="button"
                        @click="mobileView = 'map'"
                        :class="mobileView === 'map' ? 'bg-teal-600 text-white' : 'text-slate-300'"
                        class="px-4 py-1.5 rounded-full text-xs font-bold transition"
                    >
                        🗺️ Map View
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
