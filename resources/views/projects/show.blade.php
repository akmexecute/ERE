@extends('layouts.app', ['title' => $project->title_en . ' — Development Project & Inventory Matrix'])

@section('content')
<div class="bg-slate-900 text-slate-100 py-12 border-b border-slate-800">
    <!-- Hero Header: Project Master Identity & Permitting Credentials -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 pb-8 border-b border-slate-800">
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <span class="px-2.5 py-1 text-xs font-semibold rounded bg-amber-500/20 text-amber-400 border border-amber-500/30">
                        {{ ucfirst(str_replace('_', ' ', $project->project_type)) }}
                    </span>
                    <span class="px-2.5 py-1 text-xs font-semibold rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center gap-1.5">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        {{ ucfirst(str_replace('_', ' ', $project->status->value)) }}
                    </span>
                    <span class="text-xs text-slate-400 font-mono">
                        Permit: {{ $project->building_permit_number ?? 'AA/CON/BP-2025-001429' }}
                    </span>
                </div>

                <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-white mb-2">
                    {{ $project->title_en }}
                </h1>
                <p class="text-lg text-amber-400/90 font-medium font-['Noto_Sans_Ethiopic'] mb-3">
                    {{ $project->title_am ?? 'ቦሌ ሆራይዘን የቅንጦት መኖሪያዎች' }}
                </p>

                <div class="flex flex-wrap items-center gap-6 text-sm text-slate-300">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                        {{ $project->address_line ?? 'Bole Medhanialem, Addis Ababa' }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        Developer: <strong class="text-white">{{ $project->developer->company->legal_name ?? 'Horizon Developments PLC' }}</strong>
                        <span class="inline-block px-1.5 py-0.5 text-[10px] bg-emerald-950 text-emerald-300 border border-emerald-800 rounded">Verified</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Delivery: <strong>{{ \Carbon\Carbon::parse($project->expected_completion_date ?? '2027-12-31')->format('M Y') }}</strong>
                    </span>
                </div>
            </div>

            <!-- Overall Progress Widget -->
            <div class="bg-slate-800/80 rounded-xl p-5 border border-slate-700/80 min-w-[280px]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs uppercase tracking-wider text-slate-400 font-semibold">Verified Progress</span>
                    <span class="text-2xl font-bold text-emerald-400">{{ number_format($overallProgress, 2) }}%</span>
                </div>
                <div class="w-full bg-slate-700 h-2.5 rounded-full overflow-hidden mb-2">
                    <div class="bg-emerald-500 h-full rounded-full transition-all duration-700 ease-out" style="width: {{ $overallProgress }}%"></div>
                </div>
                <p class="text-xs text-slate-400">
                    Proclamation No. 1357/2024 Certified Milestones
                </p>
            </div>
        </div>

        <!-- Section 1: Construction Milestones Timeline (SRS Section 90) -->
        <div class="mt-8">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">Structural Construction Milestones</h2>
                    <p class="text-xs text-slate-400">Independent civil engineering verification audit trail</p>
                </div>
                <span class="text-xs text-slate-400 font-mono">Formula: ∑(Weight × Completion) = {{ number_format($overallProgress, 2) }}%</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3">
                @foreach($project->milestones->sortBy('order_index') as $milestone)
                    <div class="bg-slate-800/60 rounded-lg p-3.5 border {{ $milestone->completion_percentage >= 100 ? 'border-emerald-500/40 bg-emerald-950/10' : ($milestone->completion_percentage > 0 ? 'border-amber-500/40 bg-amber-950/10' : 'border-slate-700/50') }}">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[11px] font-mono text-slate-400">#0{{ $milestone->order_index }}</span>
                            @if($milestone->is_verified)
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-400 bg-emerald-950/60 px-1.5 py-0.5 rounded border border-emerald-800">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                    Audited
                                </span>
                            @else
                                <span class="text-[10px] text-slate-500">In Progress</span>
                            @endif
                        </div>

                        <h3 class="text-xs font-semibold text-slate-200 line-clamp-1 mb-0.5" title="{{ $milestone->name_en }}">
                            {{ $milestone->name_en }}
                        </h3>
                        @if($milestone->name_am)
                            <p class="text-[10px] text-slate-400 font-['Noto_Sans_Ethiopic'] line-clamp-1 mb-2">
                                {{ $milestone->name_am }}
                            </p>
                        @endif

                        <div class="flex items-baseline justify-between text-xs mb-1">
                            <span class="text-slate-400">Weight: <strong>{{ number_format((float) $milestone->progress_weight, 0) }}%</strong></span>
                            <span class="font-bold {{ $milestone->completion_percentage >= 100 ? 'text-emerald-400' : ($milestone->completion_percentage > 0 ? 'text-amber-400' : 'text-slate-500') }}">
                                {{ number_format((float) $milestone->completion_percentage, 0) }}%
                            </span>
                        </div>

                        <div class="w-full bg-slate-700/60 h-1.5 rounded-full overflow-hidden">
                            <div class="h-full rounded-full {{ $milestone->completion_percentage >= 100 ? 'bg-emerald-500' : ($milestone->completion_percentage > 0 ? 'bg-amber-400' : 'bg-slate-600') }}"
                                 style="width: {{ $milestone->completion_percentage }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<!-- Main Interactive Body: Inventory Stacking Matrix Explorer -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10"
     x-data="projectMatrix({
        projectId: {{ $project->id }},
        buildings: {{ json_encode($project->buildings->map(fn($b) => [
            'id' => $b->id,
            'code' => $b->building_code,
            'name' => $b->name_en,
            'floors_count' => $b->floors_count,
            'floors' => $b->floors->sortByDesc('floor_number')->map(fn($f) => [
                'id' => $f->id,
                'floor_number' => $f->floor_number,
                'floor_label' => $f->floor_label,
                'units' => $f->units->map(fn($u) => [
                    'id' => $u->id,
                    'unit_number' => $u->unit_number,
                    'floor_number' => $u->floor_number,
                    'unit_type' => $u->unit_type,
                    'area_sqm' => (float) $u->area_sqm,
                    'bedrooms' => $u->bedrooms,
                    'bathrooms' => $u->bathrooms,
                    'price_etb' => (float) $u->price_etb,
                    'price_per_sqm_etb' => (float) $u->price_per_sqm_etb,
                    'orientation' => $u->orientation,
                    'parking_allocation' => $u->parking_allocation,
                    'status' => $u->status->value,
                ]),
            ]),
        ])) }},
        defaultPlan: {{ json_encode($defaultPlan ? [
            'id' => $defaultPlan->id,
            'name' => $defaultPlan->name_en,
            'installments' => $defaultPlan->installments->map(fn($i) => [
                'seq' => $i->sequence_number,
                'label' => $i->milestone_label_en,
                'pct' => (float) $i->percentage,
            ]),
        ] : null) }},
        paymentAccounts: {{ json_encode($paymentAccounts->map(fn($a) => [
            'id' => $a->id,
            'label' => $a->account_label,
            'institution' => $a->institution_name,
            'holder' => $a->account_holder_name,
            'number' => $a->account_number,
            'branch' => $a->branch_name,
            'currency' => $a->currency,
            'instructions' => $a->transfer_instructions,
        ])->values()) }}
     })">

    <div class="flex flex-col lg:flex-row gap-8">

        <!-- Left/Main Column: Stacking Matrix Controls & Grid -->
        <div class="lg:w-2/3">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6">
                <!-- Stacking Matrix Controls Bar -->
                <div class="flex flex-wrap items-center justify-between gap-4 pb-6 border-b border-slate-100">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">Unit Stacking Matrix</h2>
                        <p class="text-xs text-slate-500">Live inventory floor-by-floor architectural distribution</p>
                    </div>

                    <!-- Building Switcher Tabs -->
                    <div class="flex rounded-lg bg-slate-100 p-1">
                        <template x-for="building in buildings" :key="building.id">
                            <button
                                type="button"
                                @click="activeBuildingId = building.id"
                                :class="activeBuildingId === building.id ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-600 hover:text-slate-900 font-medium'"
                                class="px-4 py-1.5 rounded-md text-xs transition"
                                x-text="building.name">
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Legend & Filters -->
                <div class="flex flex-wrap items-center justify-between gap-4 py-4 border-b border-slate-100 text-xs">
                    <!-- Status Legend -->
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center gap-1.5 font-medium text-slate-700">
                            <span class="w-3 h-3 rounded bg-emerald-500 border border-emerald-600"></span> Available
                        </span>
                        <span class="inline-flex items-center gap-1.5 font-medium text-slate-700">
                            <span class="w-3 h-3 rounded bg-amber-500 border border-amber-600"></span> Reserved (Hold)
                        </span>
                        <span class="inline-flex items-center gap-1.5 font-medium text-slate-700">
                            <span class="w-3 h-3 rounded bg-blue-600 border border-blue-700"></span> Contracted
                        </span>
                        <span class="inline-flex items-center gap-1.5 font-medium text-slate-700">
                            <span class="w-3 h-3 rounded bg-slate-400 border border-slate-500"></span> Sold
                        </span>
                    </div>

                    <!-- Type Filter -->
                    <div class="flex items-center gap-2">
                        <label for="typeFilter" class="text-slate-500 font-medium">Type:</label>
                        <select id="typeFilter" x-model="filterType" class="text-xs rounded-md border-slate-300 py-1 pl-2 pr-6">
                            <option value="all">All Bed Types</option>
                            <option value="1br">1 Bedroom</option>
                            <option value="2br">2 Bedrooms</option>
                            <option value="3br">3 Bedrooms</option>
                            <option value="penthouse">Penthouse</option>
                        </select>
                    </div>
                </div>

                <!-- Stacking Elevation Grid -->
                <div class="mt-6 space-y-3">
                    <template x-for="floor in currentFloors" :key="floor.id">
                        <div class="flex items-center gap-3">
                            <!-- Floor Label Badge -->
                            <div class="w-20 shrink-0 text-right pr-2">
                                <span class="inline-block px-2 py-1 rounded bg-slate-100 text-slate-700 font-bold text-xs font-mono" x-text="floor.floor_label"></span>
                            </div>

                            <!-- Floor Units Strip -->
                            <div class="flex-1 grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                <template x-for="unit in floor.units" :key="unit.id">
                                    <button
                                        type="button"
                                        @click="selectUnit(unit)"
                                        :disabled="filterType !== 'all' && unit.unit_type !== filterType"
                                        :class="{
                                            'opacity-20 cursor-not-allowed': filterType !== 'all' && unit.unit_type !== filterType,
                                            'ring-2 ring-slate-900 ring-offset-2': selectedUnit && selectedUnit.id === unit.id,
                                            'bg-emerald-50 border-emerald-300 text-emerald-950 hover:bg-emerald-100': unit.status === 'available',
                                            'bg-amber-50 border-amber-300 text-amber-950 hover:bg-amber-100': unit.status === 'reserved',
                                            'bg-blue-50 border-blue-300 text-blue-950 hover:bg-blue-100': unit.status === 'contracted',
                                            'bg-slate-100 border-slate-300 text-slate-500 hover:bg-slate-200': unit.status === 'sold'
                                        }"
                                        class="p-2.5 rounded-xl border text-left transition flex flex-col justify-between h-20 shadow-xs">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-xs font-mono" x-text="unit.unit_number"></span>
                                            <span class="text-[10px] uppercase font-semibold px-1 rounded"
                                                  :class="{
                                                    'bg-emerald-200 text-emerald-800': unit.status === 'available',
                                                    'bg-amber-200 text-amber-800': unit.status === 'reserved',
                                                    'bg-blue-200 text-blue-800': unit.status === 'contracted',
                                                    'bg-slate-200 text-slate-700': unit.status === 'sold'
                                                  }"
                                                  x-text="unit.status"></span>
                                        </div>
                                        <div>
                                            <div class="text-[11px] font-medium text-slate-700" x-text="unit.bedrooms + ' Bed · ' + unit.area_sqm + ' m²'"></div>
                                            <div class="text-[11px] font-bold text-slate-900" x-text="formatEtb(unit.price_etb) + ' ETB'"></div>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Project Master Facilities & Specs Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-base font-bold text-slate-900 mb-3">Masterplan Infrastructure & Amenities</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach($project->facilities_json ?? [] as $facility)
                        <div class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-50 text-slate-700 text-xs font-medium border border-slate-100">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            {{ $facility }}
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Site Plan & Architectural Design Gallery (Modules 22 & 23) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mt-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Project Master Site Plan & Design Previews</h3>
                        <p class="text-xs text-slate-500">Verified architectural layout and illustrative renderings</p>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-md bg-slate-100 text-slate-700">
                        {{ $project->land_area_sqm ? number_format((float) $project->land_area_sqm, 2) . ' m² Land Parcel' : 'Addis Ababa Masterplan' }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Site Plan Preview Card -->
                    <div class="rounded-xl border border-slate-200 overflow-hidden bg-slate-50">
                        <div class="h-44 bg-gradient-to-br from-slate-800 to-slate-900 flex flex-col items-center justify-center p-4 text-center relative">
                            <svg class="w-12 h-12 text-amber-400 mb-2 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                            <span class="text-xs font-bold text-white uppercase tracking-wider">Master Site Plan Blueprint</span>
                            <span class="text-[11px] text-slate-400 mt-1">Ground footprint, road access & building orientation</span>
                            <span class="absolute top-2 left-2 px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                Site Plan Drawing
                            </span>
                        </div>
                        <div class="p-3 bg-white text-xs text-slate-600 border-t border-slate-100 flex items-center justify-between">
                            <span>Permit Ref: {{ $project->building_permit_number ?? 'AA/CON/BP-2025' }}</span>
                            <span class="text-[11px] font-semibold text-slate-800">Physical Site Layout</span>
                        </div>
                    </div>

                    <!-- Building Architectural Render Card -->
                    <div class="rounded-xl border border-slate-200 overflow-hidden bg-slate-50">
                        <div class="h-44 bg-gradient-to-br from-indigo-950 to-slate-900 flex flex-col items-center justify-center p-4 text-center relative">
                            <svg class="w-12 h-12 text-indigo-400 mb-2 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            <span class="text-xs font-bold text-white uppercase tracking-wider">Architectural 3D Render</span>
                            <span class="text-[11px] text-slate-400 mt-1">Proposed exterior elevation & facade design</span>
                            <span class="absolute top-2 left-2 px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                Illustrative Render (Not Completed Photo)
                            </span>
                        </div>
                        <div class="p-3 bg-white text-xs text-slate-600 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-slate-500">Proposed Concept</span>
                            <span class="text-[11px] font-semibold text-amber-700">Eurocode 8 Seismic Standards</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Selected Unit Specification & Reservation Action Drawer -->
        <div class="lg:w-1/3">
            <div class="sticky top-20 bg-white rounded-2xl shadow-md border border-slate-200 p-6" x-show="selectedUnit" x-transition>
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div>
                        <span class="text-xs uppercase font-mono tracking-wider text-slate-400">Selected Unit</span>
                        <h3 class="text-2xl font-bold text-slate-900" x-text="selectedUnit ? selectedUnit.unit_number : ''"></h3>
                    </div>
                    <span class="text-xs uppercase font-bold px-2.5 py-1 rounded-full"
                          :class="{
                            'bg-emerald-100 text-emerald-800': selectedUnit && selectedUnit.status === 'available',
                            'bg-amber-100 text-amber-800': selectedUnit && selectedUnit.status === 'reserved',
                            'bg-blue-100 text-blue-800': selectedUnit && selectedUnit.status === 'contracted',
                            'bg-slate-100 text-slate-700': selectedUnit && selectedUnit.status === 'sold'
                          }"
                          x-text="selectedUnit ? selectedUnit.status : ''"></span>
                </div>

                <!-- Unit Specification Metrics -->
                <div class="grid grid-cols-2 gap-3 my-5">
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="text-[11px] text-slate-500">Gross Area</span>
                        <div class="text-base font-bold text-slate-900" x-text="selectedUnit ? selectedUnit.area_sqm + ' m²' : ''"></div>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="text-[11px] text-slate-500">Floor Elevation</span>
                        <div class="text-base font-bold text-slate-900" x-text="selectedUnit ? 'Floor ' + selectedUnit.floor_number : ''"></div>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="text-[11px] text-slate-500">Bedrooms & Baths</span>
                        <div class="text-base font-bold text-slate-900" x-text="selectedUnit ? selectedUnit.bedrooms + ' Bed / ' + selectedUnit.bathrooms + ' Bath' : ''"></div>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="text-[11px] text-slate-500">Parking Bays</span>
                        <div class="text-base font-bold text-slate-900" x-text="selectedUnit ? selectedUnit.parking_allocation + ' Reserved' : ''"></div>
                    </div>
                </div>

                <!-- Price Breakdown -->
                <div class="p-4 rounded-xl bg-slate-900 text-white mb-6">
                    <span class="text-xs uppercase tracking-wider text-slate-400">Total Purchase Price</span>
                    <div class="text-2xl font-extrabold text-amber-400 my-1" x-text="selectedUnit ? formatEtb(selectedUnit.price_etb) + ' ETB' : ''"></div>
                    <div class="text-xs text-slate-300 flex justify-between pt-2 border-t border-slate-800">
                        <span>Price per Sqm:</span>
                        <strong x-text="selectedUnit ? formatEtb(selectedUnit.price_per_sqm_etb) + ' ETB/m²' : ''"></strong>
                    </div>
                </div>

                <!-- Milestone Payment Schedule Simulator -->
                <div class="mb-6">
                    <h4 class="text-xs uppercase font-bold text-slate-700 tracking-wider mb-2">Milestone Payment Plan (100%)</h4>
                    <div class="space-y-2 border border-slate-100 rounded-xl p-3 bg-slate-50/50">
                        <template x-for="item in defaultPlan ? defaultPlan.installments : []" :key="item.seq">
                            <div class="flex items-center justify-between text-xs py-1 border-b border-slate-100 last:border-0">
                                <span class="text-slate-600 truncate mr-2" x-text="item.seq + '. ' + item.label"></span>
                                <div class="text-right shrink-0">
                                    <span class="font-semibold text-slate-900" x-text="formatEtb(calculateInstallmentAmount(item.pct)) + ' ETB'"></span>
                                    <span class="text-[10px] text-slate-500" x-text="' (' + item.pct + '%)'"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Action Button -->
                <template x-if="selectedUnit && selectedUnit.status === 'available'">
                    <button
                        type="button"
                        @click="openReservationModal()"
                        class="w-full py-3.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-md hover:shadow-lg transition flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        <span>Reserve Unit (15-Min Hold)</span>
                    </button>
                </template>

                <template x-if="selectedUnit && selectedUnit.status !== 'available'">
                    <div class="w-full py-3 px-4 rounded-xl bg-slate-100 text-slate-500 font-semibold text-sm text-center">
                        Unit is currently <span class="uppercase" x-text="selectedUnit.status"></span>
                    </div>
                </template>
            </div>

            <!-- Empty State Prompt -->
            <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-8 text-center text-slate-500" x-show="!selectedUnit">
                <svg class="w-12 h-12 mx-auto text-slate-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                <h4 class="text-sm font-semibold text-slate-700 mb-1">Select a Unit on the Matrix</h4>
                <p class="text-xs text-slate-500">Click any available unit cell in the elevation grid to preview floor plans, pricing breakdown, and initiate a reservation hold.</p>
            </div>
        </div>

    </div>

    <!-- Modal: Concurrency-Safe Unit Reservation & Hold Window (SRS Section 94 & 156) -->
    <div x-show="reservationModalOpen"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-4">

        <div class="bg-white rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-6 sm:p-8 shadow-2xl border border-slate-200 relative my-auto"
             @click.away="if (!reservationActive) reservationModalOpen = false">

            <!-- Close button -->
            <button
                type="button"
                @click="reservationModalOpen = false"
                class="absolute top-4 right-4 text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>

            <!-- State 1: Hold Placement Form -->
            <div x-show="!reservationActive && !contractConverted">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Lock Unit Reservation Hold</h3>
                        <p class="text-xs text-slate-500">Atomic 15-minute temporary hold with double-booking prevention</p>
                    </div>
                </div>

                <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 mb-5 space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Unit:</span>
                        <strong class="text-slate-900" x-text="selectedUnit ? selectedUnit.unit_number + ' (' + selectedUnit.bedrooms + ' Bed · ' + selectedUnit.area_sqm + ' m²)' : ''"></strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Agreed Price:</span>
                        <strong class="text-slate-900" x-text="selectedUnit ? formatEtb(selectedUnit.price_etb) + ' ETB' : ''"></strong>
                    </div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-emerald-800">
                        <span class="font-medium">10% Reservation Deposit:</span>
                        <strong class="text-sm font-bold" x-text="selectedUnit ? formatEtb(selectedUnit.price_etb * 0.10) + ' ETB' : ''"></strong>
                    </div>
                </div>

                <div class="mb-5">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Hold Duration Window</label>
                    <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>You have <strong>15 minutes</strong> to secure your initial reservation before the unit automatically releases back to public inventory.</span>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="flex items-start gap-2.5 text-xs text-slate-600 cursor-pointer">
                        <input type="checkbox" x-model="statutoryConsent" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span>
                            I acknowledge that this reservation hold establishes a temporary priority lock under Ethiopian law (Proclamation No. 1357/2024) and must be converted to an executed contract within the required period.
                        </span>
                    </label>
                </div>

                <div x-show="errorMessage" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg" x-text="errorMessage"></div>

                <button
                    type="button"
                    @click="submitReservation()"
                    :disabled="submitting || !statutoryConsent"
                    :class="submitting || !statutoryConsent ? 'opacity-50 cursor-not-allowed' : 'hover:bg-emerald-700'"
                    class="w-full py-3 rounded-xl bg-emerald-600 text-white font-bold text-sm shadow transition flex items-center justify-center gap-2">
                    <span x-text="submitting ? 'Securing Row Lock...' : 'Confirm 15-Minute Hold'"></span>
                </button>
            </div>

            <!-- State 2: Active Reservation Hold with Live Countdown Timer -->
            <div x-show="reservationActive && !contractConverted">
                <div class="text-center mb-6">
                    <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <span class="inline-block px-2.5 py-0.5 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800 uppercase tracking-wide mb-1">
                        Priority Hold Active
                    </span>
                    <h3 class="text-xl font-bold text-slate-900" x-text="activeReservation?.reservation_code"></h3>
                    <p class="text-xs text-slate-500">PostgreSQL partial unique constraint lock secured</p>
                </div>

                <!-- Timer Component -->
                <div class="p-4 rounded-xl bg-slate-900 text-center text-white mb-6">
                    <span class="text-xs text-slate-400 uppercase tracking-wider">Remaining Hold Time</span>
                    <div class="text-4xl font-extrabold font-mono text-amber-400 my-1" x-text="countdownFormatted"></div>
                    <span class="text-xs text-slate-400">Inventory locked against all other concurrent buyers</span>
                </div>

                <!-- Statutory Disclosures Consent (Proclamation No. 1357/2024 Section 95) -->
                <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 mb-6">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">Off-Plan Statutory Disclosures</h4>
                    <p class="text-xs text-slate-600 mb-3">
                        Per Federal Real Estate Development & Marketing Proclamation No. 1357/2024, the developer confirms valid building permit <strong>{{ $project->building_permit_number ?? 'AA/CON/BP-2025-001429' }}</strong>, designated developer project bank account, and verified construction progress ({{ number_format($overallProgress, 2) }}%).
                    </p>
                    <label class="flex items-start gap-2 text-xs text-slate-700 cursor-pointer">
                        <input type="checkbox" x-model="contractDisclosureAccepted" class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="font-medium">
                            I explicitly accept the statutory terms and agree to convert this reservation into a legally binding developer sales contract with the itemized installment schedule.
                        </span>
                    </label>
                </div>

                <div x-show="errorMessage" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg" x-text="errorMessage"></div>

                <div class="flex gap-3">
                    <button
                        type="button"
                        @click="convertToContract()"
                        :disabled="submitting || !contractDisclosureAccepted"
                        :class="submitting || !contractDisclosureAccepted ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-700'"
                        class="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold text-sm shadow transition">
                        <span x-text="submitting ? 'Executing Contract...' : 'Convert to Sales Contract'"></span>
                    </button>
                    <button
                        type="button"
                        @click="cancelReservation()"
                        class="px-4 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition">
                        Cancel Hold
                    </button>
                </div>
            </div>

            <!-- State 3: Contract Successfully Executed -->
            <div x-show="contractConverted">
                <div class="text-center mb-6">
                    <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <span class="inline-block px-2.5 py-0.5 text-xs font-bold rounded-full bg-blue-100 text-blue-800 uppercase tracking-wide mb-1">
                        Contract Executed
                    </span>
                    <h3 class="text-xl font-bold text-slate-900" x-text="contractData?.contract_code"></h3>
                    <p class="text-xs text-slate-500">Proclamation No. 1357/2024 Compliant Off-Plan Contract</p>
                </div>

                <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 text-xs space-y-2 mb-4">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Contract Total:</span>
                        <strong class="text-slate-900" x-text="formatEtb(contractData?.contract_price_etb) + ' ETB'"></strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Deposit Paid (Settled):</span>
                        <strong class="text-emerald-700" x-text="formatEtb(contractData?.deposit_paid_etb) + ' ETB'"></strong>
                    </div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 font-bold">
                        <span class="text-slate-700">Balance Payable:</span>
                        <span class="text-slate-900" x-text="formatEtb(contractData?.balance_payable_etb) + ' ETB'"></span>
                    </div>
                    <div class="flex justify-between text-slate-500 text-[11px]">
                        <span>Installments Schedule:</span>
                        <span x-text="contractData?.installments_count + ' Milestone Tranches'"></span>
                    </div>
                </div>

                <!-- Two Payment Options (Option A: Gateway, Option B: Developer Accounts) -->
                <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/70 mb-5 text-left">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-2">Installment Payment Options</h4>
                    
                    <div class="flex gap-2 mb-3">
                        <button type="button" @click="paymentOption = 'provider'"
                                :class="paymentOption === 'provider' ? 'bg-white border-blue-600 text-blue-700 shadow-xs' : 'bg-slate-100 text-slate-600 border-transparent'"
                                class="flex-1 py-1.5 px-2 rounded-lg text-xs font-semibold border transition text-center">
                            Option A: Digital Gateway
                        </button>
                        <button type="button" @click="paymentOption = 'manual'"
                                :class="paymentOption === 'manual' ? 'bg-white border-blue-600 text-blue-700 shadow-xs' : 'bg-slate-100 text-slate-600 border-transparent'"
                                class="flex-1 py-1.5 px-2 rounded-lg text-xs font-semibold border transition text-center">
                            Option B: Manual Bank Transfer to Developer
                        </button>
                    </div>

                    <!-- Option A Content -->
                    <div x-show="paymentOption === 'provider'" class="space-y-2 text-xs">
                        <p class="text-slate-600 text-[11px]">Instant settlement via licensed Ethiopian payment operators:</p>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="p-2 border border-slate-200 rounded-lg bg-white text-center font-bold text-slate-700 text-xs">Telebirr</div>
                            <div class="p-2 border border-slate-200 rounded-lg bg-white text-center font-bold text-slate-700 text-xs">CBE Birr</div>
                            <div class="p-2 border border-slate-200 rounded-lg bg-white text-center font-bold text-slate-700 text-xs">Chapa</div>
                        </div>
                    </div>

                    <!-- Option B Content -->
                    <div x-show="paymentOption === 'manual'" class="space-y-3 text-xs">
                        <p class="text-slate-600 text-[11px]">Direct transfer to developer's verified project accounts:</p>
                        
                        <template x-if="paymentAccounts && paymentAccounts.length > 0">
                            <div class="space-y-2">
                                <template x-for="acc in paymentAccounts" :key="acc.id">
                                    <div class="p-2.5 rounded-lg border border-slate-200 bg-white">
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="font-bold text-slate-900" x-text="acc.institution"></span>
                                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 font-semibold border border-emerald-200" x-text="acc.currency"></span>
                                        </div>
                                        <div class="text-[11px] text-slate-600 font-mono" x-text="'Account: ' + acc.number"></div>
                                        <div class="text-[10px] text-slate-500" x-text="'Holder: ' + acc.holder"></div>
                                        <template x-if="acc.branch">
                                            <div class="text-[10px] text-slate-500" x-text="'Branch: ' + acc.branch"></div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="!paymentAccounts || paymentAccounts.length === 0">
                            <div class="p-3 bg-slate-100 rounded-lg border border-slate-200 text-slate-600 text-[11px]">
                                <span class="font-bold text-slate-800">No bank accounts configured:</span>
                                Official payment instructions have not yet been registered by the developer for this project. Please contact the developer office or use digital gateway settlement.
                            </div>
                        </template>

                        <div class="pt-2 border-t border-slate-200">
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1">Deposit Slip / Bank Reference</label>
                            <input type="text" x-model="manualTransferRef" placeholder="e.g. CBE-TX-2026-908123" class="w-full text-xs rounded-lg border-slate-300 py-1.5 px-2.5">
                            <button type="button" @click="submitManualPaymentProof()" :disabled="submitting || !manualTransferRef || !paymentAccounts || paymentAccounts.length === 0"
                                    :class="submitting || !manualTransferRef || !paymentAccounts || paymentAccounts.length === 0 ? 'opacity-50 cursor-not-allowed' : ''"
                                    class="w-full mt-2 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs shadow-xs transition">
                                Submit Transfer Proof for Developer Verification
                            </button>
                            <div x-show="paymentSubmittedMessage" class="mt-2 p-2 bg-emerald-50 border border-emerald-200 text-emerald-700 text-[11px] rounded" x-text="paymentSubmittedMessage"></div>
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    @click="reservationModalOpen = false; window.location.reload()"
                    class="w-full py-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm shadow transition">
                    Done & Refresh Inventory
                </button>
            </div>

        </div>
    </div>

</div>

<!-- Alpine Component Script for Concurrency-Safe Stacking Matrix -->
<script>
function projectMatrix(config) {
    return {
        projectId: config.projectId,
        buildings: config.buildings || [],
        defaultPlan: config.defaultPlan,
        paymentAccounts: config.paymentAccounts || [],
        paymentOption: 'manual',
        manualTransferRef: '',
        paymentSubmittedMessage: null,
        activeBuildingId: config.buildings[0]?.id || null,
        filterType: 'all',
        selectedUnit: null,
        reservationModalOpen: false,
        statutoryConsent: false,
        contractDisclosureAccepted: false,
        submitting: false,
        errorMessage: null,
        reservationActive: false,
        activeReservation: null,
        countdownSeconds: 900, // 15 minutes
        countdownFormatted: '15:00',
        timerInterval: null,
        contractConverted: false,
        contractData: null,

        get currentBuilding() {
            return this.buildings.find(b => b.id === this.activeBuildingId) || this.buildings[0];
        },

        get currentFloors() {
            return this.currentBuilding ? this.currentBuilding.floors : [];
        },

        selectUnit(unit) {
            this.selectedUnit = unit;
        },

        formatEtb(amount) {
            if (!amount && amount !== 0) return '0.00';
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount);
        },

        calculateInstallmentAmount(pct) {
            if (!this.selectedUnit) return 0;
            return (this.selectedUnit.price_etb * pct) / 100.0;
        },

        openReservationModal() {
            this.errorMessage = null;
            this.statutoryConsent = false;
            this.contractDisclosureAccepted = false;
            this.reservationActive = false;
            this.contractConverted = false;
            this.reservationModalOpen = true;
        },

        async submitReservation() {
            if (!this.selectedUnit || !this.statutoryConsent) return;
            this.submitting = true;
            this.errorMessage = null;

            const idempotencyKey = 'RES-' + this.selectedUnit.id + '-' + Date.now() + '-' + Math.random().toString(36).substring(2, 8);

            try {
                const response = await fetch(`/api/v1/units/${this.selectedUnit.id}/reserve`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        idempotency_key: idempotencyKey,
                        deposit_percentage: 10.0,
                        hold_minutes: 15
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    this.errorMessage = data.message || data.error || 'Failed to place reservation hold.';
                    this.submitting = false;
                    return;
                }

                // Reservation hold placed successfully!
                this.activeReservation = data;
                this.reservationActive = true;
                this.selectedUnit.status = 'reserved';
                this.startCountdown(900);
            } catch (err) {
                this.errorMessage = 'Network connection error while placing hold. Please try again.';
            } finally {
                this.submitting = false;
            }
        },

        startCountdown(seconds) {
            if (this.timerInterval) clearInterval(this.timerInterval);
            this.countdownSeconds = seconds;
            this.updateFormattedCountdown();

            this.timerInterval = setInterval(() => {
                this.countdownSeconds--;
                this.updateFormattedCountdown();
                if (this.countdownSeconds <= 0) {
                    clearInterval(this.timerInterval);
                    this.errorMessage = 'Reservation hold window has expired.';
                    this.reservationActive = false;
                    if (this.selectedUnit) this.selectedUnit.status = 'available';
                }
            }, 1000);
        },

        updateFormattedCountdown() {
            const m = Math.floor(this.countdownSeconds / 60);
            const s = this.countdownSeconds % 60;
            this.countdownFormatted = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        },

        async convertToContract() {
            if (!this.activeReservation || !this.contractDisclosureAccepted) return;
            this.submitting = true;
            this.errorMessage = null;

            try {
                const response = await fetch(`/api/v1/reservations/${this.activeReservation.id}/convert`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        disclosure_accepted: true,
                        disclosure_version: 'v1.0-proc1357'
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    this.errorMessage = data.message || data.error || 'Conversion failed.';
                    this.submitting = false;
                    return;
                }

                if (this.timerInterval) clearInterval(this.timerInterval);
                this.contractData = data;
                this.contractConverted = true;
                if (this.selectedUnit) this.selectedUnit.status = 'contracted';
            } catch (err) {
                this.errorMessage = 'Network connection error during contract execution.';
            } finally {
                this.submitting = false;
            }
        },

        async cancelReservation() {
            if (!this.activeReservation) return;
            if (this.timerInterval) clearInterval(this.timerInterval);

            try {
                await fetch(`/api/v1/reservations/${this.activeReservation.id}/cancel`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({ reason: 'Cancelled by user during review.' })
                });

                if (this.selectedUnit) this.selectedUnit.status = 'available';
                this.reservationActive = false;
                this.reservationModalOpen = false;
            } catch (err) {
                this.reservationModalOpen = false;
            }
        },

        async submitManualPaymentProof() {
            if (!this.contractData || !this.manualTransferRef) return;
            const installment = this.contractData.installments?.[0] || null;
            const account = this.paymentAccounts?.[0] || null;

            if (!installment || !account) {
                this.errorMessage = 'Installment or payment account details unavailable.';
                return;
            }

            this.submitting = true;
            this.errorMessage = null;

            try {
                const response = await fetch(`/api/v1/contracts/${this.contractData.id}/payments/manual`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        installment_id: installment.id,
                        payment_account_id: account.id,
                        amount_etb: installment.amount_etb || this.contractData.deposit_paid_etb || 100000,
                        transfer_reference: this.manualTransferRef,
                        notes: 'Submitted via Stacking Matrix checkout drawer.'
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    this.errorMessage = data.message || data.error || 'Failed to submit payment claim.';
                    this.submitting = false;
                    return;
                }

                this.paymentSubmittedMessage = `Payment claim recorded (${data.transaction_reference}). Pending developer verification.`;
                this.manualTransferRef = '';
            } catch (err) {
                this.errorMessage = 'Network connection error while submitting payment.';
            } finally {
                this.submitting = false;
            }
        }
    };
}
</script>
@endsection
