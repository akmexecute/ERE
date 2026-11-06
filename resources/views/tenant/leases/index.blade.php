<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8" x-data="{ tab: 'tenant' }">
        <!-- Breadcrumb & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-6">
            <div>
                <nav class="text-xs text-slate-500 mb-1 flex items-center gap-2">
                    <a href="{{ url('/dashboard') }}" class="hover:text-teal-700">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('tenant.profile') }}" class="hover:text-teal-700">Tenant Management</a>
                    <span>/</span>
                    <span class="text-slate-800 font-medium">Lease Contracts</span>
                </nav>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Lease Contracts</h1>
                <p class="text-sm text-slate-600 mt-1">Legally binding tenancy agreements, bilateral digital signatures, and inspection records.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('rental-applications.index') }}" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 text-sm font-semibold transition">
                    Rental Applications
                </a>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-4 border-b border-slate-200">
            <button
                type="button"
                @click="tab = 'tenant'"
                class="pb-3 text-sm font-semibold border-b-2 transition"
                :class="tab === 'tenant' ? 'border-teal-700 text-teal-800' : 'border-transparent text-slate-500 hover:text-slate-700'"
            >
                As Tenant ({{ $tenantLeases->count() }})
            </button>
            <button
                type="button"
                @click="tab = 'landlord'"
                class="pb-3 text-sm font-semibold border-b-2 transition"
                :class="tab === 'landlord' ? 'border-teal-700 text-teal-800' : 'border-transparent text-slate-500 hover:text-slate-700'"
            >
                As Landlord / Owner ({{ $landlordLeases->count() }})
            </button>
        </div>

        <!-- Tab 1: Tenant Leases -->
        <div x-show="tab === 'tenant'" class="space-y-4">
            @if($tenantLeases->count() > 0)
                <div class="grid grid-cols-1 gap-4">
                    @foreach($tenantLeases as $lease)
                        @php
                            $statusVal = is_object($lease->status) ? $lease->status->value : $lease->status;
                            $statusLbl = is_object($lease->status) ? $lease->status->label() : ucwords(str_replace('_', ' ', (string) $lease->status));
                        @endphp
                        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-6 hover:border-teal-500 transition">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2.5">
                                    <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                        {{ $lease->lease_code }}
                                    </span>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold
                                        @if($statusVal === 'active') bg-emerald-100 text-emerald-800
                                        @elseif($statusVal === 'pending_signatures') bg-amber-100 text-amber-800
                                        @elseif($statusVal === 'draft') bg-slate-100 text-slate-700
                                        @elseif($statusVal === 'completed') bg-blue-100 text-blue-800
                                        @else bg-red-100 text-red-800 @endif">
                                        {{ $statusLbl }}
                                    </span>
                                </div>
                                <h2 class="text-base font-bold text-slate-900">{{ $lease->property?->title ?? 'Contract Property' }}</h2>
                                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                    <span>Term: <x-calendar-date :date="$lease->start_date" /> – <x-calendar-date :date="$lease->end_date" /></span>
                                    <span>•</span>
                                    <span>Rent: <strong class="text-teal-800">{{ number_format((float) $lease->monthly_rent_etb, 2) }} ETB/mo</strong></span>
                                    <span>•</span>
                                    <span>Landlord: {{ $lease->landlord?->name ?? 'Verified Landlord' }}</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <a href="{{ route('rent.schedule', $lease->uuid) }}" class="px-3.5 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 text-xs font-semibold transition">
                                    Rent Schedule
                                </a>
                                <a href="{{ route('leases.show', $lease->uuid) }}" class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold transition">
                                    View Contract &rarr;
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl bg-white border border-dashed border-slate-300 p-12 text-center space-y-3">
                    <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-xl">📜</div>
                    <h3 class="text-sm font-bold text-slate-900">No active or past lease contracts</h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto">When your rental application is approved by a landlord and converted, your formal lease agreement will appear here.</p>
                </div>
            @endif
        </div>

        <!-- Tab 2: Landlord Leases -->
        <div x-show="tab === 'landlord'" class="space-y-4" x-cloak>
            @if($landlordLeases->count() > 0)
                <div class="grid grid-cols-1 gap-4">
                    @foreach($landlordLeases as $lease)
                        @php
                            $statusVal = is_object($lease->status) ? $lease->status->value : $lease->status;
                            $statusLbl = is_object($lease->status) ? $lease->status->label() : ucwords(str_replace('_', ' ', (string) $lease->status));
                        @endphp
                        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-6 hover:border-teal-500 transition">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2.5">
                                    <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                        {{ $lease->lease_code }}
                                    </span>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold
                                        @if($statusVal === 'active') bg-emerald-100 text-emerald-800
                                        @elseif($statusVal === 'pending_signatures') bg-amber-100 text-amber-800
                                        @elseif($statusVal === 'draft') bg-slate-100 text-slate-700
                                        @elseif($statusVal === 'completed') bg-blue-100 text-blue-800
                                        @else bg-red-100 text-red-800 @endif">
                                        {{ $statusLbl }}
                                    </span>
                                </div>
                                <h2 class="text-base font-bold text-slate-900">{{ $lease->property?->title ?? 'Contract Property' }}</h2>
                                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                    <span>Tenant: <strong class="text-slate-800">{{ $lease->tenant?->name ?? 'Registered Tenant' }}</strong></span>
                                    <span>•</span>
                                    <span>Term: <x-calendar-date :date="$lease->start_date" /> – <x-calendar-date :date="$lease->end_date" /></span>
                                    <span>•</span>
                                    <span>Rent: <strong class="text-teal-800">{{ number_format((float) $lease->monthly_rent_etb, 2) }} ETB/mo</strong></span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <a href="{{ route('rent.schedule', $lease->uuid) }}" class="px-3.5 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 text-xs font-semibold transition">
                                    Rent Schedule & Ledger
                                </a>
                                <a href="{{ route('leases.show', $lease->uuid) }}" class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold transition">
                                    View Contract &rarr;
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl bg-white border border-dashed border-slate-300 p-12 text-center space-y-3">
                    <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-xl">🏢</div>
                    <h3 class="text-sm font-bold text-slate-900">No managed leases</h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto">Convert approved tenant applications into lease contracts to track rentals and collect payments.</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
