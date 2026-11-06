<x-app-layout>
    <div class="min-h-screen bg-[#faf9f5] py-8 px-4 sm:px-6 lg:px-8" x-data="{
        activeTab: '{{ $activeTab }}',
        setTab(tab) {
            this.activeTab = tab;
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.replaceState({}, '', url);
        }
    }">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Hero Header: User Identity & Trust Summary -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-start sm:items-center gap-4">
                        <div class="w-16 h-16 rounded-2xl bg-[#0f3a38] text-amber-200 font-bold text-2xl flex items-center justify-center shadow-xs shrink-0">
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <h1 class="text-2xl font-bold text-slate-900">{{ $user->name }}</h1>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Verified Level {{ $user->verification_level }}
                                </span>
                                @if($hostProfile)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-900 border border-amber-200">
                                        🏨 Host
                                    </span>
                                @endif
                                @if($user->isAgent())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-900 border border-blue-200">
                                        💼 Licensed Agent
                                    </span>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 mt-2">
                                <span class="flex items-center gap-1 font-mono text-slate-700">📱 {{ $user->phone }}</span>
                                <span>•</span>
                                <span>Privacy: <code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-700 font-medium">{{ $user->phone_privacy?->value ?? 'show_phone' }}</code></span>
                                <span>•</span>
                                <span>Language: <strong class="text-slate-800">{{ strtoupper($user->preferred_locale ?? 'en') }}</strong></span>
                                @if($user->email)
                                    <span>•</span>
                                    <span>✉️ {{ $user->email }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a
                            href="{{ route('listings.create') }}"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#0f3a38] hover:bg-[#134e4a] text-white text-sm font-semibold shadow-xs transition"
                        >
                            <span class="text-base leading-none">+</span>
                            <span>Create listing</span>
                        </a>
                        <a
                            href="{{ route('messages.inbox') }}"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-sm font-medium transition"
                        >
                            <span>💬 Inbox</span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button
                                type="submit"
                                class="px-3.5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:text-rose-700 hover:bg-rose-50 text-sm font-medium transition"
                            >
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <div class="mt-8 border-t border-slate-200/80 pt-4">
                    <nav class="flex space-x-2 overflow-x-auto pb-1" aria-label="Dashboard Tabs">
                        <!-- Tab: My Listings -->
                        <button
                            type="button"
                            @click="setTab('listings')"
                            :class="activeTab === 'listings'
                                ? 'bg-[#0f3a38] text-white font-semibold shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-medium'"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm transition shrink-0"
                        >
                            <span>🏠 My Listings</span>
                            <span
                                :class="activeTab === 'listings' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'"
                                class="px-1.5 py-0.5 text-xs rounded-full font-bold"
                            >
                                {{ $listingsCounts['total'] }}
                            </span>
                        </button>

                        <!-- Tab: My Stays -->
                        <button
                            type="button"
                            @click="setTab('stays')"
                            :class="activeTab === 'stays'
                                ? 'bg-[#0f3a38] text-white font-semibold shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-medium'"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm transition shrink-0"
                        >
                            <span>🏨 My Stays</span>
                            @if($hostProfile && $stayKpis['staysCount'] > 0)
                                <span
                                    :class="activeTab === 'stays' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'"
                                    class="px-1.5 py-0.5 text-xs rounded-full font-bold"
                                >
                                    {{ $stayKpis['staysCount'] }}
                                </span>
                            @endif
                        </button>

                        <!-- Tab: My Purchases & Bookings -->
                        <button
                            type="button"
                            @click="setTab('purchases')"
                            :class="activeTab === 'purchases'
                                ? 'bg-[#0f3a38] text-white font-semibold shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-medium'"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm transition shrink-0"
                        >
                            <span>🛍️ My Purchases &amp; Bookings</span>
                            @php
                                $totalConsumer = $consumerCounts['guestBookings'] + $consumerCounts['rentalApplications'] + $consumerCounts['leases'] + $consumerCounts['reservations'] + ($consumerCounts['salesContracts'] ?? 0) + $consumerCounts['offers'];
                            @endphp
                            @if($totalConsumer > 0)
                                <span
                                    :class="activeTab === 'purchases' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'"
                                    class="px-1.5 py-0.5 text-xs rounded-full font-bold"
                                >
                                    {{ $totalConsumer }}
                                </span>
                            @endif
                        </button>

                        <!-- Tab: My Profile & Business -->
                        <button
                            type="button"
                            @click="setTab('profile')"
                            :class="activeTab === 'profile'
                                ? 'bg-[#0f3a38] text-white font-semibold shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-medium'"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm transition shrink-0"
                        >
                            <span>👤 My Profile &amp; Business</span>
                        </button>
                    </nav>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- SECTION 1: MY LISTINGS TAB -->
            <!-- ============================================================== -->
            <div x-show="activeTab === 'listings'" class="space-y-6">
                <!-- Metrics Bar -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200">
                        <span class="text-xs font-medium text-slate-500">Active Published</span>
                        <div class="mt-1 flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-slate-900">{{ $listingsCounts['published'] }}</span>
                            <span class="text-xs text-emerald-700 font-medium">Live on marketplace</span>
                        </div>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-slate-200">
                        <span class="text-xs font-medium text-slate-500">In Draft</span>
                        <div class="mt-1 flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-slate-900">{{ $listingsCounts['draft'] }}</span>
                            <span class="text-xs text-slate-500">Unsubmitted</span>
                        </div>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-slate-200">
                        <span class="text-xs font-medium text-slate-500">Under Review</span>
                        <div class="mt-1 flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-slate-900">{{ $listingsCounts['pending'] }}</span>
                            <span class="text-xs text-amber-700 font-medium">Moderation queue</span>
                        </div>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-slate-200">
                        <span class="text-xs font-medium text-slate-500">Engagement</span>
                        <div class="mt-1 flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-slate-900">{{ $listingsCounts['totalViews'] }}</span>
                            <span class="text-xs text-slate-500">Views • {{ $listingsCounts['totalFavorites'] }} Saves</span>
                        </div>
                    </div>
                </div>

                <!-- Listings Grid -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Commercial &amp; Property Listings</h2>
                            <p class="text-xs text-slate-500">Properties you own or publish for sale, rent, lease, or land development.</p>
                        </div>
                        <a href="{{ route('listings.create') }}" class="px-3.5 py-2 rounded-xl bg-teal-50 text-teal-900 hover:bg-teal-100 text-xs font-semibold transition">
                            + New Listing
                        </a>
                    </div>

                    @if($listings->isEmpty())
                        <div class="text-center py-12 px-4 rounded-xl border border-dashed border-slate-200 bg-slate-50/50">
                            <span class="text-4xl">🏘️</span>
                            <h3 class="mt-3 text-base font-bold text-slate-900">No listings published yet</h3>
                            <p class="mt-1 text-xs text-slate-500 max-w-md mx-auto">
                                You can list a residential villa, apartment, commercial office lease, plot of land, or development project using your unified account.
                            </p>
                            <div class="mt-5">
                                <a
                                    href="{{ route('listings.create') }}"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#0f3a38] hover:bg-[#134e4a] text-white text-xs font-semibold shadow-xs transition"
                                >
                                    <span>+</span> Start listing wizard
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="divide-y divide-slate-100">
                            @foreach($listings as $item)
                                @php
                                    $st = is_object($item->status) && enum_exists($item->status::class) ? $item->status->value : (string) $item->status;
                                    $mode = $item->listingType?->transaction_mode ?? 'BUY';
                                    $isLand = $item->property?->category?->code === 'LND';
                                @endphp
                                <div class="py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                    <div class="flex items-center gap-4">
                                        <div class="w-16 h-16 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden shrink-0 flex items-center justify-center">
                                            @if($item->property && $item->property->coverImage)
                                                <img src="{{ Storage::url($item->property->coverImage->webp_medium_path ?? $item->property->coverImage->original_sanitized_path) }}" alt="" class="w-full h-full object-cover">
                                            @else
                                                <span class="text-2xl">{{ $isLand ? '🌄' : ($mode === 'RENT' ? '🔑' : '🏠') }}</span>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h4 class="text-sm font-bold text-slate-900">
                                                    {{ $item->property?->title_en ?? 'Untitled Property' }}
                                                </h4>
                                                <!-- Mode Badge -->
                                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider
                                                    {{ $mode === 'BUY' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : '' }}
                                                    {{ $mode === 'RENT' ? 'bg-blue-50 text-blue-800 border border-blue-200' : '' }}
                                                    {{ $mode === 'LEASE' ? 'bg-purple-50 text-purple-800 border border-purple-200' : '' }}
                                                    {{ $mode === 'STAY' ? 'bg-amber-50 text-amber-800 border border-amber-200' : '' }}
                                                    {{ $mode === 'NEW_PROJECTS' ? 'bg-rose-50 text-rose-800 border border-rose-200' : '' }}
                                                ">
                                                    {{ $isLand ? 'Land • ' . $mode : $mode }}
                                                </span>
                                                <!-- Status Badge -->
                                                <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold
                                                    {{ $st === 'published' ? 'bg-emerald-100 text-emerald-900' : '' }}
                                                    {{ $st === 'draft' ? 'bg-slate-100 text-slate-700' : '' }}
                                                    {{ in_array($st, ['pending_review', 'under_moderation']) ? 'bg-amber-100 text-amber-900' : '' }}
                                                    {{ $st === 'rejected' ? 'bg-red-100 text-red-900' : '' }}
                                                    {{ $st === 'paused' ? 'bg-yellow-100 text-yellow-900' : '' }}
                                                ">
                                                    {{ ucfirst(str_replace('_', ' ', $st)) }}
                                                </span>
                                            </div>
                                            <p class="text-xs text-slate-500 mt-1">
                                                Code: <span class="font-mono text-slate-700">{{ $item->property?->property_code ?? 'Pending' }}</span>
                                                • {{ $item->property?->city?->name_en ?? 'Addis Ababa' }}
                                                @if($item->property?->subcity), {{ $item->property->subcity->name_en }}@endif
                                                • Price: <strong class="text-slate-900">ETB {{ number_format((float) $item->price_etb) }}</strong>
                                                <span class="text-[11px] text-slate-400">/ {{ $item->price_period ?? 'total' }}</span>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 self-end sm:self-center">
                                        @if($item->slug)
                                            <a href="{{ route('listings.show', $item->slug) }}" class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-xs font-medium text-slate-700 transition">
                                                View public
                                            </a>
                                        @endif
                                        <a href="{{ route('listings.wizard', ['type' => strtolower($mode), 'property_id' => $item->property_id]) }}" class="px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-xs font-semibold text-white transition">
                                            Manage
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- SECTION 2: MY STAYS TAB (HOST OPERATIONS) -->
            <!-- ============================================================== -->
            <div x-show="activeTab === 'stays'" class="space-y-6">
                @if(!$hostProfile)
                    <!-- Welcoming Host Onboarding Empty State -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-8 sm:p-12 text-center max-w-3xl mx-auto shadow-xs">
                        <div class="w-16 h-16 rounded-2xl bg-amber-100 text-amber-800 font-bold text-3xl flex items-center justify-center mx-auto">
                            🏨
                        </div>
                        <h2 class="mt-4 text-xl font-bold text-slate-900">Start Hosting with ERE Short Stays</h2>
                        <p class="mt-2 text-sm text-slate-600 leading-relaxed">
                            Turn your residential villa, serviced apartment, or guesthouse into a verified short-stay accommodation. Reach business travelers, diaspora visitors, and verified Ethiopian guests with secure escrow payouts in Ethiopian Birr (ETB).
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-8 text-left">
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                                <span class="text-xl">🛡️</span>
                                <h4 class="mt-2 text-xs font-bold text-slate-900">Verified Guests Only</h4>
                                <p class="mt-1 text-[11px] text-slate-500">Level 1+ verified phone numbers and identity checks prior to check-in.</p>
                            </div>
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                                <span class="text-xl">📅</span>
                                <h4 class="mt-2 text-xs font-bold text-slate-900">Full Calendar Control</h4>
                                <p class="mt-1 text-[11px] text-slate-500">Set nightly rates, weekend rates, minimum stays, and iCal synchronisation.</p>
                            </div>
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                                <span class="text-xl">💳</span>
                                <h4 class="mt-2 text-xs font-bold text-slate-900">Guaranteed ETB Payouts</h4>
                                <p class="mt-1 text-[11px] text-slate-500">Automated payouts directly to Telebirr, CBE, or Awash Bank accounts.</p>
                            </div>
                        </div>

                        <!-- Host Profile Setup Trigger Form -->
                        <div class="mt-8 pt-6 border-t border-slate-100">
                            <form method="POST" action="{{ route('host.profile.store') }}" class="max-w-md mx-auto space-y-4 text-left">
                                @csrf
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Host Display Name</label>
                                    <input type="text" name="display_name" value="{{ $user->name }}" required class="w-full text-xs rounded-xl border-slate-300">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Host Type</label>
                                        <select name="host_type" class="w-full text-xs rounded-xl border-slate-300">
                                            <option value="individual_owner">Individual Owner</option>
                                            <option value="property_manager">Property Manager</option>
                                            <option value="guesthouse">Guesthouse</option>
                                            <option value="hotel">Hotel</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Payout Schedule</label>
                                        <select name="payout_schedule" class="w-full text-xs rounded-xl border-slate-300">
                                            <option value="after_checkin">After check-in</option>
                                            <option value="weekly">Weekly</option>
                                            <option value="monthly">Monthly</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-[#0f3a38] hover:bg-[#134e4a] text-white text-xs font-semibold shadow-xs transition">
                                    Activate hosting &amp; create stay listing
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <!-- Active Host Dashboard Overview -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="bg-white p-5 rounded-2xl border border-slate-200">
                            <span class="text-xs font-medium text-slate-500">Managed Stays</span>
                            <div class="mt-1 flex items-baseline gap-2">
                                <span class="text-2xl font-bold text-slate-900">{{ $stayKpis['staysCount'] }}</span>
                                <span class="text-xs text-slate-500">Active units</span>
                            </div>
                        </div>
                        <div class="bg-white p-5 rounded-2xl border border-slate-200">
                            <span class="text-xs font-medium text-slate-500">Upcoming Arrivals</span>
                            <div class="mt-1 flex items-baseline gap-2">
                                <span class="text-2xl font-bold text-slate-900">{{ $stayKpis['upcomingCheckins'] }}</span>
                                <span class="text-xs text-amber-700 font-medium">Check-ins pending</span>
                            </div>
                        </div>
                        <div class="bg-white p-5 rounded-2xl border border-slate-200">
                            <span class="text-xs font-medium text-slate-500">Current In-House</span>
                            <div class="mt-1 flex items-baseline gap-2">
                                <span class="text-2xl font-bold text-slate-900">{{ $stayKpis['currentGuests'] }}</span>
                                <span class="text-xs text-emerald-700 font-medium">Checked in</span>
                            </div>
                        </div>
                        <div class="bg-white p-5 rounded-2xl border border-slate-200">
                            <span class="text-xs font-medium text-slate-500">Earned Revenue</span>
                            <div class="mt-1 flex items-baseline gap-2">
                                <span class="text-2xl font-bold text-slate-900">ETB {{ number_format($stayKpis['totalRevenueEtb']) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Stay Properties & Units -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Stay Accommodation Units</h3>
                                <p class="text-xs text-slate-500">Configured stay profiles, nightly rates, and unit availability.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('listings.wizard', ['type' => 'stay']) }}" class="px-3 py-1.5 rounded-xl bg-teal-50 text-teal-900 hover:bg-teal-100 text-xs font-semibold transition">
                                    + Add Stay Listing
                                </a>
                                <a href="{{ route('host.profile') }}" class="px-3 py-1.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-medium transition">
                                    Host settings
                                </a>
                            </div>
                        </div>

                        @if($hostProfile->stayProfiles->isEmpty())
                            <div class="text-center py-8 px-4 rounded-xl border border-dashed border-slate-200 bg-slate-50/50">
                                <span class="text-3xl">🛏️</span>
                                <p class="mt-2 text-xs text-slate-600">Your host profile is active, but you have not linked a stay listing yet.</p>
                                <a href="{{ route('listings.wizard', ['type' => 'stay']) }}" class="mt-3 inline-block px-3.5 py-2 rounded-xl bg-[#0f3a38] text-white text-xs font-semibold">
                                    Publish your first stay
                                </a>
                            </div>
                        @else
                            <div class="divide-y divide-slate-100">
                                @foreach($hostProfile->stayProfiles as $stay)
                                    <div class="py-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h4 class="text-sm font-bold text-slate-900">{{ $stay->property?->title_en ?? 'Short Stay Accommodation' }}</h4>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-teal-50 text-teal-800">
                                                    {{ ucfirst(str_replace('_', ' ', $stay->accommodation_model ?? 'entire_place')) }}
                                                </span>
                                            </div>
                                            <p class="text-xs text-slate-500 mt-0.5">
                                                Rate: <strong class="text-slate-800">ETB {{ number_format((float) $stay->base_nightly_rate_etb) }}/night</strong>
                                                @if($stay->weekend_nightly_rate_etb) • Weekend: ETB {{ number_format((float) $stay->weekend_nightly_rate_etb) }}@endif
                                                • Max {{ $stay->max_guests ?? 2 }} guests
                                                • {{ $stay->accommodationUnits->count() }} units
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @if($stay->listing && $stay->listing->slug)
                                                <a href="{{ route('stays.show', $stay->listing->slug) }}" class="px-3 py-1 rounded-lg border border-slate-200 hover:bg-slate-50 text-xs font-medium text-slate-700 transition">
                                                    View public
                                                </a>
                                            @endif
                                            <a href="{{ route('host.dashboard') }}" class="px-3 py-1 rounded-lg bg-slate-900 text-white hover:bg-slate-800 text-xs font-semibold transition">
                                                Calendar &amp; rates
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Upcoming Guest Reservations -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-4">
                        <h3 class="text-base font-bold text-slate-900">Recent Guest Reservations</h3>
                        @if($hostBookings->isEmpty())
                            <p class="text-xs text-slate-500 py-4">No reservations booked yet for your stays.</p>
                        @else
                            <div class="divide-y divide-slate-100">
                                @foreach($hostBookings as $hb)
                                    @php
                                        $hbst = is_object($hb->status) && enum_exists($hb->status::class) ? $hb->status->value : (string) $hb->status;
                                    @endphp
                                    <div class="py-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <strong class="font-mono text-slate-900">{{ $hb->booking_code }}</strong>
                                                <span class="text-slate-600">Guest: <strong>{{ $hb->guest?->name ?? 'Guest' }}</strong></span>
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-900">
                                                    {{ ucfirst($hbst) }}
                                                </span>
                                            </div>
                                            <p class="text-slate-500 mt-0.5">
                                                {{ $hb->check_in_date?->format('M d, Y') ?? $hb->check_in_date }} &rarr; {{ $hb->check_out_date?->format('M d, Y') ?? $hb->check_out_date }}
                                                ({{ $hb->nights_count }} nights)
                                                • Total: <strong>ETB {{ number_format((float) $hb->total_amount_etb) }}</strong>
                                            </p>
                                        </div>
                                        <a href="{{ route('messages.inbox') }}" class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 font-medium">
                                            Message guest
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <!-- ============================================================== -->
            <!-- SECTION 3: MY PURCHASES & BOOKINGS TAB -->
            <!-- ============================================================== -->
            <div x-show="activeTab === 'purchases'" class="space-y-6">

                <!-- 1. Guest Stay Reservations -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Guest Stay Bookings</h3>
                            <p class="text-xs text-slate-500">Short stays and vacation accommodations reserved by you.</p>
                        </div>
                        <a href="{{ route('stays.index') }}" class="px-3 py-1.5 rounded-xl bg-amber-50 text-amber-900 hover:bg-amber-100 text-xs font-semibold transition">
                            Explore stays
                        </a>
                    </div>

                    @if($guestBookings->isEmpty())
                        <div class="text-center py-6 px-4 rounded-xl border border-dashed border-slate-200 bg-slate-50/50">
                            <span class="text-3xl">🏖️</span>
                            <p class="mt-2 text-xs text-slate-600">No short stays reserved yet.</p>
                            <a href="{{ route('stays.index') }}" class="mt-2 inline-block text-xs font-semibold text-teal-800 hover:underline">
                                Browse Ethiopian stays &rarr;
                            </a>
                        </div>
                    @else
                        <div class="divide-y divide-slate-100">
                            @foreach($guestBookings as $gb)
                                @php
                                    $gbst = is_object($gb->status) && enum_exists($gb->status::class) ? $gb->status->value : (string) $gb->status;
                                @endphp
                                <div class="py-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4 class="text-sm font-bold text-slate-900">{{ $gb->property?->title_en ?? 'Accommodation' }}</h4>
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-900">
                                                {{ ucfirst($gbst) }}
                                            </span>
                                        </div>
                                        <p class="text-slate-500 mt-0.5">
                                            Code: <span class="font-mono text-slate-700">{{ $gb->booking_code }}</span>
                                            • Host: <strong>{{ $gb->host?->display_name ?? 'Host' }}</strong>
                                            • {{ $gb->check_in_date?->format('M d, Y') ?? $gb->check_in_date }} &rarr; {{ $gb->check_out_date?->format('M d, Y') ?? $gb->check_out_date }}
                                            • <strong>ETB {{ number_format((float) $gb->total_amount_etb) }}</strong>
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if($gb->stayProfile && $gb->stayProfile->listing && $gb->stayProfile->listing->slug)
                                            <a href="{{ route('stays.show', $gb->stayProfile->listing->slug) }}" class="px-2.5 py-1 rounded-lg border border-slate-200 hover:bg-slate-50 font-medium">
                                                Stay details
                                            </a>
                                        @endif
                                        <a href="{{ route('messages.inbox') }}" class="px-2.5 py-1 rounded-lg bg-slate-900 text-white hover:bg-slate-800 font-semibold">
                                            Contact host
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- 2. Rental Applications & Active Leases -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Rental Applications &amp; Leases</h3>
                            <p class="text-xs text-slate-500">Long-term tenancy applications and signed lease contracts.</p>
                        </div>
                        <a href="{{ route('tenant.profile') }}" class="px-3 py-1.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-xs font-medium text-slate-700 transition">
                            Tenant profile
                        </a>
                    </div>

                    @if($rentalApplications->isEmpty() && $tenantLeases->isEmpty())
                        <div class="text-center py-6 px-4 rounded-xl border border-dashed border-slate-200 bg-slate-50/50">
                            <span class="text-3xl">📋</span>
                            <p class="mt-2 text-xs text-slate-600">No rental applications or active leases recorded for your account.</p>
                            <a href="{{ url('/search?mode=RENT') }}" class="mt-2 inline-block text-xs font-semibold text-teal-800 hover:underline">
                                Search properties for rent &rarr;
                            </a>
                        </div>
                    @else
                        <!-- Applications -->
                        @if($rentalApplications->isNotEmpty())
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Submitted Applications</h4>
                                <div class="divide-y divide-slate-100">
                                    @foreach($rentalApplications as $app)
                                        @php
                                            $appst = is_object($app->status) && enum_exists($app->status::class) ? $app->status->value : (string) $app->status;
                                        @endphp
                                        <div class="py-2.5 flex items-center justify-between text-xs">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-bold text-slate-900">{{ $app->property?->title_en ?? 'Property' }}</span>
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-900">
                                                        {{ ucfirst($appst) }}
                                                    </span>
                                                </div>
                                                <p class="text-slate-500">
                                                    Code: <span class="font-mono">{{ $app->application_code }}</span>
                                                    • Landlord: {{ $app->landlord?->name ?? 'Landlord' }}
                                                    • Proposed Rent: <strong>ETB {{ number_format((float) $app->proposed_monthly_rent_etb) }}/mo</strong>
                                                </p>
                                            </div>
                                            <a href="{{ route('rental-applications.show', $app->uuid) }}" class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 font-medium">
                                                View application
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Leases -->
                        @if($tenantLeases->isNotEmpty())
                            <div class="pt-3 border-t border-slate-100">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Active Lease Contracts</h4>
                                <div class="divide-y divide-slate-100">
                                    @foreach($tenantLeases as $lse)
                                        @php
                                            $lsest = is_object($lse->status) && enum_exists($lse->status::class) ? $lse->status->value : (string) $lse->status;
                                        @endphp
                                        <div class="py-2.5 flex items-center justify-between text-xs">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-bold text-slate-900">{{ $lse->property?->title_en ?? 'Leased Property' }}</span>
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-900">
                                                        {{ ucfirst($lsest) }}
                                                    </span>
                                                </div>
                                                <p class="text-slate-500">
                                                    Code: <span class="font-mono">{{ $lse->lease_code }}</span>
                                                    • Term: {{ $lse->start_date }} &rarr; {{ $lse->end_date }}
                                                    • Rent: <strong>ETB {{ number_format((float) $lse->monthly_rent_etb) }}/mo</strong>
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <a href="{{ route('rent.schedule', $lse->uuid) }}" class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 font-medium">
                                                    Rent schedule
                                                </a>
                                                <a href="{{ route('leases.show', $lse->uuid) }}" class="px-2.5 py-1 rounded-lg bg-slate-900 text-white font-semibold">
                                                    View lease
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </div>

                <!-- 3. Purchase Offers & Development Unit Reservations -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-4">
                    <h3 class="text-base font-bold text-slate-900">Purchase Offers &amp; Project Unit Holds</h3>
                    <p class="text-xs text-slate-500">Submitted offers and off-plan apartment unit reservations.</p>

                    @if($purchaseOffers->isEmpty() && $unitReservations->isEmpty())
                        <div class="text-center py-6 px-4 rounded-xl border border-dashed border-slate-200 bg-slate-50/50">
                            <span class="text-3xl">🤝</span>
                            <p class="mt-2 text-xs text-slate-600">No active purchase offers or unit reservations.</p>
                            <a href="{{ url('/search?mode=BUY') }}" class="mt-2 inline-block text-xs font-semibold text-teal-800 hover:underline">
                                Search properties for sale &rarr;
                            </a>
                        </div>
                    @else
                        <!-- Offers -->
                        @if($purchaseOffers->isNotEmpty())
                            <div class="divide-y divide-slate-100">
                                @foreach($purchaseOffers as $ofr)
                                    <div class="py-2.5 flex items-center justify-between text-xs">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-slate-900">{{ $ofr->property?->title_en ?? 'Property Offer' }}</span>
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-900">
                                                    {{ ucfirst($ofr->status) }}
                                                </span>
                                            </div>
                                            <p class="text-slate-500">
                                                Offer Code: <span class="font-mono">{{ $ofr->offer_code }}</span>
                                                • Offered: <strong>ETB {{ number_format((float) $ofr->latest_amount_etb) }}</strong>
                                                (Asking: ETB {{ number_format((float) $ofr->listed_asking_price_etb) }})
                                            </p>
                                        </div>
                                        <a href="{{ route('messages.inbox') }}" class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 font-medium">
                                            Negotiation chat
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Reservations -->
                        @if($unitReservations->isNotEmpty())
                            <div class="pt-3 border-t border-slate-100">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Off-Plan Unit Holds</h4>
                                <div class="divide-y divide-slate-100">
                                    @foreach($unitReservations as $res)
                                        @php
                                            $resst = is_object($res->status) && enum_exists($res->status::class) ? $res->status->value : (string) $res->status;
                                        @endphp
                                        <div class="py-2.5 flex items-center justify-between text-xs">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-bold text-slate-900">{{ $res->project?->name_en ?? 'Development Project' }}</span>
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-rose-50 text-rose-900">
                                                        {{ ucfirst($resst) }}
                                                    </span>
                                                </div>
                                                <p class="text-slate-500">
                                                    Unit Code: <span class="font-mono">{{ $res->unit?->unit_code ?? 'Unit' }}</span>
                                                    • Agreed Price: <strong>ETB {{ number_format((float) $res->agreed_price_etb) }}</strong>
                                                    • Deposit: ETB {{ number_format((float) $res->deposit_amount_etb) }}
                                                </p>
                                            </div>
                                            @if($res->project && $res->project->slug)
                                                <a href="{{ route('projects.show', $res->project->slug) }}" class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 font-medium">
                                                    Project details
                                                </a>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <!-- Sales Contracts & Seller Identity Verification -->
                        @if(isset($salesContracts) && $salesContracts->isNotEmpty())
                            <div class="pt-3 border-t border-slate-100">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Signed Sales Contracts &amp; Verified Seller Identity</h4>
                                    <span class="text-[10px] text-teal-800 font-semibold bg-teal-50 px-2 py-0.5 rounded-full border border-teal-200">Statutory Protected Contracts</span>
                                </div>
                                <div class="divide-y divide-slate-100">
                                    @foreach($salesContracts as $cnt)
                                        @php
                                            $cntSt = is_object($cnt->status) && enum_exists($cnt->status::class) ? $cnt->status->value : (string) $cnt->status;
                                            $cntSnap = $cnt->seller_identity_snapshot_json ?? [];
                                        @endphp
                                        <div class="py-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-bold text-slate-900">{{ $cnt->project?->name ?? 'Development Project' }} • {{ $cnt->unit?->unit_code ?? 'Unit' }}</span>
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                        {{ ucfirst($cntSt) }}
                                                    </span>
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-teal-50 text-teal-900 border border-teal-200">
                                                        Seller Verified: {{ $cntSnap['company_legal_name'] ?? $cnt->developerCompany?->name ?? 'Developer' }}
                                                    </span>
                                                </div>
                                                <p class="text-slate-500 mt-0.5">
                                                    Contract: <span class="font-mono text-slate-800 font-semibold">{{ $cnt->contract_code }}</span>
                                                    • Price: <strong>ETB {{ number_format((float) $cnt->contract_price_etb) }}</strong>
                                                    • Deposit: <span class="text-emerald-700 font-semibold">ETB {{ number_format((float) $cnt->deposit_paid_etb) }}</span>
                                                    • Signer ID: <span class="font-mono text-slate-700">{{ $cntSnap['authorized_representative']['masked_national_id'] ?? 'FAN-****-Verified' }}</span>
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <a href="{{ route('buyer.contracts.show', $cnt->contract_code) }}" class="px-3 py-1.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white font-semibold shadow-xs transition">
                                                    Seller Identity &amp; Contract
                                                </a>
                                                <a href="{{ route('buyer.contracts.show', $cnt->contract_code) }}#file-complaint" class="px-2.5 py-1.5 rounded-xl border border-rose-200 text-rose-700 hover:bg-rose-50 font-medium transition">
                                                    Report Seller
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </div>

                <!-- 4. Saved Favorites -->
                @if($favorites->isNotEmpty())
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-bold text-slate-900">Saved Favorites</h3>
                            <a href="{{ route('favorites.index') }}" class="text-xs font-semibold text-teal-800 hover:underline">
                                View all ({{ $consumerCounts['favorites'] }}) &rarr;
                            </a>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            @foreach($favorites as $fav)
                                @if($fav->listing && $fav->listing->property)
                                    <a href="{{ route('listings.show', $fav->listing->slug ?? '') }}" class="p-3 rounded-xl border border-slate-200 hover:border-teal-600 transition group block">
                                        <div class="aspect-video bg-slate-100 rounded-lg overflow-hidden mb-2">
                                            @if($fav->listing->property->coverImage)
                                                <img src="{{ Storage::url($fav->listing->property->coverImage->webp_medium_path ?? $fav->listing->property->coverImage->original_sanitized_path) }}" alt="" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-slate-400 text-xl">🏠</div>
                                            @endif
                                        </div>
                                        <h5 class="text-xs font-bold text-slate-900 group-hover:text-teal-700 truncate">{{ $fav->listing->property->title_en }}</h5>
                                        <p class="text-[11px] font-semibold text-slate-700 mt-0.5">ETB {{ number_format((float) $fav->listing->price_etb) }}</p>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- ============================================================== -->
            <!-- SECTION 4: MY PROFILE & BUSINESS TAB -->
            <!-- ============================================================== -->
            <div x-show="activeTab === 'profile'" class="space-y-6">

                <!-- Profile Information Card -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 space-y-6">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Personal Information &amp; Preferences</h3>
                        <p class="text-xs text-slate-500">Manage your verified identity, contact information, and privacy settings.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                            <span class="text-slate-500 font-medium">Full Legal Name</span>
                            <p class="text-sm font-bold text-slate-900 mt-1">{{ $user->name }}</p>
                        </div>
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                            <span class="text-slate-500 font-medium">Normalized Ethiopian Mobile</span>
                            <p class="text-sm font-bold font-mono text-slate-900 mt-1">{{ $user->phone }}</p>
                        </div>
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                            <span class="text-slate-500 font-medium">Email Address</span>
                            <p class="text-sm font-bold text-slate-900 mt-1">{{ $user->email ?? 'Not linked' }}</p>
                        </div>
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                            <span class="text-slate-500 font-medium">Phone Privacy Mode</span>
                            <p class="text-sm font-bold text-slate-900 mt-1">{{ ucfirst(str_replace('_', ' ', $user->phone_privacy?->value ?? 'show_phone')) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Trust & Verification Ladder -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Platform Trust &amp; Verification Level</h3>
                            <p class="text-xs text-slate-500">Proclamation No. 1357/2024 compliance and anti-fraud verification tier.</p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-900">
                            Level {{ $user->verification_level }} / 5
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-5 gap-3 pt-2 text-xs">
                        <div class="p-3 rounded-xl border {{ $user->verification_level >= 1 ? 'border-emerald-300 bg-emerald-50/50' : 'border-slate-200 opacity-60' }}">
                            <span class="font-bold text-slate-900">Level 1</span>
                            <p class="text-[11px] text-slate-500 mt-1">OTP Mobile Verified</p>
                            <span class="mt-2 inline-block text-[10px] font-semibold text-emerald-800">{{ $user->verification_level >= 1 ? '✓ Active' : 'Pending' }}</span>
                        </div>
                        <div class="p-3 rounded-xl border {{ $user->verification_level >= 2 ? 'border-emerald-300 bg-emerald-50/50' : 'border-slate-200 opacity-60' }}">
                            <span class="font-bold text-slate-900">Level 2</span>
                            <p class="text-[11px] text-slate-500 mt-1">Kebele / Fayda ID</p>
                            <span class="mt-2 inline-block text-[10px] font-semibold text-emerald-800">{{ $user->verification_level >= 2 ? '✓ Active' : 'Unsubmitted' }}</span>
                        </div>
                        <div class="p-3 rounded-xl border {{ $user->verification_level >= 3 ? 'border-emerald-300 bg-emerald-50/50' : 'border-slate-200 opacity-60' }}">
                            <span class="font-bold text-slate-900">Level 3</span>
                            <p class="text-[11px] text-slate-500 mt-1">Title Deed Vault</p>
                            <span class="mt-2 inline-block text-[10px] font-semibold text-emerald-800">{{ $user->verification_level >= 3 ? '✓ Active' : 'Unsubmitted' }}</span>
                        </div>
                        <div class="p-3 rounded-xl border {{ $user->verification_level >= 4 ? 'border-emerald-300 bg-emerald-50/50' : 'border-slate-200 opacity-60' }}">
                            <span class="font-bold text-slate-900">Level 4</span>
                            <p class="text-[11px] text-slate-500 mt-1">Physical Inspection</p>
                            <span class="mt-2 inline-block text-[10px] font-semibold text-emerald-800">{{ $user->verification_level >= 4 ? '✓ Active' : 'Unsubmitted' }}</span>
                        </div>
                        <div class="p-3 rounded-xl border {{ $user->verification_level >= 5 ? 'border-emerald-300 bg-emerald-50/50' : 'border-slate-200 opacity-60' }}">
                            <span class="font-bold text-slate-900">Level 5</span>
                            <p class="text-[11px] text-slate-500 mt-1">Trade License / Tin</p>
                            <span class="mt-2 inline-block text-[10px] font-semibold text-emerald-800">{{ $user->verification_level >= 5 ? '✓ Active' : 'Unsubmitted' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Business / Company Affiliation -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Company &amp; Agency Profiles</h3>
                            <p class="text-xs text-slate-500">Corporate brokerages, property management entities, or development firms owned by you.</p>
                        </div>
                    </div>

                    @if($companies->isEmpty())
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80 text-xs text-slate-600">
                            <p>You have not registered a corporate company or agency on ERE. Individual users can publish, buy, rent, and host without a company entity.</p>
                        </div>
                    @else
                        <div class="divide-y divide-slate-100">
                            @foreach($companies as $comp)
                                <div class="py-3 flex items-center justify-between text-xs">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4 class="text-sm font-bold text-slate-900">{{ $comp->legal_name }}</h4>
                                            @if($comp->trade_name)<span class="text-slate-500">({{ $comp->trade_name }})</span>@endif
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-teal-50 text-teal-800 uppercase">
                                                {{ $comp->company_type }}
                                            </span>
                                        </div>
                                        <p class="text-slate-500 mt-0.5">
                                            TIN: <span class="font-mono text-slate-700">{{ $comp->tin_number ?? 'Pending' }}</span>
                                            • Branches: {{ $comp->branches->count() }}
                                            • Status: <strong class="text-slate-800">{{ ucfirst($comp->status) }}</strong>
                                        </p>
                                    </div>
                                    @if($user->isAgent() || $comp->company_type === 'agency')
                                        <a href="{{ route('portfolio.dashboard') }}" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 font-medium">
                                            Manage portfolio
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Security & Audit History -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 space-y-4">
                    <h3 class="text-base font-bold text-slate-900">Recent Security &amp; Activity Log</h3>
                    @if($auditLogs->isEmpty())
                        <p class="text-xs text-slate-500">No audit activity recorded yet.</p>
                    @else
                        <div class="divide-y divide-slate-100 font-mono text-[11px]">
                            @foreach($auditLogs as $log)
                                <div class="py-2 flex items-center justify-between text-slate-600">
                                    <span>{{ $log->event ?? 'action' }} &bull; {{ $log->auditable_type ?? 'Entity' }}</span>
                                    <span class="text-slate-400">{{ $log->created_at?->diffForHumans() }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
