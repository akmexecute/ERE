<x-app-layout>
    @php
        $statusVal = is_object($lease->status) ? $lease->status->value : $lease->status;
        $statusLbl = is_object($lease->status) ? $lease->status->label() : ucwords(str_replace('_', ' ', (string) $lease->status));
    @endphp
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8" x-data="{ inspectionModal: false, inspectionType: 'move_in' }">
        <!-- Breadcrumb & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-6">
            <div>
                <nav class="text-xs text-slate-500 mb-1 flex items-center gap-2">
                    <a href="{{ url('/dashboard') }}" class="hover:text-teal-700">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('leases.index') }}" class="hover:text-teal-700">Lease Contracts</a>
                    <span>/</span>
                    <span class="text-slate-800 font-medium">{{ $lease->lease_code }}</span>
                </nav>
                <div class="flex items-center gap-3">
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Contract #{{ $lease->lease_code }}</h1>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold
                        @if($statusVal === 'active') bg-emerald-100 text-emerald-800
                        @elseif($statusVal === 'pending_signatures') bg-amber-100 text-amber-800
                        @elseif($statusVal === 'draft') bg-slate-100 text-slate-700
                        @elseif($statusVal === 'completed') bg-blue-100 text-blue-800
                        @else bg-red-100 text-red-800 @endif">
                        {{ $statusLbl }}
                    </span>
                </div>
            </div>

            <!-- Header Quick Actions -->
            <div class="flex items-center gap-3">
                <a href="{{ route('rent.schedule', $lease->uuid) }}" class="px-5 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-sm font-semibold shadow-xs transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    Rent Schedule & Payments
                </a>
            </div>
        </div>

        <!-- Bilateral Signatures Alert / Status Card -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs space-y-6">
            <h2 class="text-lg font-bold text-slate-900">Bilateral Execution & Signatures</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Tenant Signature -->
                <div class="border rounded-2xl p-5 space-y-3 {{ $lease->tenant_signed_at ? 'border-emerald-200 bg-emerald-50/40' : 'border-amber-200 bg-amber-50/40' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Tenant Signature</span>
                        @if($lease->tenant_signed_at)
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">SIGNED</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">PENDING</span>
                        @endif
                    </div>
                    <div>
                        <div class="font-bold text-slate-900">{{ $lease->tenant?->name ?? 'Tenant' }}</div>
                        <div class="text-xs text-slate-500 mt-0.5">
                            @if($lease->tenant_signed_at)
                                Signed on <x-calendar-date :date="$lease->tenant_signed_at" />
                            @else
                                Awaiting digital signature
                            @endif
                        </div>
                    </div>
                    @if(!$lease->tenant_signed_at && !empty($isTenant))
                        <form method="POST" action="{{ route('leases.sign', $lease->uuid) }}">
                            @csrf
                            <button type="submit" class="w-full py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold shadow-xs transition">
                                Sign as Tenant
                            </button>
                        </form>
                    @endif
                </div>

                <!-- Landlord Signature -->
                <div class="border rounded-2xl p-5 space-y-3 {{ $lease->landlord_signed_at ? 'border-emerald-200 bg-emerald-50/40' : 'border-amber-200 bg-amber-50/40' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Landlord Signature</span>
                        @if($lease->landlord_signed_at)
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">SIGNED</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">PENDING</span>
                        @endif
                    </div>
                    <div>
                        <div class="font-bold text-slate-900">{{ $lease->landlord?->name ?? 'Landlord' }}</div>
                        <div class="text-xs text-slate-500 mt-0.5">
                            @if($lease->landlord_signed_at)
                                Signed on <x-calendar-date :date="$lease->landlord_signed_at" />
                            @else
                                Awaiting digital signature
                            @endif
                        </div>
                    </div>
                    @if(!$lease->landlord_signed_at && (!empty($isLandlord) || !empty($isStaff)))
                        <form method="POST" action="{{ route('leases.sign', $lease->uuid) }}">
                            @csrf
                            <button type="submit" class="w-full py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold shadow-xs transition">
                                Sign as Landlord
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <p class="text-[11px] text-slate-400">
                Notice: Bilateral digital signature confirms contract terms. Upon completion of both signatures, the lease is automatically activated, automated rent schedules are generated, and resource occupancy is locked in PostgreSQL.
            </p>
        </div>

        <!-- Lease Terms & Key Metadata -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs space-y-6">
            <h2 class="text-lg font-bold text-slate-900">Contractual Terms</h2>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/60">
                    <span class="text-slate-500 block">Start Date</span>
                    <span class="font-bold text-slate-900 text-sm mt-0.5 block">
                        <x-calendar-date :date="$lease->start_date" />
                    </span>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/60">
                    <span class="text-slate-500 block">End Date</span>
                    <span class="font-bold text-slate-900 text-sm mt-0.5 block">
                        <x-calendar-date :date="$lease->end_date" />
                    </span>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/60">
                    <span class="text-slate-500 block">Monthly Rent</span>
                    <span class="font-bold text-teal-800 text-sm mt-0.5 block">
                        {{ number_format((float) $lease->monthly_rent_etb, 2) }} ETB
                    </span>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/60">
                    <span class="text-slate-500 block">Security Deposit</span>
                    <span class="font-bold text-slate-900 text-sm mt-0.5 block">
                        {{ number_format((float) $lease->security_deposit_etb, 2) }} ETB
                    </span>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/60">
                    <span class="text-slate-500 block">Payment Frequency</span>
                    <span class="font-bold text-slate-900 text-sm mt-0.5 block capitalize">
                        {{ is_object($lease->payment_frequency) ? $lease->payment_frequency->label() : ucwords(str_replace('_', ' ', (string) $lease->payment_frequency)) }}
                    </span>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/60">
                    <span class="text-slate-500 block">Due Day</span>
                    <span class="font-bold text-slate-900 text-sm mt-0.5 block">Day {{ $lease->due_day_of_month }}</span>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/60 sm:col-span-2">
                    <span class="text-slate-500 block">Leased Property / Unit</span>
                    <span class="font-bold text-slate-900 text-sm mt-0.5 block truncate">
                        {{ $lease->property?->title ?? 'Target Property' }}
                        @if($lease->projectUnit)
                            (Unit #{{ $lease->projectUnit->unit_number ?? $lease->projectUnit->id }})
                        @else
                            (Whole Property)
                        @endif
                    </span>
                </div>
            </div>

            @if(!empty($lease->contract_terms))
                <div class="border-t border-slate-100 pt-4">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Terms & Conditions</h3>
                    <div class="bg-slate-50 rounded-xl p-4 text-xs text-slate-700 font-mono whitespace-pre-wrap leading-relaxed max-h-48 overflow-y-auto border border-slate-200/60">
                        {{ is_array($lease->contract_terms) ? json_encode($lease->contract_terms, JSON_PRETTY_PRINT) : $lease->contract_terms }}
                    </div>
                </div>
            @endif
        </div>

        <!-- Module 26: Move-In & Move-Out Property Inspections -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Property Condition & Inspections</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Persistent records of utility meters, room conditions, and bilateral evidence acknowledgement.</p>
                </div>

                @if(!empty($isLandlord) || !empty($isStaff))
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="inspectionModal = true; inspectionType = 'move_in'"
                            class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold transition"
                        >
                            + Move-In Inspection
                        </button>
                        <button
                            type="button"
                            @click="inspectionModal = true; inspectionType = 'move_out'"
                            class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold transition"
                        >
                            + Move-Out Inspection
                        </button>
                    </div>
                @endif
            </div>

            @if($lease->inspections && $lease->inspections->count() > 0)
                <div class="space-y-4">
                    @foreach($lease->inspections as $inspection)
                        @php
                            $inspTypeVal = is_object($inspection->inspection_type) ? $inspection->inspection_type->value : $inspection->inspection_type;
                            $inspTypeLbl = is_object($inspection->inspection_type) ? $inspection->inspection_type->label() : ucwords(str_replace('_', ' ', (string) $inspection->inspection_type));
                        @endphp
                        <div class="rounded-2xl border border-slate-200 p-5 space-y-4 bg-slate-50/50">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200/60 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider
                                        {{ $inspTypeVal === 'move_in' ? 'bg-teal-100 text-teal-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $inspTypeLbl }}
                                    </span>
                                    <span class="text-xs text-slate-500">Date: <x-calendar-date :date="$inspection->inspection_date" /></span>
                                    <span class="text-xs text-slate-400">• Inspector: {{ $inspection->inspector?->name ?? 'Inspector' }}</span>
                                </div>

                                <div class="flex items-center gap-2 text-xs">
                                    @if($inspection->isFullyAcknowledged())
                                        <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Bilaterally Acknowledged
                                        </span>
                                    @else
                                        @if(!$inspection->acknowledged_by_tenant_at && !empty($isTenant))
                                            <form method="POST" action="{{ route('inspections.acknowledge', $inspection->uuid) }}">
                                                @csrf
                                                <button type="submit" class="px-3 py-1 rounded-lg bg-teal-700 hover:bg-teal-800 text-white font-semibold transition text-xs">
                                                    Acknowledge Inspection
                                                </button>
                                            </form>
                                        @elseif(!$inspection->acknowledged_by_landlord_at && (!empty($isLandlord) || !empty($isStaff)))
                                            <form method="POST" action="{{ route('inspections.acknowledge', $inspection->uuid) }}">
                                                @csrf
                                                <button type="submit" class="px-3 py-1 rounded-lg bg-teal-700 hover:bg-teal-800 text-white font-semibold transition text-xs">
                                                    Acknowledge Inspection
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-amber-700 font-medium">Pending Party Acknowledgement</span>
                                        @endif
                                    @endif
                                </div>
                            </div>

                            <!-- Meter Readings -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                                <div class="bg-white p-3 rounded-xl border border-slate-200">
                                    <span class="text-slate-500 block">Electricity Meter</span>
                                    <span class="font-bold text-slate-900 text-sm mt-0.5 block">
                                        {{ $inspection->electricity_meter_reading !== null ? number_format((float) $inspection->electricity_meter_reading, 2) . ' kWh' : 'N/A' }}
                                    </span>
                                </div>
                                <div class="bg-white p-3 rounded-xl border border-slate-200">
                                    <span class="text-slate-500 block">Water Meter</span>
                                    <span class="font-bold text-slate-900 text-sm mt-0.5 block">
                                        {{ $inspection->water_meter_reading !== null ? number_format((float) $inspection->water_meter_reading, 2) . ' m³' : 'N/A' }}
                                    </span>
                                </div>
                                <div class="bg-white p-3 rounded-xl border border-slate-200">
                                    <span class="text-slate-500 block">Tenant Ack</span>
                                    <span class="font-medium text-slate-800 text-xs mt-0.5 block">
                                        {{ $inspection->acknowledged_by_tenant_at ? 'Confirmed' : 'Pending' }}
                                    </span>
                                </div>
                                <div class="bg-white p-3 rounded-xl border border-slate-200">
                                    <span class="text-slate-500 block">Landlord Ack</span>
                                    <span class="font-medium text-slate-800 text-xs mt-0.5 block">
                                        {{ $inspection->acknowledged_by_landlord_at ? 'Confirmed' : 'Pending' }}
                                    </span>
                                </div>
                            </div>

                            @if($inspection->notes)
                                <div class="text-xs text-slate-700 bg-white p-3 rounded-xl border border-slate-200">
                                    <span class="font-semibold text-slate-900 block mb-0.5">Notes:</span>
                                    {{ $inspection->notes }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-xl bg-slate-50 border border-dashed border-slate-200 p-6 text-center text-xs text-slate-500">
                    No inspection records entered yet. Record move-in utility meters and condition before tenant occupancy.
                </div>
            @endif
        </div>

        <!-- Inspection Recording Modal (Alpine.js) -->
        <div
            x-show="inspectionModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 flex items-center justify-center p-4"
            x-cloak
        >
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-6 shadow-xl" @click.away="inspectionModal = false">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">
                        Record <span class="capitalize" x-text="inspectionType.replace('_', ' ')"></span> Inspection
                    </h3>
                    <button type="button" @click="inspectionModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form method="POST" action="{{ route('leases.inspections.store', $lease->uuid) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="inspection_type" :value="inspectionType">

                    <div>
                        <label for="inspection_date" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Inspection Date *
                        </label>
                        <input
                            type="date"
                            id="inspection_date"
                            name="inspection_date"
                            value="{{ now()->toDateString() }}"
                            required
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
                        >
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="electricity_meter_reading" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Electricity (kWh)
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                id="electricity_meter_reading"
                                name="electricity_meter_reading"
                                placeholder="e.g. 14250.5"
                                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
                            >
                        </div>
                        <div>
                            <label for="water_meter_reading" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Water (m³)
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                id="water_meter_reading"
                                name="water_meter_reading"
                                placeholder="e.g. 340.2"
                                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="inspection_notes" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Condition Remarks / Notes
                        </label>
                        <textarea
                            id="inspection_notes"
                            name="notes"
                            rows="3"
                            placeholder="Walls painted fresh, 2 door keys delivered, bathroom fixtures operational..."
                            class="w-full rounded-xl border border-slate-300 p-3 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="inspectionModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 text-xs font-semibold">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold shadow-xs">
                            Save Inspection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
