<x-app-layout>
    @php
        $statusVal = is_object($application->status) ? $application->status->value : $application->status;
        $statusLbl = is_object($application->status) ? $application->status->label() : ucwords(str_replace('_', ' ', (string) $application->status));
    @endphp
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8" x-data="{ reviewModal: false, decision: 'approve' }">
        <!-- Breadcrumb & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-6">
            <div>
                <nav class="text-xs text-slate-500 mb-1 flex items-center gap-2">
                    <a href="{{ url('/dashboard') }}" class="hover:text-teal-700">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('rental-applications.index') }}" class="hover:text-teal-700">Rental Applications</a>
                    <span>/</span>
                    <span class="text-slate-800 font-medium">{{ $application->application_code }}</span>
                </nav>
                <div class="flex items-center gap-3">
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Application #{{ $application->application_code }}</h1>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold
                        @if($statusVal === 'approved') bg-emerald-100 text-emerald-800
                        @elseif($statusVal === 'submitted') bg-blue-100 text-blue-800
                        @elseif($statusVal === 'document_review') bg-amber-100 text-amber-800
                        @elseif($statusVal === 'converted_to_lease') bg-purple-100 text-purple-800
                        @elseif($statusVal === 'rejected') bg-red-100 text-red-800
                        @else bg-slate-100 text-slate-700 @endif">
                        {{ $statusLbl }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">Submitted on {{ $application->created_at?->format('M d, Y H:i') ?? 'Recent' }}</p>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap items-center gap-3">
                @if($statusVal === 'approved' && !empty($isLandlordOrReviewer) && !$application->converted_lease_id)
                    <form method="POST" action="{{ route('rental-applications.convert', $application->uuid) }}">
                        @csrf
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-purple-700 hover:bg-purple-800 text-white text-sm font-semibold shadow-xs transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Convert to Formal Lease
                        </button>
                    </form>
                @endif

                @if($application->convertedLease)
                    <a href="{{ route('leases.show', $application->convertedLease->uuid) }}" class="px-4 py-2.5 rounded-xl bg-purple-100 hover:bg-purple-200 text-purple-900 text-sm font-semibold transition">
                        View Generated Lease &rarr;
                    </a>
                @endif

                @if(!empty($isLandlordOrReviewer) && in_array($statusVal, ['submitted', 'document_review']))
                    <button
                        type="button"
                        @click="reviewModal = true; decision = 'approve'"
                        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs transition"
                    >
                        Approve
                    </button>
                    <button
                        type="button"
                        @click="reviewModal = true; decision = 'document_review'"
                        class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shadow-xs transition"
                    >
                        Request Documents
                    </button>
                    <button
                        type="button"
                        @click="reviewModal = true; decision = 'reject'"
                        class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-semibold shadow-xs transition"
                    >
                        Reject
                    </button>
                @endif

                @if(auth()->id() === $application->tenant_id && in_array($statusVal, ['submitted', 'document_review']))
                    <form method="POST" action="{{ route('rental-applications.review', $application->uuid) }}">
                        @csrf
                        <input type="hidden" name="decision" value="withdraw">
                        <button type="submit" onclick="return confirm('Are you sure you want to withdraw this application?')" class="px-4 py-2 rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-semibold transition">
                            Withdraw Application
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <!-- Application Review Banner / Rejection Notice -->
        @if($statusVal === 'rejected' && $application->rejection_reason)
            <div class="rounded-2xl bg-red-50 border border-red-200 p-5 text-red-900 text-sm space-y-1">
                <div class="font-bold flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Application Rejected
                </div>
                <p class="text-xs text-red-800">Reason: {{ $application->rejection_reason }}</p>
                @if($application->reviewed_at)
                    <p class="text-[11px] text-red-600">Reviewed on {{ $application->reviewed_at->format('M d, Y') }}</p>
                @endif
            </div>
        @elseif($statusVal === 'approved')
            <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-5 text-emerald-900 text-sm space-y-1">
                <div class="font-bold flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Application Approved
                </div>
                <p class="text-xs text-emerald-800">
                    The landlord has approved your rental proposal.
                    @if($application->converted_lease_id)
                        A formal lease contract has been generated.
                    @else
                        Next step: The landlord or manager will generate the official lease contract.
                    @endif
                </p>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Left 2 Cols: Terms & Applicant Information -->
            <div class="md:col-span-2 space-y-8">
                <!-- Proposed Lease Terms -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs space-y-6">
                    <h2 class="text-lg font-bold text-slate-900">Proposed Tenancy Terms</h2>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200/60">
                            <span class="text-slate-500 block">Move-in Date</span>
                            <span class="font-bold text-slate-900 text-sm mt-0.5 block">
                                <x-calendar-date :date="$application->desired_move_in_date" />
                            </span>
                        </div>
                        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200/60">
                            <span class="text-slate-500 block">Duration</span>
                            <span class="font-bold text-slate-900 text-sm mt-0.5 block">{{ $application->lease_duration_months }} Months</span>
                        </div>
                        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200/60">
                            <span class="text-slate-500 block">Offered Rent</span>
                            <span class="font-bold text-teal-800 text-sm mt-0.5 block">{{ number_format((float) $application->proposed_monthly_rent_etb, 2) }} ETB</span>
                        </div>
                        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200/60">
                            <span class="text-slate-500 block">Frequency</span>
                            <span class="font-bold text-slate-900 text-sm mt-0.5 block capitalize">{{ is_object($application->payment_frequency) ? $application->payment_frequency->label() : ucwords(str_replace('_', ' ', (string) $application->payment_frequency)) }}</span>
                        </div>
                    </div>

                    <div class="text-xs text-slate-600">
                        <span class="font-medium text-slate-700">Planned Occupants:</span>
                        <span class="ml-1 font-semibold text-slate-900">{{ $application->occupant_count }} person(s)</span>
                    </div>

                    @if($application->applicant_message)
                        <div class="border-t border-slate-100 pt-4">
                            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Message from Applicant</h3>
                            <p class="text-sm text-slate-700 bg-slate-50 p-4 rounded-xl border border-slate-200/60 leading-relaxed italic">
                                "{{ $application->applicant_message }}"
                            </p>
                        </div>
                    @endif
                </div>

                <!-- Tenant Profile & Screening Details -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-slate-900">Applicant Background</h2>
                        @if($application->tenantProfile?->identity_verified)
                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">
                                Verified Identity
                            </span>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div class="border border-slate-100 rounded-xl p-3">
                            <span class="text-slate-500 block">Applicant Name</span>
                            <span class="font-bold text-slate-900 text-sm">{{ $application->tenant?->name ?? 'Applicant' }}</span>
                            <span class="text-slate-500 block mt-0.5">{{ $application->tenant?->phone ?? 'No phone' }}</span>
                        </div>
                        <div class="border border-slate-100 rounded-xl p-3">
                            <span class="text-slate-500 block">Tenant Identifier</span>
                            <span class="font-mono font-bold text-slate-900 text-sm">
                                {{ $application->tenantProfile?->tenant_code ?? 'Not yet assigned' }}
                            </span>
                        </div>
                        <div class="border border-slate-100 rounded-xl p-3">
                            <span class="text-slate-500 block">Occupation & Employer</span>
                            <span class="font-bold text-slate-900">
                                {{ $application->tenantProfile?->occupation ?? 'Not provided' }}
                            </span>
                            <span class="text-slate-500 block">
                                @ {{ $application->tenantProfile?->employer_name ?? 'Self-employed / Private' }}
                            </span>
                        </div>
                        <div class="border border-slate-100 rounded-xl p-3">
                            <span class="text-slate-500 block">Monthly Income</span>
                            <span class="font-bold text-slate-900 text-sm">
                                @if($application->tenantProfile?->monthly_income_etb)
                                    {{ number_format((float) $application->tenantProfile->monthly_income_etb, 2) }} ETB
                                @else
                                    Undisclosed
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Col: Target Property Summary -->
            <div class="space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Property Details</h3>
                    <div>
                        <h4 class="font-bold text-slate-900 text-base">{{ $application->property?->title ?? 'Target Property' }}</h4>
                        <p class="text-xs text-slate-500 mt-1">{{ $application->property?->address ?? 'Addis Ababa, Ethiopia' }}</p>
                    </div>

                    @if($application->projectUnit)
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                            <span class="text-slate-500 block">Project Unit</span>
                            <span class="font-bold text-slate-900">Unit {{ $application->projectUnit->unit_number ?? $application->projectUnit->id }}</span>
                        </div>
                    @endif

                    <div class="border-t border-slate-100 pt-3 text-xs space-y-2">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Property Owner</span>
                            <span class="font-semibold text-slate-800">{{ $application->landlord?->name ?? 'Verified Owner' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Listed Price</span>
                            <span class="font-semibold text-slate-800">{{ number_format((float) ($application->property?->price_etb ?? 0), 2) }} ETB</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Review Decision Modal (Alpine.js) -->
        <div
            x-show="reviewModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 flex items-center justify-center p-4"
            x-cloak
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-6 shadow-xl" @click.away="reviewModal = false">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">
                        Review Rental Application #{{ $application->application_code }}
                    </h3>
                    <button type="button" @click="reviewModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form method="POST" action="{{ route('rental-applications.review', $application->uuid) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="decision" :value="decision">

                    <div class="p-3 rounded-xl text-xs font-semibold"
                        :class="decision === 'approve' ? 'bg-emerald-50 text-emerald-900 border border-emerald-200' : (decision === 'reject' ? 'bg-red-50 text-red-900 border border-red-200' : 'bg-amber-50 text-amber-900 border border-amber-200')">
                        Selected Action: <span class="capitalize" x-text="decision.replace('_', ' ')"></span>
                    </div>

                    <div x-show="decision === 'reject'">
                        <label for="rejection_reason" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Rejection Reason *
                        </label>
                        <textarea
                            id="rejection_reason"
                            name="rejection_reason"
                            rows="2"
                            placeholder="State the reason (e.g. proposed rent below threshold, unit already leased)..."
                            class="w-full rounded-xl border border-slate-300 p-3 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
                        ></textarea>
                    </div>

                    <div>
                        <label for="notes" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Review Notes (Optional)
                        </label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="3"
                            placeholder="Add internal review or applicant communication notes..."
                            class="w-full rounded-xl border border-slate-300 p-3 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="reviewModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 text-xs font-semibold">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl text-white text-xs font-semibold shadow-xs"
                            :class="decision === 'approve' ? 'bg-emerald-600 hover:bg-emerald-700' : (decision === 'reject' ? 'bg-red-600 hover:bg-red-700' : 'bg-amber-600 hover:bg-amber-700')">
                            Confirm Decision
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
