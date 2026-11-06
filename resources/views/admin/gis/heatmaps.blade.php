<x-app-layout>
    <div class="bg-slate-50 min-h-screen py-8 px-4 sm:px-6 lg:px-8 text-slate-800">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Breadcrumbs & Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                        <a href="{{ route('admin.dashboard') }}" class="hover:underline">Admin</a>
                        <span>/</span>
                        <span>Geospatial Intelligence</span>
                        <span>/</span>
                        <span class="text-slate-800 font-semibold">PostGIS Heatmaps</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900">Neighborhood ETB/m² Asking-Price Heatmaps (Module 34.2)</h1>
                    <p class="text-xs text-slate-500">Spatial asking-price aggregations computed from platform listing inventory across Ethiopian sub-cities and neighborhoods.</p>
                </div>

                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('admin.gis.heatmaps.recalculate') }}">
                        @csrf
                        <input type="hidden" name="transaction_mode" value="{{ $selectedMode }}">
                        <button
                            type="submit"
                            class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold transition shadow-sm flex items-center gap-1.5"
                        >
                            <span>⚡</span> Recalculate Aggregations
                        </button>
                    </form>
                </div>
            </div>

            @if(session('success'))
                <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 font-semibold">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Mandatory Attribution Disclosure Banner (SRS Sections 37 & 127) -->
            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-1">
                <div class="font-bold flex items-center gap-1.5">
                    <span>⚠️</span> Mandatory Regulatory Attribution (SRS Module 34.2)
                </div>
                <div>
                    <strong>Platform Asking Price Indicator:</strong> Values are derived exclusively from active platform listing asking prices and do not constitute certified government tax assessments or municipal land registry transaction valuations.
                </div>
            </div>

            <!-- Transaction Mode Tabs & Summary Stats -->
            <div class="flex flex-wrap items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200">
                <div class="inline-flex rounded-xl bg-slate-100 p-1">
                    @foreach(['BUY' => 'Sales (BUY)', 'RENT' => 'Rentals (RENT)', 'LEASE' => 'Commercial (LEASE)'] as $modeKey => $modeLabel)
                        <a
                            href="{{ route('admin.gis.heatmaps.index', ['transaction_mode' => $modeKey]) }}"
                            class="px-4 py-1.5 rounded-lg text-xs font-bold transition {{ $selectedMode === $modeKey ? 'bg-teal-700 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
                        >
                            {{ $modeLabel }}
                        </a>
                    @endforeach
                </div>

                <div class="text-xs text-slate-600">
                    Neighborhoods Covered: <strong class="text-slate-900 font-mono">{{ $totalLocationsCovered }}</strong>
                </div>
            </div>

            <!-- Heatmaps Aggregation Table -->
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-slate-700">
                                <th class="p-3.5 pl-6 font-semibold">Neighborhood / Sub-City</th>
                                <th class="p-3.5 font-semibold text-center">Sample Size</th>
                                <th class="p-3.5 font-semibold text-right">Avg ETB/m²</th>
                                <th class="p-3.5 font-semibold text-right">Median ETB/m²</th>
                                <th class="p-3.5 font-semibold text-right">Min &ndash; Max ETB/m²</th>
                                <th class="p-3.5 font-semibold text-center">Price Tier</th>
                                <th class="p-3.5 pr-6 font-semibold text-right">Snapshot Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse($heatmaps as $hm)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="p-3.5 pl-6">
                                        <div class="font-bold text-slate-900">{{ $hm->location?->name_en ?? 'Location #' . $hm->location_id }}</div>
                                        @if($hm->location?->name_am)
                                            <div class="text-[11px] text-slate-400 font-ethiopic">{{ $hm->location->name_am }}</div>
                                        @endif
                                        <div class="text-[10px] text-slate-400 font-mono">
                                            Lat: {{ number_format((float) $hm->center_latitude, 4) }}, Lng: {{ number_format((float) $hm->center_longitude, 4) }}
                                        </div>
                                    </td>
                                    <td class="p-3.5 text-center font-mono font-semibold text-slate-800">
                                        {{ $hm->sample_size }} listings
                                    </td>
                                    <td class="p-3.5 text-right font-mono font-bold text-slate-900">
                                        {{ number_format((float) $hm->avg_price_per_sqm_etb, 2) }}
                                    </td>
                                    <td class="p-3.5 text-right font-mono font-bold text-teal-800">
                                        {{ number_format((float) $hm->median_price_per_sqm_etb, 2) }}
                                    </td>
                                    <td class="p-3.5 text-right font-mono text-slate-500 text-[11px]">
                                        {{ number_format((float) $hm->min_price_per_sqm_etb, 0) }} &ndash; {{ number_format((float) $hm->max_price_per_sqm_etb, 0) }}
                                    </td>
                                    <td class="p-3.5 text-center">
                                        @php
                                            $tierColors = [
                                                1 => 'bg-emerald-100 text-emerald-900 border-emerald-300',
                                                2 => 'bg-teal-100 text-teal-900 border-teal-300',
                                                3 => 'bg-amber-100 text-amber-900 border-amber-300',
                                                4 => 'bg-orange-100 text-orange-900 border-orange-300',
                                                5 => 'bg-rose-100 text-rose-900 border-rose-300',
                                            ];
                                            $tierClass = $tierColors[$hm->price_tier] ?? 'bg-slate-100 text-slate-800 border-slate-300';
                                        @endphp
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border uppercase tracking-wider {{ $tierClass }}">
                                            Tier {{ $hm->price_tier }}
                                        </span>
                                    </td>
                                    <td class="p-3.5 pr-6 text-right font-mono text-slate-500 text-[11px]">
                                        {{ $hm->snapshot_date?->format('Y-m-d') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-slate-500">
                                        No aggregated heatmap records computed for [{{ $selectedMode }}] yet.
                                        Click "Recalculate Aggregations" above to aggregate platform listing prices.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($heatmaps->hasPages())
                    <div class="p-4 border-t border-slate-200">
                        {{ $heatmaps->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>

