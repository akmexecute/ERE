<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
        <!-- Breadcrumb & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-6">
            <div>
                <nav class="text-xs text-slate-500 mb-1 flex items-center gap-2">
                    <a href="{{ url('/dashboard') }}" class="hover:text-teal-700">Dashboard</a>
                    <span>/</span>
                    <span class="text-slate-800 font-medium">Tenant Management</span>
                </nav>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Tenant Profile & Rental History</h1>
                <p class="text-sm text-slate-600 mt-1">Manage employment information, emergency contacts, verified identity, and past tenancies.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('rental-applications.index') }}" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 text-sm font-semibold transition">
                    Rental Applications
                </a>
                <a href="{{ route('leases.index') }}" class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-sm font-semibold transition">
                    My Leases
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Summary & Identification Card -->
            <div class="space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-6">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-teal-800 text-white font-bold text-xl flex items-center justify-center">
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">{{ $user->name }}</h2>
                            <p class="text-xs text-slate-500">{{ $user->phone }}</p>
                            @if($profile?->tenant_code)
                                <div class="mt-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-semibold bg-slate-100 text-slate-800 border border-slate-200">
                                        {{ $profile->tenant_code }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-4 space-y-3">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-slate-500">Identity Status</span>
                            @if($profile && ($profile->is_identity_verified ?? false))
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Fayda / NID Verified
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                    Standard Verification (Level {{ $user->verification_level ?? 1 }})
                                </span>
                            @endif
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-slate-500">Status</span>
                            <span class="capitalize text-slate-800 font-medium">{{ is_object($profile?->status) ? $profile->status->label() : ($profile?->status ?? 'Pending setup') }}</span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-slate-500">Active Leases</span>
                            <span class="text-slate-800 font-semibold">{{ $profile ? $profile->leases->filter(fn($l) => (is_object($l->status) ? $l->status->value : $l->status) === 'active')->count() : 0 }}</span>
                        </div>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-4 text-xs text-slate-600 space-y-2 border border-slate-200/60">
                        <div class="font-semibold text-slate-800 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-teal-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Privacy & Sensitive Data
                        </div>
                        <p>National identification, income, and emergency contact details are encrypted and masked in ordinary administrative views per platform compliance rules.</p>
                    </div>
                </div>
            </div>

            <!-- Right Column: Profile Form & History -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Profile Form -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs">
                    <h2 class="text-xl font-bold text-slate-900 mb-1">Employment & Personal Details</h2>
                    <p class="text-xs text-slate-500 mb-6">These details will be referenced during rental application reviews by prospective landlords.</p>

                    <form method="POST" action="{{ route('tenant.profile.update') }}" class="space-y-6">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <label for="occupation" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Occupation / Profession</label>
                                <input type="text" id="occupation" name="occupation" value="{{ old('occupation', $profile?->occupation) }}" placeholder="e.g. Software Engineer, Civil Servant" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:border-transparent">
                                @error('occupation') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="employer_name" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Employer / Organization</label>
                                <input type="text" id="employer_name" name="employer_name" value="{{ old('employer_name', $profile?->employer_name) }}" placeholder="e.g. Commercial Bank of Ethiopia" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:border-transparent">
                                @error('employer_name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-2">
                                <label for="monthly_income_etb" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Monthly Income (ETB)</label>
                                <div class="relative">
                                    <input type="number" step="0.01" min="0" id="monthly_income_etb" name="monthly_income_etb" value="{{ old('monthly_income_etb', $profile?->monthly_income_etb) }}" placeholder="50000.00" class="w-full rounded-xl border border-slate-300 pl-3.5 pr-14 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:border-transparent">
                                    <span class="absolute right-3.5 top-2.5 text-xs font-bold text-slate-400">ETB</span>
                                </div>
                                @error('monthly_income_etb') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-6">
                            <h3 class="text-sm font-bold text-slate-900 mb-1">Emergency Contact</h3>
                            <p class="text-xs text-slate-500 mb-4">A trusted contact reachable in case of lease emergencies or property safety incidents.</p>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label for="emergency_contact_name" class="block text-xs font-medium text-slate-700 mb-1">Contact Name</label>
                                    <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name', $profile?->emergency_contact_name) }}" placeholder="Full Name" class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600">
                                </div>
                                <div>
                                    <label for="emergency_contact_phone" class="block text-xs font-medium text-slate-700 mb-1">
                                        Phone Number 
                                        @if($profile?->emergency_contact_phone)
                                            <span class="text-[10px] text-slate-400 font-normal">({{ $profile->maskedEmergencyPhone() }})</span>
                                        @endif
                                    </label>
                                    <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $profile?->emergency_contact_phone) }}" placeholder="+251911..." class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600">
                                </div>
                                <div>
                                    <label for="emergency_contact_relationship" class="block text-xs font-medium text-slate-700 mb-1">Relationship</label>
                                    <input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship', $profile?->emergency_contact_relationship) }}" placeholder="e.g. Sibling, Spouse" class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-sm font-semibold shadow-xs transition">
                                Save Profile Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Structured Rental History (Distinguishing Verified Leases vs Self-Reported) -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs space-y-6">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">Verified Platform Tenancies</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Lease contracts completed and audited directly within the GTP Real Estate platform.</p>
                    </div>

                    @if($profile && $profile->leases->count() > 0)
                        <div class="divide-y divide-slate-100">
                            @foreach($profile->leases as $lease)
                                <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-slate-900 text-sm">{{ $lease->property->title }}</span>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                Verified Lease
                                            </span>
                                        </div>
                                        <div class="text-xs text-slate-500 mt-1 flex items-center gap-2">
                                            <span>Term: {{ $lease->start_date->format('M Y') }} – {{ $lease->end_date->format('M Y') }}</span>
                                            <span>•</span>
                                            <span>{{ number_format((float) $lease->monthly_rent_etb, 2) }} ETB/mo</span>
                                        </div>
                                    </div>
                                    <div>
                                        <a href="{{ route('leases.show', $lease->uuid) }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">
                                            View Lease Details &rarr;
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-xl bg-slate-50 border border-dashed border-slate-200 p-6 text-center text-xs text-slate-500">
                            No completed platform lease records yet. Active leases will automatically appear here once signed.
                        </div>
                    @endif

                    <!-- Self-Reported Rental History -->
                    @if($profile && $profile->rentalHistories->count() > 0)
                        <div class="border-t border-slate-100 pt-6 space-y-3">
                            <h3 class="text-sm font-bold text-slate-900">Self-Reported Prior Landlords</h3>
                            <p class="text-xs text-slate-500">External rental history reported by the tenant for evaluation.</p>
                            <div class="space-y-3">
                                @foreach($profile->rentalHistories as $history)
                                    <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 flex flex-col sm:flex-row justify-between gap-2 text-xs">
                                        <div>
                                            <span class="font-semibold text-slate-800">{{ $history->property_address }}</span>
                                            <p class="text-slate-500 mt-0.5">Landlord: {{ $history->landlord_name }} ({{ $history->landlord_contact }})</p>
                                        </div>
                                        <div class="text-right sm:text-right text-slate-600">
                                            <span>{{ $history->start_date?->format('M Y') }} – {{ $history->end_date?->format('M Y') ?? 'Present' }}</span>
                                            <p class="font-medium text-slate-800">{{ number_format((float) $history->monthly_rent_etb, 2) }} ETB/mo</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
