<x-app-layout>
    <div class="min-h-screen bg-[#faf9f5] py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto space-y-8">

            <!-- Breadcrumb & Back to Dashboard -->
            <div class="flex items-center justify-between">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 hover:text-slate-900 transition">
                    <span>&larr;</span> Back to Dashboard
                </a>
                <span class="text-xs font-medium text-slate-400">Step 1 of 2: Listing Selection</span>
            </div>

            <!-- Page Title -->
            <div class="text-center max-w-2xl mx-auto space-y-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-teal-50 text-teal-800 border border-teal-200">
                    Post a Property &amp; Guided Publishing Flow
                </span>
                <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">What would you like to publish?</h1>
                <p class="text-sm text-slate-600 leading-relaxed">
                    Select the commercial model for your property. Each pathway configures the appropriate pricing terms, documentation vault, real-time <strong>Listing Quality Score</strong>, <strong>Auto-Calculated Price per m²</strong>, and regulatory screening.
                </p>
            </div>

            <!-- 6 Guided Choices Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                <!-- 1. Property for Sale -->
                <a
                    href="{{ route('listings.wizard', ['type' => 'sale']) }}"
                    class="group relative bg-white p-6 rounded-2xl border border-slate-200 hover:border-teal-600 hover:shadow-md transition flex flex-col justify-between"
                >
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-3xl">🏠</span>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-800 border border-emerald-200">
                                BUY Mode
                            </span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 group-hover:text-teal-800 transition">Property for Sale</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Residential villas, townhouses, luxury apartments, or commercial premises for outright purchase.
                        </p>
                        <ul class="text-[11px] text-slate-600 space-y-1.5 pt-2 border-t border-slate-100">
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> Total asking price in ETB
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> Title deed vault &amp; ownership verification
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> Multi-round buyer negotiation engine
                            </li>
                        </ul>
                    </div>
                    <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-teal-800 group-hover:translate-x-0.5 transition">
                        <span>Publish for sale</span>
                        <span>&rarr;</span>
                    </div>
                </a>

                <!-- 2. Property for Rent -->
                <a
                    href="{{ route('listings.wizard', ['type' => 'rent']) }}"
                    class="group relative bg-white p-6 rounded-2xl border border-slate-200 hover:border-teal-600 hover:shadow-md transition flex flex-col justify-between"
                >
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-3xl">🔑</span>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-800 border border-blue-200">
                                RENT Mode
                            </span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 group-hover:text-teal-800 transition">Property for Rent</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Monthly residential apartments, furnished villas, or long-term family homes.
                        </p>
                        <ul class="text-[11px] text-slate-600 space-y-1.5 pt-2 border-t border-slate-100">
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> Monthly &amp; quarterly rent schedules
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> Security deposit &amp; utility agreements
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> Verified tenant screening applications
                            </li>
                        </ul>
                    </div>
                    <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-teal-800 group-hover:translate-x-0.5 transition">
                        <span>Publish for rent</span>
                        <span>&rarr;</span>
                    </div>
                </a>

                <!-- 3. Commercial or Residential Lease -->
                <a
                    href="{{ route('listings.wizard', ['type' => 'lease']) }}"
                    class="group relative bg-white p-6 rounded-2xl border border-slate-200 hover:border-teal-600 hover:shadow-md transition flex flex-col justify-between"
                >
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-3xl">📜</span>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-purple-50 text-purple-800 border border-purple-200">
                                LEASE Mode
                            </span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 group-hover:text-teal-800 transition">Commercial / Long Lease</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Multi-year corporate office floors, retail bank branches, industrial warehouses, or long-term ground leases.
                        </p>
                        <ul class="text-[11px] text-slate-600 space-y-1.5 pt-2 border-t border-slate-100">
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> Multi-year term &amp; annual escalations
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> VAT &amp; ERCA sequential tax invoices
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> Commercial zoning &amp; mandate rules
                            </li>
                        </ul>
                    </div>
                    <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-teal-800 group-hover:translate-x-0.5 transition">
                        <span>Publish commercial lease</span>
                        <span>&rarr;</span>
                    </div>
                </a>

                <!-- 4. Land / Plot -->
                <a
                    href="{{ route('listings.wizard', ['type' => 'land']) }}"
                    class="group relative bg-white p-6 rounded-2xl border border-slate-200 hover:border-teal-600 hover:shadow-md transition flex flex-col justify-between"
                >
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-3xl">🌄</span>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-900 border border-amber-200">
                                Land (LND)
                            </span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 group-hover:text-teal-800 transition">Land / Plot</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Residential villa plots, commercial development acreage, or industrial land parcels.
                        </p>
                        <ul class="text-[11px] text-slate-600 space-y-1.5 pt-2 border-t border-slate-100">
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> Land area (m²) basis &amp; price/m² calculation
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> Topography, soil, &amp; road frontage specs
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> No bedroom or bathroom requirements
                            </li>
                        </ul>
                    </div>
                    <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-teal-800 group-hover:translate-x-0.5 transition">
                        <span>Publish land listing</span>
                        <span>&rarr;</span>
                    </div>
                </a>

                <!-- 5. Short-Stay Accommodation -->
                <a
                    href="{{ route('listings.wizard', ['type' => 'stay']) }}"
                    class="group relative bg-white p-6 rounded-2xl border border-slate-200 hover:border-teal-600 hover:shadow-md transition flex flex-col justify-between"
                >
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-3xl">🏨</span>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-900 border border-amber-200">
                                STAY Mode
                            </span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 group-hover:text-teal-800 transition">Short-Stay Accommodation</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Furnished serviced apartments, holiday villas, or guesthouses with nightly and weekend bookings.
                        </p>
                        <ul class="text-[11px] text-slate-600 space-y-1.5 pt-2 border-t border-slate-100">
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> Nightly rates &amp; weekend pricing in ETB
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> Guest capacity &amp; bed configurations
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> House rules &amp; secure private house manual
                            </li>
                        </ul>
                    </div>
                    <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-teal-800 group-hover:translate-x-0.5 transition">
                        <span>Configure stay listing</span>
                        <span>&rarr;</span>
                    </div>
                </a>

                <!-- 6. Development Project -->
                <a
                    href="{{ route('projects.index') }}"
                    class="group relative bg-white p-6 rounded-2xl border border-slate-200 hover:border-teal-600 hover:shadow-md transition flex flex-col justify-between"
                >
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-3xl">🏗️</span>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-rose-50 text-rose-800 border border-rose-200">
                                NEW_PROJECTS
                            </span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 group-hover:text-teal-800 transition">Development Project</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Multi-unit off-plan apartment towers, residential estates, or commercial development complexes.
                        </p>
                        <ul class="text-[11px] text-slate-600 space-y-1.5 pt-2 border-t border-slate-100">
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> Construction milestones &amp; progress tracking
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> Unit stacking matrix &amp; off-plan inventory
                            </li>
                            <li class="flex items-center gap-1.5">
                                <span class="text-teal-600 font-bold">✓</span> Proclamation No. 1357/2024 developer contracts
                            </li>
                        </ul>
                    </div>
                    <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-teal-800 group-hover:translate-x-0.5 transition">
                        <span>Project explorer</span>
                        <span>&rarr;</span>
                    </div>
                </a>

            </div>

            <!-- Asset Decoupling Callout -->
            <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span class="text-2xl">💡</span>
                    <div>
                        <h4 class="text-sm font-bold text-slate-900">Reusing an already registered physical property?</h4>
                        <p class="text-xs text-slate-500 mt-0.5">
                            If you already registered a physical property on ERE, you can create a new commercial offer (for example, offering an existing villa for rent after it was previously listed for sale) without re-entering structural specs or GPS coordinates.
                        </p>
                    </div>
                </div>
                <a
                    href="{{ route('listings.wizard', ['use_existing_property' => 1]) }}"
                    class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-semibold shrink-0 transition"
                >
                    Select registered property
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
