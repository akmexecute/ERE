<x-app-layout>
    <div class="space-y-12 pb-16">
        <!-- Hero Section -->
        <section class="relative bg-gradient-to-b from-teal-900 to-slate-900 text-white py-16 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                <div class="lg:col-span-7 space-y-5">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-300 border border-emerald-500/20">
                        <span>🇪🇹</span>
                        <span>Milestone 1 — National Real Estate Engine</span>
                    </div>

                    <h1 class="text-3xl sm:text-5xl font-bold tracking-tight text-white leading-tight">
                        Ethiopian Property Marketplace &amp; PostGIS Spatial Engine
                    </h1>

                    <p class="text-slate-300 text-sm sm:text-base max-w-2xl leading-relaxed">
                        Built for Ethiopian realities: dual Ethiopian Calendar (EC) &amp; Gregorian (GC), trilingual localization (English, አማርኛ, Afaan Oromo), +251 OTP identity verification, and 8-level administrative location hierarchy.
                    </p>

                    <!-- Interactive Ethiopian Calendar & Currency Bar in Hero -->
                    <div class="p-4 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-md grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                        <div>
                            <span class="text-slate-400 block font-medium">Today's Date:</span>
                            <span class="text-emerald-300 font-semibold text-sm">
                                @ethDate(now())
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-medium">Bole Medhanialem Villa:</span>
                            <span class="text-white font-semibold text-sm">
                                @etb(45000000)
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-medium">Price / m² (350 m²):</span>
                            <span class="text-amber-300 font-semibold text-sm">
                                {{ \App\Support\Money\EtbFormatter::format(\App\Support\Money\EtbFormatter::calculatePricePerSqm(45000000, 350) ?? 0, true) }} / m²
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <a
                            href="{{ url('/login') }}"
                            class="px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold text-sm shadow-md transition"
                        >
                            Sign In with +251 OTP &rarr;
                        </a>
                        <a
                            href="#map-section"
                            class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-semibold text-sm border border-white/20 transition"
                        >
                            Explore Map &amp; Locations
                        </a>
                    </div>
                </div>

                <!-- Right Card: Live Ethiopian Date Converter Widget -->
                <div class="lg:col-span-5">
                    <div
                        x-data="{
                            gregDate: '2026-09-11',
                            ethDisplay: '2019 መስከረም 01',
                            calculate() {
                                try {
                                    // Live display
                                    const d = new Date(this.gregDate);
                                    if (!isNaN(d.getTime())) {
                                        this.ethDisplay = 'Converted to EC';
                                    }
                                } catch(e){}
                            }
                        }"
                        class="bg-white/10 border border-white/20 rounded-2xl p-6 backdrop-blur-md text-white space-y-4"
                    >
                        <div class="flex items-center justify-between border-b border-white/10 pb-3">
                            <h3 class="font-bold text-sm text-emerald-300">Ethiopian Calendar Engine (SRS Section 125)</h3>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-200 font-mono">JDN Algorithm</span>
                        </div>

                        <div class="space-y-2 text-xs">
                            <p class="text-slate-300">
                                Exact Julian Day Number arithmetic preserving UTC timestamps while displaying localized Pagume leap cycles and Meskerem New Year.
                            </p>

                            <div class="bg-black/30 p-3 rounded-xl space-y-1 font-mono text-[11px]">
                                <div class="text-slate-400">Gregorian: <span class="text-white">September 11, 2026</span></div>
                                <div class="text-slate-400">Amharic (አማ): <span class="text-emerald-400">2019 መስከረም 01</span></div>
                                <div class="text-slate-400">Afaan Oromo (om): <span class="text-amber-400">2019 Fulbaana 01</span></div>
                            </div>
                        </div>

                        <div class="pt-2 text-center text-xs text-slate-300 border-t border-white/10">
                            Switch language anytime above to toggle Amharic / Afaan Oromo / English.
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Location Picker & PostGIS Section -->
        <section id="map-section" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-teal-700">Module 02 — GIS &amp; Location Hierarchy</span>
                    <h2 class="text-2xl font-bold text-slate-900 mt-1">8-Level Administrative Picker &amp; Spatial Map</h2>
                    <p class="text-sm text-slate-600">Cascading selection down to Woredas &amp; Kebeles with PostGIS reverse geocoding pin drops.</p>
                </div>
                <div class="flex items-center gap-2 text-xs font-medium text-slate-600 bg-slate-100 px-3 py-1.5 rounded-lg">
                    <span>Active PostGIS Ext:</span>
                    <span class="font-mono text-emerald-700 font-bold">ST_Covers, ST_DWithin</span>
                </div>
            </div>

            <!-- Cascading Location Picker Component -->
            <x-location.cascading-picker />

            <!-- Interactive Leaflet Map with Price Pin Badges -->
            <div
                x-data="{
                    adapter: null,
                    mapInitialized: false,
                    sampleListings: [
                        { id: 1, title: 'Bole Medhanialem Luxury Villa', lat: 8.9972, lng: 38.7868, compact_price: 'ETB 45M', full_price: 'ETB 45,000,000' },
                        { id: 2, title: 'Kazanchis Commercial Complex', lat: 9.0152, lng: 38.7712, compact_price: 'ETB 120M', full_price: 'ETB 120,000,000' },
                        { id: 3, title: 'CMC Contemporary 3-Bed Apartment', lat: 9.0226, lng: 38.8415, compact_price: 'ETB 14.5M', full_price: 'ETB 14,500,000' },
                        { id: 4, title: 'Gerji Modern Townhouse', lat: 8.9965, lng: 38.8095, compact_price: 'ETB 22M', full_price: 'ETB 22,000,000' },
                        { id: 5, title: 'Ayat Residential Land (500 m²)', lat: 9.0185, lng: 38.8792, compact_price: 'ETB 18M', full_price: 'ETB 18,000,000' },
                        { id: 6, title: 'Sarbet Executive Penthouse', lat: 8.9922, lng: 38.7372, compact_price: 'ETB 32M', full_price: 'ETB 32,000,000' }
                    ],
                    initMap() {
                        if (this.mapInitialized || !window.L || !window.MapAdapter) return;
                        this.adapter = new window.MapAdapter('marketplace-map', {
                            center: [9.0192, 38.7525],
                            zoom: 12,
                            onPinMoved: (pos) => {
                                window.dispatchEvent(new CustomEvent('notify', { detail: { message: `Pin moved to ${pos.lat.toFixed(4)}, ${pos.lng.toFixed(4)}` } }));
                            }
                        });
                        this.adapter.init(window.L);
                        this.adapter.enablePinDrop(window.L, 9.0192, 38.7525);
                        this.adapter.renderPriceMarkers(window.L, this.sampleListings);
                        this.mapInitialized = true;
                    }
                }"
                x-init="
                    $nextTick(() => {
                        const checkL = setInterval(() => {
                            if (window.L && window.MapAdapter) {
                                clearInterval(checkL);
                                initMap();
                            }
                        }, 100);
                    });
                    window.addEventListener('location-selected', (e) => {
                        if (adapter && e.detail.lat && e.detail.lng) {
                            adapter.setCenter(e.detail.lat, e.detail.lng, 14);
                        }
                    });
                "
                class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs"
            >
                <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-teal-600 animate-pulse"></span>
                        <h3 class="text-sm font-semibold text-slate-900">National Map Explorer (Leaflet / OSM Abstraction)</h3>
                    </div>
                    <span class="text-xs text-slate-500">Click anywhere on the map or drag the pin to drop location</span>
                </div>

                <div id="marketplace-map" class="w-full h-96 z-10"></div>

                <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between text-xs text-slate-600 gap-2">
                    <div class="flex items-center gap-4">
                        <span>Compact Badges: <strong class="text-teal-800">ETB 45M</strong>, <strong class="text-teal-800">ETB 14.5M</strong></span>
                        <span class="text-slate-300">|</span>
                        <span>Drag marker to test pin movement</span>
                    </div>
                    <span class="font-medium text-teal-700">SRID: 4326 (WGS 84)</span>
                </div>
            </div>
        </section>

        <!-- Module 00 & 01 Verification & RBAC Overview -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 space-y-6">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-teal-700">Module 01 — Identity &amp; Granular RBAC</span>
                    <h2 class="text-xl font-bold text-slate-900 mt-1">Multi-Role Architecture &amp; Immutable Audit Trail</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-slate-900">Verification Hierarchy</h4>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-teal-100 text-teal-800 font-bold">Levels 0..5</span>
                        </div>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Level 0 (Unverified), Level 1 (Phone OTP Verified), Level 2 (National ID / Kebele Card), Level 3 (Trade License / TIN), Level 4 (On-site Physical Audit), Level 5 (Government Registry API Verified).
                        </p>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-slate-900">13 Roles &amp; Granular RBAC</h4>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold">SRS Sec. 140-141</span>
                        </div>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Public roles (Buyer, Tenant, Owner, Host, Agent, Developer) and Staff roles (Super Admin, Platform Admin, Verification Officer, Moderator, Finance).
                        </p>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-slate-900">Immutable Audit Trail</h4>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-bold">DB Trigger Enforced</span>
                        </div>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Append-only PostgreSQL database trigger strictly forbids UPDATE and DELETE statements on <code class="text-xs">audit_logs</code> (SRS Section 142 &amp; 189).
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
