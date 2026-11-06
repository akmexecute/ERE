<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8" x-data="{ tab: 'sent' }">
        <!-- Breadcrumb & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-6">
            <div>
                <nav class="text-xs text-slate-500 mb-1 flex items-center gap-2">
                    <a href="{{ url('/dashboard') }}" class="hover:text-teal-700">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('tenant.profile') }}" class="hover:text-teal-700">Tenant Management</a>
                    <span>/</span>
                    <span class="text-slate-800 font-medium">Rental Applications</span>
                </nav>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Rental Applications</h1>
                <p class="text-sm text-slate-600 mt-1">Review applicant requests, manage background screenings, and convert approvals to formal leases.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('rental-applications.create') }}" class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-sm font-semibold transition shadow-xs">
                    + Apply for Property
                </a>
                <a href="{{ url('/search?mode=RENT') }}" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 text-sm font-semibold transition">
                    Browse Rentals
                </a>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-4 border-b border-slate-200">
            <button
                type="button"
                @click="tab = 'sent'"
                class="pb-3 text-sm font-semibold border-b-2 transition"
                :class="tab === 'sent' ? 'border-teal-700 text-teal-800' : 'border-transparent text-slate-500 hover:text-slate-700'"
            >
                My Submitted Applications ({{ $sentApplications->count() }})
            </button>
            <button
                type="button"
                @click="tab = 'received'"
                class="pb-3 text-sm font-semibold border-b-2 transition"
                :class="tab === 'received' ? 'border-teal-700 text-teal-800' : 'border-transparent text-slate-500 hover:text-slate-700'"
            >
                Received from Prospective Tenants ({{ $receivedApplications->count() }})
            </button>
        </div>

        <!-- Tab 1: Sent Applications -->
        <div x-show="tab === 'sent'" class="space-y-4">
            @if($sentApplications->count() > 0)
                <div class="grid grid-cols-1 gap-4">
                    @foreach($sentApplications as $app)
                        @php
                            $statusVal = is_object($app->status) ? $app->status->value : $app->status;
                            $statusLbl = is_object($app->status) ? $app->status->label() : ucwords(str_replace('_', ' ', (string) $app->status));
                        @endphp
                        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-6 hover:border-teal-500 transition">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2.5">
                                    <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                        {{ $app->application_code }}
                                    </span>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold
                                        @if($statusVal === 'approved') bg-emerald-100 text-emerald-800
                                        @elseif($statusVal === 'submitted') bg-blue-100 text-blue-800
                                        @elseif($statusVal === 'document_review') bg-amber-100 text-amber-800
                                        @elseif($statusVal === 'converted_to_lease') bg-purple-100 text-purple-800
                                        @elseif($statusVal === 'rejected') bg-red-100 text-red-800
                                        @else bg-slate-100 text-slate-700 @endif">
                                        {{ $statusLbl }}
                                    </span>
                                </div>
                                <h2 class="text-base font-bold text-slate-900">{{ $app->property?->title ?? 'Property #' . $app->property_id }}</h2>
                                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                    <span>Move-in: <x-calendar-date :date="$app->desired_move_in_date" /></span>
                                    <span>•</span>
                                    <span>Term: {{ $app->lease_duration_months }} months</span>
                                    <span>•</span>
                                    <span>Proposed: <strong>{{ number_format((float) $app->proposed_monthly_rent_etb, 2) }} ETB/mo</strong></span>
                                    <span>•</span>
                                    <span>Landlord: {{ $app->landlord?->name ?? 'Verified Owner' }}</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <a href="{{ route('rental-applications.show', $app->uuid) }}" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold transition">
                                    View Application &rarr;
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl bg-white border border-dashed border-slate-300 p-12 text-center space-y-3">
                    <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-xl">📄</div>
                    <h3 class="text-sm font-bold text-slate-900">No rental applications submitted</h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto">Browse verified rental apartments, villas, and commercial spaces on the national marketplace and submit your application.</p>
                    <div class="flex items-center justify-center gap-3 pt-2">
                        <a href="{{ route('rental-applications.create') }}" class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold transition">
                            Apply for Tenancy
                        </a>
                        <a href="{{ url('/search?mode=RENT') }}" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-semibold transition">
                            Browse Rentals
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <!-- Tab 2: Received Applications -->
        <div x-show="tab === 'received'" class="space-y-4" x-cloak>
            @if($receivedApplications->count() > 0)
                <div class="grid grid-cols-1 gap-4">
                    @foreach($receivedApplications as $app)
                        @php
                            $statusVal = is_object($app->status) ? $app->status->value : $app->status;
                            $statusLbl = is_object($app->status) ? $app->status->label() : ucwords(str_replace('_', ' ', (string) $app->status));
                        @endphp
                        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-6 hover:border-teal-500 transition">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2.5">
                                    <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                        {{ $app->application_code }}
                                    </span>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold
                                        @if($statusVal === 'approved') bg-emerald-100 text-emerald-800
                                        @elseif($statusVal === 'submitted') bg-blue-100 text-blue-800
                                        @elseif($statusVal === 'document_review') bg-amber-100 text-amber-800
                                        @elseif($statusVal === 'converted_to_lease') bg-purple-100 text-purple-800
                                        @elseif($statusVal === 'rejected') bg-red-100 text-red-800
                                        @else bg-slate-100 text-slate-700 @endif">
                                        {{ $statusLbl }}
                                    </span>
                                </div>
                                <h2 class="text-base font-bold text-slate-900">{{ $app->property?->title ?? 'Property #' . $app->property_id }}</h2>
                                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                    <span>Applicant: <strong class="text-slate-800">{{ $app->tenant?->name ?? 'Prospective Tenant' }}</strong></span>
                                    <span>•</span>
                                    <span>Move-in: <x-calendar-date :date="$app->desired_move_in_date" /></span>
                                    <span>•</span>
                                    <span>Offered: <strong>{{ number_format((float) $app->proposed_monthly_rent_etb, 2) }} ETB/mo</strong></span>
                                    <span>•</span>
                                    <span>Occupants: {{ $app->occupant_count }}</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <a href="{{ route('rental-applications.show', $app->uuid) }}" class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold transition">
                                    Review Application &rarr;
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl bg-white border border-dashed border-slate-300 p-12 text-center space-y-3">
                    <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-xl">📥</div>
                    <h3 class="text-sm font-bold text-slate-900">No applications received</h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto">When prospective tenants apply for your rental properties or development units, their applications will be listed here for screening.</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
