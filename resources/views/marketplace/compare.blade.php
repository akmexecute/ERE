<x-app-layout>
    <x-slot name="title">Property Comparison Matrix — Ethiopian Real Estate Marketplace</x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <!-- Breadcrumb & Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <nav class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                    <a href="{{ url('/search') }}" class="text-teal-800 hover:underline">Marketplace</a>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold">Side-by-Side Comparison</span>
                </nav>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">
                    Property Comparison Matrix
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Compare up to 4 verified residential or commercial properties across price, area, and physical specifications.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a
                    href="{{ url('/search') }}"
                    class="px-4 py-2 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-xs font-semibold shadow-xs transition flex items-center gap-1.5"
                >
                    <span>🔍</span>
                    <span>Browse More Listings</span>
                </a>
            </div>
        </div>

        @if(count($items) === 0)
            <!-- Empty Comparison State -->
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center max-w-md mx-auto space-y-4 shadow-xs my-8">
                <div class="w-16 h-16 rounded-full bg-teal-50 text-teal-800 flex items-center justify-center text-3xl mx-auto">
                    ⚖️
                </div>
                <h2 class="text-lg font-bold text-slate-900">No Properties Selected</h2>
                <p class="text-xs text-slate-500 leading-relaxed">
                    You have not added any properties to the matrix yet. Browse our marketplace listings and click "Compare" to evaluate up to 4 properties side-by-side.
                </p>
                <div class="pt-2">
                    <a
                        href="{{ url('/search') }}"
                        class="inline-block px-5 py-2.5 rounded-xl bg-teal-800 text-white text-xs font-bold hover:bg-teal-900 transition shadow-xs"
                    >
                        Explore Marketplace Listings
                    </a>
                </div>
            </div>
        @else
            <!-- Comparison Matrix Table -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/80">
                                <th class="py-4 px-6 font-bold text-slate-500 uppercase tracking-wider text-[11px] w-48 shrink-0">
                                    Metric / Spec
                                </th>
                                @foreach($items as $item)
                                    @php
                                        $remainingIds = array_filter(array_column($items, 'id'), fn($id) => $id !== $item['id']);
                                        $removeUrl = route('marketplace.compare', ['ids' => $remainingIds]);
                                    @endphp
                                    <th class="py-4 px-6 text-left align-top min-w-[240px] max-w-[280px]">
                                        <div class="space-y-2">
                                            @if(!empty($item['primary_image']))
                                                <div class="h-32 rounded-xl overflow-hidden bg-slate-100 border border-slate-200">
                                                    <img
                                                        src="{{ asset('storage/' . $item['primary_image']) }}"
                                                        alt="{{ $item['title'] }}"
                                                        class="w-full h-full object-cover"
                                                    >
                                                </div>
                                            @else
                                                <div class="h-32 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-2xl text-slate-400">
                                                    🏢
                                                </div>
                                            @endif

                                            <div class="flex items-start justify-between gap-2">
                                                <div>
                                                    <span class="font-mono text-[10px] text-slate-500">{{ $item['property_code'] }}</span>
                                                    <h3 class="text-xs font-bold text-slate-900 leading-tight">
                                                        {{ $item['title'] }}
                                                    </h3>
                                                    <p class="text-[11px] text-slate-500 mt-0.5">📍 {{ $item['location_label'] }}</p>
                                                </div>
                                                <a
                                                    href="{{ $removeUrl }}"
                                                    class="text-slate-400 hover:text-rose-600 text-sm p-1 rounded-md transition"
                                                    title="Remove from comparison"
                                                >
                                                    ✕
                                                </a>
                                            </div>
                                        </div>
                                    </th>
                                @endforeach

                                @for($i = count($items); $i < 4; $i++)
                                    <th class="py-4 px-6 text-center align-middle min-w-[200px] border-l border-dashed border-slate-200 text-slate-400">
                                        <div class="space-y-2 py-6">
                                            <span class="text-2xl opacity-60">➕</span>
                                            <p class="text-[11px] font-medium text-slate-500">Slot {{ $i + 1 }} Empty</p>
                                            <a href="{{ url('/search') }}" class="text-[10px] font-semibold text-teal-800 hover:underline">
                                                Add Listing →
                                            </a>
                                        </div>
                                    </th>
                                @endfor
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <!-- Asking Price Row -->
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-3.5 px-6 font-semibold text-slate-700 bg-slate-50/40">
                                    Asking Price (ETB)
                                </td>
                                @foreach($items as $item)
                                    @php $isLowest = ($highlights['lowest_price_id'] === $item['id']); @endphp
                                    <td class="py-3.5 px-6 {{ $isLowest ? 'bg-emerald-50/40' : '' }}">
                                        <div class="text-sm font-black text-slate-900">
                                            {{ \App\Support\Money\EtbFormatter::format($item['price_etb']) }}
                                        </div>
                                        @if($isLowest)
                                            <span class="inline-block mt-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold">
                                                ★ Lowest Price
                                            </span>
                                        @endif
                                    </td>
                                @endforeach
                                @for($i = count($items); $i < 4; $i++)
                                    <td class="border-l border-dashed border-slate-200"></td>
                                @endfor
                            </tr>

                            <!-- Price per m² Row -->
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-3.5 px-6 font-semibold text-slate-700 bg-slate-50/40">
                                    Price / m²
                                </td>
                                @foreach($items as $item)
                                    @php $isLowestSqm = ($highlights['lowest_price_per_sqm_id'] === $item['id']); @endphp
                                    <td class="py-3.5 px-6 {{ $isLowestSqm ? 'bg-emerald-50/40' : '' }}">
                                        @if($item['price_per_sqm_etb'])
                                            <div class="font-bold text-teal-800">
                                                {{ \App\Support\Money\EtbFormatter::format($item['price_per_sqm_etb']) }}/m²
                                            </div>
                                            @if($isLowestSqm)
                                                <span class="inline-block mt-1 px-2 py-0.5 rounded-full bg-teal-100 text-teal-800 text-[10px] font-bold">
                                                    ★ Best Value / m²
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-slate-400">N/A</span>
                                        @endif
                                    </td>
                                @endforeach
                                @for($i = count($items); $i < 4; $i++)
                                    <td class="border-l border-dashed border-slate-200"></td>
                                @endfor
                            </tr>

                            <!-- Area Row -->
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-3.5 px-6 font-semibold text-slate-700 bg-slate-50/40">
                                    Area (m²)
                                </td>
                                @foreach($items as $item)
                                    @php $isLargest = ($highlights['largest_area_id'] === $item['id']); @endphp
                                    <td class="py-3.5 px-6 {{ $isLargest ? 'bg-emerald-50/40' : '' }}">
                                        @if($item['area_sqm'])
                                            <div class="font-bold text-slate-900">
                                                {{ number_format((float)$item['area_sqm'], 1) }} m²
                                            </div>
                                            @if($isLargest)
                                                <span class="inline-block mt-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold">
                                                    ★ Largest Area
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-slate-400">Not specified</span>
                                        @endif
                                    </td>
                                @endforeach
                                @for($i = count($items); $i < 4; $i++)
                                    <td class="border-l border-dashed border-slate-200"></td>
                                @endfor
                            </tr>

                            <!-- Verification Level Row -->
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-3.5 px-6 font-semibold text-slate-700 bg-slate-50/40">
                                    Verification Level
                                </td>
                                @foreach($items as $item)
                                    @php $isHighestVerif = ($highlights['highest_verification_id'] === $item['id']); @endphp
                                    <td class="py-3.5 px-6 {{ $isHighestVerif ? 'bg-emerald-50/40' : '' }}">
                                        <x-verification.badge :level="$item['verification_level']" />
                                        @if($isHighestVerif && $item['verification_level'] > 0)
                                            <span class="inline-block mt-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold">
                                                ★ Highest Verified
                                            </span>
                                        @endif
                                    </td>
                                @endforeach
                                @for($i = count($items); $i < 4; $i++)
                                    <td class="border-l border-dashed border-slate-200"></td>
                                @endfor
                            </tr>

                            <!-- Bedrooms & Bathrooms Row -->
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-3.5 px-6 font-semibold text-slate-700 bg-slate-50/40">
                                    Bedrooms &amp; Bathrooms
                                </td>
                                @foreach($items as $item)
                                    <td class="py-3.5 px-6 text-slate-800">
                                        🛏️ {{ $item['bedrooms'] ?? 0 }} Beds • 🚿 {{ $item['bathrooms'] ?? 0 }} Baths
                                    </td>
                                @endforeach
                                @for($i = count($items); $i < 4; $i++)
                                    <td class="border-l border-dashed border-slate-200"></td>
                                @endfor
                            </tr>

                            <!-- Parking Spaces Row -->
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-3.5 px-6 font-semibold text-slate-700 bg-slate-50/40">
                                    Parking Spaces
                                </td>
                                @foreach($items as $item)
                                    <td class="py-3.5 px-6 text-slate-800">
                                        🚗 {{ $item['parking_spaces'] ?? 0 }} Spaces
                                    </td>
                                @endforeach
                                @for($i = count($items); $i < 4; $i++)
                                    <td class="border-l border-dashed border-slate-200"></td>
                                @endfor
                            </tr>

                            <!-- Amenities Row -->
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-3.5 px-6 font-semibold text-slate-700 bg-slate-50/40">
                                    Amenities
                                </td>
                                @foreach($items as $item)
                                    <td class="py-3.5 px-6">
                                        @if(!empty($item['amenities']))
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($item['amenities'] as $amenity)
                                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px]">
                                                        {{ $amenity }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-slate-400">None listed</span>
                                        @endif
                                    </td>
                                @endforeach
                                @for($i = count($items); $i < 4; $i++)
                                    <td class="border-l border-dashed border-slate-200"></td>
                                @endfor
                            </tr>

                            <!-- Action Row -->
                            <tr>
                                <td class="py-4 px-6 font-semibold text-slate-700 bg-slate-50/40"></td>
                                @foreach($items as $item)
                                    <td class="py-4 px-6">
                                        <a
                                            href="{{ url('/listings/' . ($item['property_code'] ?? $item['id'])) }}"
                                            class="block w-full py-2 px-3 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-xs font-bold text-center transition shadow-xs"
                                        >
                                            View Property Details
                                        </a>
                                    </td>
                                @endforeach
                                @for($i = count($items); $i < 4; $i++)
                                    <td class="border-l border-dashed border-slate-200"></td>
                                @endfor
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
