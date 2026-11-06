<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
        <!-- Breadcrumb & Header -->
        <div>
            <nav class="text-xs text-slate-500 mb-1 flex items-center gap-2">
                <a href="{{ url('/dashboard') }}" class="hover:text-teal-700">Dashboard</a>
                <span>/</span>
                <a href="{{ route('rental-applications.index') }}" class="hover:text-teal-700">Applications</a>
                <span>/</span>
                <span class="text-slate-800 font-medium">New Rental Application</span>
            </nav>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Apply for Tenancy</h1>
            <p class="text-sm text-slate-600 mt-1">Submit your rental proposal, desired lease duration, and payment frequency directly to the property owner.</p>
        </div>

        @if(!$property && (!isset($availableProperties) || $availableProperties->isEmpty()))
            <!-- Empty State when no properties exist -->
            <div class="rounded-3xl bg-white border border-dashed border-slate-300 p-12 text-center space-y-4 shadow-sm">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-teal-50 text-teal-700 flex items-center justify-center text-2xl font-bold">
                    🏠
                </div>
                <div class="space-y-1">
                    <h2 class="text-lg font-bold text-slate-900">No Rental Properties Available</h2>
                    <p class="text-xs text-slate-500 max-w-md mx-auto">
                        There are currently no active rental properties listed in the system. Properties must be published before prospective tenants can apply.
                    </p>
                </div>
                <div class="pt-2 flex items-center justify-center gap-3">
                    <a href="{{ url('/dashboard') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition">
                        Return to Dashboard
                    </a>
                    <a href="{{ route('listings.create') }}" class="px-5 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold transition shadow-xs">
                        List a Property
                    </a>
                </div>
            </div>
        @else
            <!-- Property Selector & Target Property Summary -->
            @if(isset($availableProperties) && $availableProperties->count() > 1)
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs space-y-2">
                    <label for="property_picker" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Select Leased Property
                    </label>
                    <select
                        id="property_picker"
                        onchange="if(this.value) window.location.href = '{{ route('rental-applications.create') }}?property_id=' + this.value"
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-xs text-slate-900 bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-teal-600 font-medium"
                    >
                        @foreach($availableProperties as $avail)
                            <option value="{{ $avail->id }}" {{ ($property?->id == $avail->id) ? 'selected' : '' }}>
                                {{ $avail->title }} — {{ number_format((float) ($avail->price_etb ?? 0), 2) }} ETB/month ({{ $avail->address ?? 'Addis Ababa' }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if($property)
                <div class="bg-teal-900 text-white rounded-2xl p-6 sm:p-8 flex flex-col sm:flex-row justify-between gap-6 shadow-md">
                    <div class="space-y-2">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-teal-800 text-teal-200 border border-teal-700">
                            Target Property
                        </span>
                        <h2 class="text-2xl font-bold">{{ $property->title }}</h2>
                        <p class="text-xs text-teal-200">
                            {{ $property->address ?? 'Addis Ababa, Ethiopia' }} • {{ $property->city ?? 'Addis Ababa' }}
                        </p>
                        @if($property->price_etb)
                            <p class="text-sm font-semibold text-emerald-300 mt-2">
                                Listed Rent: {{ number_format((float) $property->price_etb, 2) }} ETB / month
                            </p>
                        @endif
                    </div>

                    <div class="bg-teal-800/80 rounded-xl p-4 text-xs space-y-2 border border-teal-700/60 max-w-xs">
                        <div class="font-bold text-teal-100">Landlord / Management</div>
                        <div class="text-teal-200 font-medium">{{ $property->owner?->name ?? 'Verified Property Owner' }}</div>
                        <p class="text-[11px] text-teal-300/80">Your profile, occupation, and contact details will be shared with the owner upon submission.</p>
                    </div>
                </div>
            @endif

            <!-- Application Form -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs">
                <form method="POST" action="{{ route('rental-applications.store') }}" class="space-y-6">
                    @csrf
                    <input type="hidden" name="property_id" value="{{ old('property_id', $property?->id) }}">
                    @if(isset($listingId) && $listingId)
                        <input type="hidden" name="listing_id" value="{{ $listingId }}">
                    @endif
                    @if(isset($unitId) && $unitId)
                        <input type="hidden" name="project_unit_id" value="{{ $unitId }}">
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label for="desired_move_in_date" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Desired Move-In Date *
                            </label>
                            <input
                                type="date"
                                id="desired_move_in_date"
                                name="desired_move_in_date"
                                min="{{ now()->toDateString() }}"
                                value="{{ old('desired_move_in_date', now()->addDays(7)->toDateString()) }}"
                                required
                                class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:border-transparent"
                            >
                            @error('desired_move_in_date') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="lease_duration_months" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Lease Duration (Months) *
                            </label>
                            <select
                                id="lease_duration_months"
                                name="lease_duration_months"
                                required
                                class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:border-transparent bg-white"
                            >
                                <option value="6" {{ old('lease_duration_months') == '6' ? 'selected' : '' }}>6 Months</option>
                                <option value="12" {{ old('lease_duration_months', '12') == '12' ? 'selected' : '' }}>12 Months (1 Year)</option>
                                <option value="24" {{ old('lease_duration_months') == '24' ? 'selected' : '' }}>24 Months (2 Years)</option>
                                <option value="36" {{ old('lease_duration_months') == '36' ? 'selected' : '' }}>36 Months (3 Years)</option>
                            </select>
                            @error('lease_duration_months') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="proposed_monthly_rent_etb" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Proposed Monthly Rent (ETB) *
                            </label>
                            <div class="relative">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="1"
                                    id="proposed_monthly_rent_etb"
                                    name="proposed_monthly_rent_etb"
                                    value="{{ old('proposed_monthly_rent_etb', $property?->price_etb ?? 25000) }}"
                                    required
                                    class="w-full rounded-xl border border-slate-300 pl-3.5 pr-14 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:border-transparent"
                                >
                                <span class="absolute right-3.5 top-2.5 text-xs font-bold text-slate-400">ETB</span>
                            </div>
                            @error('proposed_monthly_rent_etb') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="payment_frequency" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Payment Frequency *
                            </label>
                            <select
                                id="payment_frequency"
                                name="payment_frequency"
                                required
                                class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:border-transparent bg-white"
                            >
                                <option value="monthly" {{ old('payment_frequency', 'monthly') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                <option value="quarterly" {{ old('payment_frequency') == 'quarterly' ? 'selected' : '' }}>Quarterly (Every 3 Months)</option>
                                <option value="semi_annual" {{ old('payment_frequency') == 'semi_annual' ? 'selected' : '' }}>Semi-Annual (Every 6 Months)</option>
                                <option value="annual" {{ old('payment_frequency') == 'annual' ? 'selected' : '' }}>Annual (1 Payment / Year)</option>
                            </select>
                            @error('payment_frequency') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="occupant_count" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Number of Occupants *
                            </label>
                            <input
                                type="number"
                                id="occupant_count"
                                name="occupant_count"
                                min="1"
                                max="20"
                                value="{{ old('occupant_count', 1) }}"
                                required
                                class="w-full sm:w-1/2 rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:border-transparent"
                            >
                            @error('occupant_count') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="applicant_message" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Message to Landlord (Optional)
                            </label>
                            <textarea
                                id="applicant_message"
                                name="applicant_message"
                                rows="4"
                                placeholder="Introduce yourself, describe your employment stability, or mention specific requests regarding move-in..."
                                class="w-full rounded-xl border border-slate-300 p-3.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:border-transparent"
                            >{{ old('applicant_message') }}</textarea>
                            @error('applicant_message') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <p class="text-xs text-slate-500">
                            By submitting, you agree to verified identity screening and reference verification.
                        </p>
                        <button
                            type="submit"
                            class="w-full sm:w-auto px-6 py-3 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-sm font-semibold shadow-xs transition"
                        >
                            Submit Rental Application
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
