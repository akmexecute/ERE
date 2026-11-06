<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
        <!-- Breadcrumb & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-6">
            <div>
                <nav class="text-xs text-slate-500 mb-1 flex items-center gap-2">
                    <a href="{{ route('portfolio.dashboard') }}" class="hover:text-teal-700">Portfolio Dashboard</a>
                    <span>/</span>
                    <span class="text-slate-800 font-medium">Unit Inventory Matrix</span>
                </nav>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Multi-Unit Portfolio Matrix</h1>
                <p class="text-sm text-slate-600 mt-1">Real-time occupancy status, unit specifications, tenant contracts, and rental values.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('portfolio.dashboard') }}" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-semibold transition">
                    &larr; Back to KPI Dashboard
                </a>
            </div>
        </div>

        <!-- Buildings & Units Matrix -->
        <div class="space-y-8">
            @forelse($properties as $property)
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
                    <!-- Property / Building Header -->
                    <div class="bg-slate-50/80 px-6 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-lg font-bold text-slate-900">{{ $property->title_en }}</span>
                                <span class="font-mono text-xs text-slate-500 bg-slate-200 px-2 py-0.5 rounded">
                                    {{ $property->property_code }}
                                </span>
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5">
                                {{ $property->city?->name_en ?? 'Addis Ababa' }} • Owner: <strong>{{ $property->owner?->name }}</strong>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-slate-600">
                                {{ $property->projectUnits->count() + $property->accommodationUnits->count() }} Sub-Units
                            </span>
                        </div>
                    </div>

                    <!-- Units Grid / Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead class="bg-slate-100/60 border-b border-slate-200 text-slate-500 uppercase font-semibold">
                                <tr>
                                    <th class="px-6 py-3">Unit Code</th>
                                    <th class="px-6 py-3">Floor</th>
                                    <th class="px-6 py-3">Beds / Baths</th>
                                    <th class="px-6 py-3">Area (m²)</th>
                                    <th class="px-6 py-3">Occupancy Status</th>
                                    <th class="px-6 py-3">Current Occupant / Tenant</th>
                                    <th class="px-6 py-3 text-right">Rent / Month (ETB)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @php
                                    $activeLeases = $property->leases;
                                @endphp

                                @if($property->projectUnits->isNotEmpty())
                                    @foreach($property->projectUnits as $unit)
                                        @php
                                            $unitLease = $activeLeases->firstWhere('project_unit_id', $unit->id);
                                            $isOccupied = $unitLease !== null || $unit->status === 'contracted' || $unit->status === 'sold';
                                        @endphp
                                        <tr class="hover:bg-slate-50/60 transition">
                                            <td class="px-6 py-3.5 font-mono font-bold text-teal-800">
                                                {{ $unit->unit_number }}
                                            </td>
                                            <td class="px-6 py-3.5">
                                                Floor {{ $unit->floor_number }}
                                            </td>
                                            <td class="px-6 py-3.5">
                                                {{ $unit->bedrooms }} Bed • {{ $unit->bathrooms }} Bath
                                            </td>
                                            <td class="px-6 py-3.5 font-mono">
                                                {{ $unit->area_sqm }} m²
                                            </td>
                                            <td class="px-6 py-3.5">
                                                @if($isOccupied)
                                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                        ● Occupied
                                                    </span>
                                                @else
                                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                                        ○ Vacant
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-3.5">
                                                @if($unitLease && $unitLease->tenant)
                                                    <span class="font-medium text-slate-900">{{ $unitLease->tenant->name }}</span>
                                                    <span class="block text-[11px] text-slate-400">Expires @ethDate($unitLease->end_date)</span>
                                                @else
                                                    <span class="text-slate-400 italic">None</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-3.5 text-right font-mono font-bold text-slate-900">
                                                ETB {{ number_format((float) ($unitLease?->monthly_rent_etb ?? $unit->price_etb), 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                @elseif($property->accommodationUnits->isNotEmpty())
                                    @foreach($property->accommodationUnits as $stayUnit)
                                        <tr class="hover:bg-slate-50/60 transition">
                                            <td class="px-6 py-3.5 font-mono font-bold text-blue-800">
                                                {{ $stayUnit->unit_number_or_code }}
                                            </td>
                                            <td class="px-6 py-3.5 text-slate-500">—</td>
                                            <td class="px-6 py-3.5 text-slate-500">Short Stay Unit</td>
                                            <td class="px-6 py-3.5 text-slate-500">—</td>
                                            <td class="px-6 py-3.5">
                                                <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-800 border border-blue-200 capitalize">
                                                    {{ $stayUnit->status }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-3.5 text-slate-400 italic">Guest Turn-over</td>
                                            <td class="px-6 py-3.5 text-right font-mono text-slate-700">Nightly Managed</td>
                                        </tr>
                                    @endforeach
                                @else
                                    {{-- Standalone Single-Unit Property --}}
                                    @php
                                        $wholeLease = $activeLeases->first();
                                    @endphp
                                    <tr class="hover:bg-slate-50/60 transition">
                                        <td class="px-6 py-3.5 font-mono font-bold text-slate-800">WHOLE-PROPERTY</td>
                                        <td class="px-6 py-3.5">Standalone</td>
                                        <td class="px-6 py-3.5">{{ $property->bedrooms ?? 1 }} Bed • {{ $property->bathrooms ?? 1 }} Bath</td>
                                        <td class="px-6 py-3.5 font-mono">{{ $property->built_up_area_sqm ?? $property->plot_area_sqm ?? '—' }} m²</td>
                                        <td class="px-6 py-3.5">
                                            @if($wholeLease)
                                                <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                    ● Occupied
                                                </span>
                                            @else
                                                <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                                    ○ Vacant
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3.5">
                                            @if($wholeLease && $wholeLease->tenant)
                                                <span class="font-medium text-slate-900">{{ $wholeLease->tenant->name }}</span>
                                                <span class="block text-[11px] text-slate-400">Lease to @ethDate($wholeLease->end_date)</span>
                                            @else
                                                <span class="text-slate-400 italic">None</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3.5 text-right font-mono font-bold text-slate-900">
                                            ETB {{ number_format((float) ($wholeLease?->monthly_rent_etb ?? 0), 2) }}
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-400">
                    No managed buildings or multi-unit properties in portfolio.
                </div>
            @endforelse

            @if($properties->hasPages())
                <div class="p-4 border-t border-slate-200">
                    {{ $properties->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
