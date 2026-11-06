<x-app-layout>
    <div
        x-data="searchSplitView()"
        class="flex flex-col h-[calc(100vh-6.5rem)] overflow-hidden"
    >
        <!-- Top Search & Faceted Filter Bar (SRS Sections 15, 16, 17) -->
        <div class="bg-white border-b border-slate-200 px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3 z-20 shadow-xs">
            <form method="GET" action="{{ url('/search') }}" class="flex flex-wrap items-center gap-2.5 flex-1">
                <!-- Transaction Mode Pills -->
                <div class="inline-flex rounded-xl bg-slate-100 p-1">
                    @foreach(['BUY' => __('app.nav.buy'), 'RENT' => __('app.nav.rent'), 'LEASE' => __('app.nav.lease')] as $modeKey => $modeLabel)
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
                        placeholder="{{ __('marketplace.search_placeholder') }}"
                        class="w-full rounded-xl border-slate-300 pl-3.5 pr-20 py-2 text-sm focus:border-teal-600 focus:ring-teal-600"
                    >
                    <button
                        type="submit"
                        class="absolute right-1.5 top-1.5 bottom-1.5 px-3 rounded-lg bg-teal-700 text-white text-xs font-semibold hover:bg-teal-800 transition"
                    >
                        {{ __('marketplace.search_btn') }}
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
                    <span>{{ __('marketplace.verified_only') }}</span>
                </label>

                <!-- Sorting Selector (SRS Section 17) -->
                <select name="sort" x-model="sortBy" @change="$el.form.submit()" class="rounded-xl border-slate-300 text-xs py-2 focus:border-teal-600 focus:ring-teal-600">
                    <option value="relevance">{{ __('marketplace.relevance') }}</option>
                    <option value="newest">{{ __('marketplace.newest') }}</option>
                    <option value="price_asc">{{ __('marketplace.price_low_high') }}</option>
                    <option value="price_desc">{{ __('marketplace.price_high_low') }}</option>
                    <option value="largest">{{ __('marketplace.largest') }}</option>
                    <option value="price_per_sqm_asc">{{ __('marketplace.price_sqm_asc') }}</option>
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
                    <span>Showing <strong x-text="listings.length"></strong> {{ __('marketplace.showing_properties') }}</span>
                    <span class="text-teal-700 font-medium">{{ __('marketplace.prices_in_etb') }}</span>
                </div>

                <template x-if="listings.length === 0">
                    <div class="p-8 text-center bg-white rounded-2xl border border-slate-200 space-y-2">
                        <span class="text-3xl">🔍</span>
                        <h3 class="text-sm font-bold text-slate-800">No properties found</h3>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto">
                            No active listings match your current filters. Try expanding your search area, removing specific keywords, or adjusting price and bedroom limits.
                        </p>
                    </div>
                </template>

                <div x-show="listings.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                                        <span>🛏 <strong x-text="item.bedrooms"></strong> {{ __('marketplace.beds') }}</span>
                                        <span>🚿 <strong x-text="item.bathrooms"></strong> {{ __('marketplace.baths') }}</span>
                                        <span>📐 <strong x-text="item.area_sqm"></strong> m²</span>
                                    </div>
                                </div>
                            </div>

                            <div class="px-4 pb-4 pt-1">
                                <a
                                    :href="item.url"
                                    class="w-full block text-center py-2 px-3 rounded-xl bg-slate-100 hover:bg-teal-700 hover:text-white text-xs font-semibold text-slate-800 transition"
                                >
                                    {{ __('marketplace.view_vault') }}
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
                <div class="absolute top-4 left-4 right-4 z-10 flex flex-wrap items-center justify-between gap-2 pointer-events-none">
                    <label class="pointer-events-auto inline-flex items-center gap-2 bg-white/95 backdrop-blur px-3.5 py-2 rounded-xl shadow-md border border-slate-200 text-xs font-semibold text-slate-800">
                        <input type="checkbox" x-model="searchAsMapMoves" class="rounded border-slate-300 text-teal-700 focus:ring-teal-600">
                        <span>{{ __('marketplace.search_as_map_moves') }}</span>
                    </label>

                    <!-- Map Layer Mode Switcher: Properties vs ETB/m² Heatmap (Module 34.2) -->
                    <div class="pointer-events-auto inline-flex rounded-xl bg-white/95 backdrop-blur p-1 shadow-md border border-slate-200 text-xs font-bold">
                        <button
                            type="button"
                            @click="setMapMode('properties')"
                            :class="mapMode === 'properties' ? 'bg-teal-700 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                            class="px-3 py-1 rounded-lg transition"
                        >
                            🏠 Properties
                        </button>
                        <button
                            type="button"
                            @click="setMapMode('heatmap')"
                            :class="mapMode === 'heatmap' ? 'bg-amber-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                            class="px-3 py-1 rounded-lg transition flex items-center gap-1"
                        >
                            <span>🗺️</span>
                            <span>ETB/m² Heatmap</span>
                            <span class="text-[9px] px-1 py-0.2 rounded bg-amber-900/40 text-amber-100 font-mono">Pro</span>
                        </button>
                    </div>
                </div>

                <!-- Entitlement Gate / Upgrade Notice for Heatmap -->
                <div
                    x-show="mapMode === 'heatmap' && heatmapError"
                    x-cloak
                    class="absolute inset-x-6 top-20 z-20 p-5 rounded-2xl bg-slate-900/95 backdrop-blur text-white border border-slate-700 shadow-2xl max-w-lg mx-auto space-y-3"
                >
                    <div class="flex items-start gap-3">
                        <span class="text-2xl">🔒</span>
                        <div class="space-y-1">
                            <h4 class="text-sm font-bold text-amber-400">Advanced Spatial Heatmaps Require Subscription</h4>
                            <p class="text-xs text-slate-300 leading-relaxed" x-text="heatmapError"></p>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                        <button type="button" @click="setMapMode('properties')" class="px-3 py-1.5 rounded-xl border border-slate-700 text-xs text-slate-300 hover:bg-slate-800">
                            Back to Normal Map
                        </button>
                        <a href="{{ route('subscriptions.index') }}" class="px-4 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold transition shadow-sm">
                            View Subscription Plans &rarr;
                        </a>
                    </div>
                </div>

                <!-- Heatmap Choropleth Legend (Module 34.2) -->
                <div
                    x-show="mapMode === 'heatmap' && !heatmapError"
                    x-cloak
                    class="absolute bottom-6 left-4 z-10 bg-white/95 backdrop-blur p-3.5 rounded-2xl shadow-lg border border-slate-200 text-xs space-y-2 pointer-events-auto max-w-xs"
                >
                    <div class="font-bold text-slate-900 flex items-center justify-between">
                        <span>Asking Price Tiers (ETB/m²)</span>
                        <span class="text-[10px] text-teal-700 font-mono" x-text="transactionMode"></span>
                    </div>

                    <div class="space-y-1 text-[11px]">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-5 rounded bg-[#059669]"></span>
                            <span class="text-slate-700">Tier 1 (&lt; 25,000 ETB/m²)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-5 rounded bg-[#0284c7]"></span>
                            <span class="text-slate-700">Tier 2 (25,000 &ndash; 40,000 ETB/m²)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-5 rounded bg-[#d97706]"></span>
                            <span class="text-slate-700">Tier 3 (40,000 &ndash; 55,000 ETB/m²)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-5 rounded bg-[#c2410c]"></span>
                            <span class="text-slate-700">Tier 4 (55,000 &ndash; 75,000 ETB/m²)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-5 rounded bg-[#7f1d1d]"></span>
                            <span class="text-slate-700">Tier 5 (&ge; 75,000 ETB/m²)</span>
                        </div>
                    </div>

                    <div class="pt-1.5 border-t border-slate-200 text-[10px] text-slate-500 leading-tight">
                        * Platform Asking Price Indicator (Not government tax valuation)
                    </div>
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
                        {{ __('marketplace.view_list') }}
                    </button>
                    <button
                        type="button"
                        @click="mobileView = 'map'"
                        :class="mobileView === 'map' ? 'bg-teal-600 text-white' : 'text-slate-300'"
                        class="px-4 py-1.5 rounded-full text-xs font-bold transition"
                    >
                        {{ __('marketplace.view_map') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            function initSearchSplitViewComponent() {
                if (!window.Alpine) return;

                window.Alpine.data('searchSplitView', () => ({
                    mobileView: 'list',
                    hoveredListingId: null,
                    searchAsMapMoves: true,
                    naturalQuery: {!! json_encode($rawQuery ?? request('q', '')) !!},
                    transactionMode: {!! json_encode(request('transaction_mode') ?? request('mode', 'BUY')) !!},
                    sortBy: {!! json_encode(request('sort', 'relevance')) !!},
                    verifiedOnly: {{ request()->boolean('verified_only') ? 'true' : 'false' }},
                    listings: {!! json_encode($listingsJson ?? []) !!},
                    mapMode: 'properties',
                    heatmapLoading: false,
                    heatmapError: null,
                    mapInstance: null,
                    markersLayerGroup: null,
                    heatmapLayer: null,

                    init() {
                        this.$nextTick(() => {
                            const checkMap = setInterval(() => {
                                if (window.L) {
                                    clearInterval(checkMap);
                                    this.initMap();
                                }
                            }, 100);
                        });
                    },

                    async setMapMode(mode) {
                        if (this.mapMode === mode) return;
                        this.mapMode = mode;

                        if (mode === 'heatmap') {
                            if (this.mapInstance && this.markersLayerGroup) {
                                this.mapInstance.removeLayer(this.markersLayerGroup);
                            }
                            this.heatmapLoading = true;
                            this.heatmapError = null;
                            try {
                                const resp = await fetch('/api/gis/neighborhood-heatmap?transaction_mode=' + encodeURIComponent(this.transactionMode), {
                                    headers: { 'Accept': 'application/json' }
                                });
                                const data = await resp.json();
                                if (!resp.ok) {
                                    this.heatmapError = data.message || 'Advanced PostGIS neighborhood heatmaps require an active Agent Pro, Agency, Developer, or Enterprise subscription.';
                                    return;
                                }

                                if (!this.mapInstance) return;

                                if (this.heatmapLayer) {
                                    this.mapInstance.removeLayer(this.heatmapLayer);
                                }

                                const tierPalette = {
                                    1: '#059669',
                                    2: '#0284c7',
                                    3: '#d97706',
                                    4: '#c2410c',
                                    5: '#7f1d1d'
                                };

                                this.heatmapLayer = window.L.geoJSON(data, {
                                    style: (feature) => ({
                                        fillColor: tierPalette[feature.properties.price_tier] || '#64748b',
                                        weight: 2,
                                        opacity: 0.9,
                                        color: '#ffffff',
                                        fillOpacity: 0.65
                                    }),
                                    onEachFeature: (feature, layer) => {
                                        const p = feature.properties;
                                        const content = '<div class="p-2 text-xs font-sans space-y-1">' +
                                            '<div class="font-bold text-slate-900">' + p.neighborhood_name + '</div>' +
                                            '<div class="text-[11px] text-teal-800 font-bold">Avg: ' + new Intl.NumberFormat('en-ET').format(p.avg_price_per_sqm_etb) + ' ETB/m²</div>' +
                                            '<div class="text-[11px] text-slate-600">Median: ' + new Intl.NumberFormat('en-ET').format(p.median_price_per_sqm_etb) + ' ETB/m²</div>' +
                                            '<div class="text-[10px] text-slate-500">Sample: ' + p.sample_size + ' listings · Tier ' + p.price_tier + '</div>' +
                                            '</div>';
                                        layer.bindPopup(content);
                                    }
                                }).addTo(this.mapInstance);

                                if (this.heatmapLayer.getBounds().isValid()) {
                                    this.mapInstance.fitBounds(this.heatmapLayer.getBounds(), { padding: [20, 20] });
                                }
                            } catch (e) {
                                this.heatmapError = 'Failed to load geospatial heatmap layer.';
                            } finally {
                                this.heatmapLoading = false;
                            }
                        } else {
                            if (this.mapInstance) {
                                if (this.heatmapLayer) {
                                    this.mapInstance.removeLayer(this.heatmapLayer);
                                }
                                if (this.markersLayerGroup) {
                                    this.mapInstance.addLayer(this.markersLayerGroup);
                                }
                            }
                            this.heatmapError = null;
                        }
                    },

                    initMap() {
                        if (!window.L || !document.getElementById('ep-split-map')) return;

                        const defaultCenter = this.listings.length > 0 && this.listings[0].lat && this.listings[0].lng
                            ? [this.listings[0].lat, this.listings[0].lng]
                            : [9.0192, 38.7525];

                        const map = window.L.map('ep-split-map', {
                            center: defaultCenter,
                            zoom: 12,
                            zoomControl: false
                        });

                        this.mapInstance = map;

                        window.L.control.zoom({ position: 'bottomright' }).addTo(map);

                        window.L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                            maxZoom: 19,
                            attribution: '© OpenStreetMap © CARTO'
                        }).addTo(map);

                        const markers = {};
                        const bounds = [];
                        const markerGroup = window.L.layerGroup().addTo(map);
                        this.markersLayerGroup = markerGroup;

                        this.listings.forEach(item => {
                            if (!item.lat || !item.lng) return;

                            const icon = window.L.divIcon({
                                className: 'custom-price-pin',
                                html: '<div id="pin-' + item.id + '" class="px-2.5 py-1 rounded-full bg-teal-800 text-white text-xs font-bold shadow-md border-2 border-white cursor-pointer transition transform hover:scale-110 hover:bg-amber-600">' + item.compact_price + '</div>',
                                iconSize: [80, 32],
                                iconAnchor: [40, 16]
                            });

                            const marker = window.L.marker([item.lat, item.lng], { icon });
                            marker.on('click', () => {
                                window.location.href = item.url;
                            });
                            marker.addTo(markerGroup);
                            markers[item.id] = marker;
                            bounds.push([item.lat, item.lng]);
                        });

                        if (bounds.length > 1) {
                            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
                        }

                        let userInteracted = false;
                        const mapEl = map.getContainer();
                        if (mapEl) {
                            mapEl.addEventListener('mousedown', () => { userInteracted = true; }, { passive: true });
                            mapEl.addEventListener('touchstart', () => { userInteracted = true; }, { passive: true });
                            mapEl.addEventListener('wheel', () => { userInteracted = true; }, { passive: true });
                        }

                        let moveTimeout = null;
                        map.on('dragend zoomend', () => {
                            if (!userInteracted || !this.searchAsMapMoves) return;
                            clearTimeout(moveTimeout);
                            moveTimeout = setTimeout(() => {
                                const b = map.getBounds();
                                if (!b.isValid() || b.getSouthWest().lat === b.getNorthEast().lat) return;
                                const params = new URLSearchParams(window.location.search);
                                params.set('sw_lat', b.getSouthWest().lat.toFixed(5));
                                params.set('sw_lng', b.getSouthWest().lng.toFixed(5));
                                params.set('ne_lat', b.getNorthEast().lat.toFixed(5));
                                params.set('ne_lng', b.getNorthEast().lng.toFixed(5));
                                window.location.search = params.toString();
                            }, 800);
                        });

                        this.$watch('hoveredListingId', (id) => {
                            document.querySelectorAll('.custom-price-pin div').forEach(el => {
                                el.classList.remove('bg-amber-600', 'scale-125', 'ring-4', 'ring-amber-300');
                                el.classList.add('bg-teal-800');
                            });
                            if (id && markers[id]) {
                                const targetPin = document.getElementById('pin-' + id);
                                if (targetPin) {
                                    targetPin.classList.remove('bg-teal-800');
                                    targetPin.classList.add('bg-amber-600', 'scale-125', 'ring-4', 'ring-amber-300');
                                }
                            }
                        });
                    }
                }));
            }

            if (window.Alpine) {
                initSearchSplitViewComponent();
            } else {
                document.addEventListener('alpine:init', initSearchSplitViewComponent);
            }
        })();
    </script>
</x-app-layout>
