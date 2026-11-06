<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8" x-data="{
        filtersOpen: false,
        location: '{{ request('location', '') }}',
        guests: '{{ request('guests', '') }}',
        checkIn: '{{ request('check_in', '') }}',
        checkOut: '{{ request('check_out', '') }}',
        minPrice: '{{ request('min_price', '') }}',
        maxPrice: '{{ request('max_price', '') }}'
    }">
        <!-- Hero & Header Banner -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-teal-950 to-slate-900 text-white p-6 sm:p-10 shadow-xl border border-slate-800">
            <div class="relative z-10 max-w-3xl space-y-3">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-500/20 text-teal-300 border border-teal-500/30 text-xs font-bold uppercase tracking-wider">
                    <span>✨</span>
                    <span>Discover Short-Stay Accommodations</span>
                </div>
                <h1 class="text-2xl sm:text-4xl font-extrabold tracking-tight">
                    Find Your Ideal Short-Term Stay in Ethiopia
                </h1>
                <p class="text-sm sm:text-base text-slate-300">
                    Hand-picked serviced villas, executive apartments, and luxury guest suites across Addis Ababa with guaranteed backup power, fiber internet, and instant booking holds.
                </p>
            </div>
            <!-- Decorative Accent -->
            <div class="absolute -right-10 -bottom-10 w-72 h-72 rounded-full bg-teal-600/10 blur-3xl pointer-events-none"></div>
        </div>

        <!-- Sticky Search & Filter Bar -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5">
            <form method="GET" action="{{ route('stays.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <!-- Location Filter -->
                    <div class="space-y-1">
                        <label for="filter-location" class="block text-xs font-bold uppercase tracking-wider text-slate-600">Location / Area</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </span>
                            <input
                                type="text"
                                id="filter-location"
                                name="location"
                                value="{{ request('location') }}"
                                placeholder="Bole, Kazanchis, CMC..."
                                class="w-full pl-9 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-teal-600 focus:border-teal-600 bg-slate-50/50"
                            />
                        </div>
                    </div>

                    <!-- Check-In Date -->
                    <div class="space-y-1">
                        <label for="filter-check-in" class="block text-xs font-bold uppercase tracking-wider text-slate-600">Check-in</label>
                        <div class="relative">
                            <input
                                type="date"
                                id="filter-check-in"
                                name="check_in"
                                value="{{ request('check_in') }}"
                                class="w-full px-3 py-2 text-xs sm:text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-teal-600 focus:border-teal-600 bg-slate-50/50"
                            />
                        </div>
                    </div>

                    <!-- Check-Out Date -->
                    <div class="space-y-1">
                        <label for="filter-check-out" class="block text-xs font-bold uppercase tracking-wider text-slate-600">Check-out</label>
                        <div class="relative">
                            <input
                                type="date"
                                id="filter-check-out"
                                name="check_out"
                                value="{{ request('check_out') }}"
                                class="w-full px-3 py-2 text-xs sm:text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-teal-600 focus:border-teal-600 bg-slate-50/50"
                            />
                        </div>
                    </div>

                    <!-- Guests Counter -->
                    <div class="space-y-1">
                        <label for="filter-guests" class="block text-xs font-bold uppercase tracking-wider text-slate-600">Guests</label>
                        <select
                            id="filter-guests"
                            name="guests"
                            class="w-full px-3 py-2 text-xs sm:text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-teal-600 focus:border-teal-600 bg-slate-50/50"
                        >
                            <option value="">Any Guests</option>
                            <option value="1" @selected(request('guests') == '1')>1 Guest</option>
                            <option value="2" @selected(request('guests') == '2')>2 Guests</option>
                            <option value="4" @selected(request('guests') == '4')>4+ Guests</option>
                            <option value="6" @selected(request('guests') == '6')>6+ Guests</option>
                            <option value="8" @selected(request('guests') == '8')>8+ Guests</option>
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-end gap-2">
                        <button
                            type="submit"
                            class="flex-1 py-2 px-4 rounded-xl bg-teal-700 hover:bg-teal-800 text-white font-bold text-xs sm:text-sm transition shadow-sm flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <span>Search</span>
                        </button>
                        @if(request()->anyFilled(['location', 'guests', 'check_in', 'check_out', 'min_price', 'max_price', 'property_type']))
                            <a
                                href="{{ route('stays.index') }}"
                                class="py-2 px-3 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-600 font-semibold text-xs sm:text-sm transition"
                                title="Reset all search filters"
                            >
                                Reset
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Price Range Filter Bar -->
                <div class="flex flex-wrap items-center gap-4 pt-3 border-t border-slate-100 text-xs">
                    <span class="font-bold text-slate-700">Nightly Price (ETB):</span>
                    <div class="flex items-center gap-2">
                        <input
                            type="number"
                            name="min_price"
                            value="{{ request('min_price') }}"
                            placeholder="Min ETB"
                            class="w-28 px-2.5 py-1 text-xs rounded-lg border border-slate-200 focus:ring-1 focus:ring-teal-600 bg-white"
                        />
                        <span class="text-slate-400 font-bold">—</span>
                        <input
                            type="number"
                            name="max_price"
                            value="{{ request('max_price') }}"
                            placeholder="Max ETB"
                            class="w-28 px-2.5 py-1 text-xs rounded-lg border border-slate-200 focus:ring-1 focus:ring-teal-600 bg-white"
                        />
                    </div>

                    <!-- Quick Pre-Set Filter Links -->
                    <div class="flex items-center gap-1.5 ml-auto text-[11px] text-slate-500">
                        <span>Popular:</span>
                        <a href="{{ route('stays.index', ['location' => 'Bole']) }}" class="px-2 py-0.5 rounded-md bg-slate-100 hover:bg-teal-50 hover:text-teal-800 transition">Bole</a>
                        <a href="{{ route('stays.index', ['location' => 'Kazanchis']) }}" class="px-2 py-0.5 rounded-md bg-slate-100 hover:bg-teal-50 hover:text-teal-800 transition">Kazanchis</a>
                        <a href="{{ route('stays.index', ['location' => 'CMC']) }}" class="px-2 py-0.5 rounded-md bg-slate-100 hover:bg-teal-50 hover:text-teal-800 transition">CMC</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Stays Discovery Results Section -->
        <div class="space-y-4">
            <!-- Results Count & Context Bar -->
            <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                <div>
                    <h2 class="text-lg font-extrabold text-slate-900">
                        {{ $totalCount }} {{ \Illuminate\Support\Str::plural('Short-Stay Property', $totalCount) }} Available
                    </h2>
                    <p class="text-xs text-slate-500">
                        Real-time database listings verified with high reliability, backup amenities, and instant booking holds.
                    </p>
                </div>

                <!-- Currency Tag -->
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 border border-slate-200">
                        All rates in <strong>ETB (Ethiopian Birr)</strong>
                    </span>
                </div>
            </div>

            <!-- Stays Grid -->
            @if(count($stays) > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($stays as $stay)
                        <article class="group bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs hover:shadow-lg transition-all duration-200 flex flex-col justify-between">
                            <div>
                                <!-- Property Photo & Badges -->
                                <div class="relative aspect-4/3 bg-slate-100 overflow-hidden">
                                    @if(!empty($stay['image_url']))
                                        <img
                                            src="{{ $stay['image_url'] }}"
                                            alt="{{ $stay['title'] }}"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                            loading="lazy"
                                        />
                                    @else
                                        <!-- Sophisticated Architectural Geometric Placeholder -->
                                        <div class="w-full h-full bg-gradient-to-br from-teal-900 via-slate-800 to-slate-950 flex flex-col items-center justify-center p-6 text-white text-center">
                                            <span class="text-4xl mb-2">🏡</span>
                                            <span class="text-xs font-bold tracking-wide uppercase text-teal-300">{{ $stay['property_type'] }}</span>
                                            <span class="text-[11px] text-slate-300 line-clamp-1 mt-1">{{ $stay['location'] }}</span>
                                        </div>
                                    @endif

                                    <!-- Top Left Badges -->
                                    <div class="absolute top-3 left-3 flex flex-col gap-1.5">
                                        @if($stay['verification_level'] >= 3)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-teal-800/90 backdrop-blur-xs text-white text-[11px] font-bold shadow-xs">
                                                <span>✓</span>
                                                <span>Verified Tier {{ $stay['verification_level'] }}</span>
                                            </span>
                                        @endif
                                        @if($stay['is_superhost'])
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-amber-500/90 backdrop-blur-xs text-slate-950 text-[10px] font-extrabold shadow-xs">
                                                <span>★</span>
                                                <span>Superhost</span>
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Top Right Instant Book Badge -->
                                    @if($stay['instant_book_enabled'])
                                        <div class="absolute top-3 right-3">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-600/90 backdrop-blur-xs text-white text-[11px] font-bold shadow-xs">
                                                <span>⚡</span>
                                                <span>Instant Book</span>
                                            </span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Card Content -->
                                <div class="p-5 space-y-3">
                                    <!-- Property Type & Rating -->
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-bold uppercase tracking-wider text-teal-800">
                                            {{ $stay['property_type'] }}
                                        </span>
                                        <div class="flex items-center gap-1 font-bold text-slate-800">
                                            <span class="text-amber-500">★</span>
                                            <span>{{ number_format($stay['rating'], 2) }}</span>
                                            <span class="text-slate-400 font-normal">({{ $stay['reviews_count'] }})</span>
                                        </div>
                                    </div>

                                    <!-- Property Title -->
                                    <h3 class="font-bold text-base text-slate-900 line-clamp-2 leading-snug group-hover:text-teal-700 transition">
                                        <a href="{{ $stay['url'] }}" target="_self">
                                            {{ $stay['title'] }}
                                        </a>
                                    </h3>

                                    <!-- Location Pin -->
                                    <div class="flex items-center gap-1 text-xs text-slate-500">
                                        <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span class="truncate">{{ $stay['location'] }}</span>
                                    </div>

                                    <!-- Capacity & Beds Overview -->
                                    <div class="text-xs text-slate-600 flex items-center gap-2 pt-1 border-t border-slate-100">
                                        <span>👥 Up to {{ $stay['max_guests'] }} guests</span>
                                        @if(!empty($stay['rooms_overview']))
                                            <span>•</span>
                                            <span>{{ $stay['rooms_overview'] }}</span>
                                        @endif
                                    </div>

                                    <!-- Amenity Pills -->
                                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                        @foreach($stay['amenities'] as $amenity)
                                            <span class="text-[10px] font-medium px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200">
                                                {{ $amenity }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <!-- Card Footer: Price & Booking Action -->
                            <div class="p-5 pt-3 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
                                <div>
                                    <div class="text-base sm:text-lg font-extrabold text-slate-900 leading-none">
                                        {{ $stay['price_formatted'] }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                        per night • taxes extra
                                    </div>
                                </div>

                                <a
                                    href="{{ $stay['url'] }}"
                                    target="_self"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white font-bold text-xs sm:text-sm transition shadow-xs cursor-pointer"
                                >
                                    <span>Select Stay</span>
                                    <span>→</span>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <!-- Pagination -->
                @if($paginator->hasPages())
                    <div class="pt-6">
                        {{ $paginator->links() }}
                    </div>
                @endif
            @else
                <!-- Clear and Helpful Empty State -->
                <div class="bg-white rounded-3xl border border-slate-200 p-8 sm:p-14 text-center max-w-2xl mx-auto space-y-5 shadow-xs">
                    <div class="w-16 h-16 rounded-2xl bg-teal-50 text-teal-700 border border-teal-200 flex items-center justify-center mx-auto text-2xl">
                        🔍
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-xl font-extrabold text-slate-900">
                            No Short-Stays Found Matching Your Criteria
                        </h3>
                        <p class="text-xs sm:text-sm text-slate-500 leading-relaxed">
                            We couldn't find any available short-stay listings matching your current location, date range, or price filters. Try adjusting your search criteria or resetting filters to see all available properties.
                        </p>
                    </div>
                    <div class="pt-2 flex flex-wrap items-center justify-center gap-3">
                        <a
                            href="{{ route('stays.index') }}"
                            class="px-5 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white font-bold text-xs sm:text-sm transition shadow-sm"
                        >
                            Clear All Filters &amp; View All Stays
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
