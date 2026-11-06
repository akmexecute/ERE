<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <!-- Header & Administration Navigation Bar (SRS Section 214) -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-teal-800">
                    National Property Platform • Administration
                </span>
                <h1 class="text-2xl font-bold text-slate-900">National Admin &amp; Market Intelligence Center</h1>
            </div>

            <div class="flex items-center gap-2 flex-wrap text-xs">
                <a
                    href="{{ route('admin.languages') }}"
                    class="px-3.5 py-1.5 rounded-xl bg-teal-800 hover:bg-teal-900 text-white font-semibold flex items-center gap-1.5 transition shadow-xs"
                >
                    <span>🌐 Languages &amp; i18n</span>
                </a>
                <a
                    href="{{ route('admin.locations') }}"
                    class="px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-semibold flex items-center gap-1.5 transition shadow-xs"
                >
                    <span>📍 Locations &amp; GIS</span>
                </a>
                <a
                    href="{{ route('admin.taxonomy.index') }}"
                    class="px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-semibold flex items-center gap-1.5 transition shadow-xs"
                >
                    <span>📑 Classifications</span>
                </a>
                <a
                    href="{{ route('admin.moderation.index') }}"
                    class="px-3.5 py-1.5 rounded-xl bg-amber-700 hover:bg-amber-800 text-white font-semibold flex items-center gap-1.5 transition shadow-xs"
                >
                    <span>🛡️ Moderation Workbench</span>
                </a>
                <a
                    href="{{ route('admin.security') }}"
                    class="px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-semibold flex items-center gap-1.5 transition shadow-xs"
                >
                    <span>🔒 Security Center</span>
                </a>
                <a
                    href="{{ route('support.tickets.index') }}"
                    class="px-3.5 py-1.5 rounded-xl bg-indigo-700 hover:bg-indigo-800 text-white font-semibold flex items-center gap-1.5 transition shadow-xs"
                >
                    <span>🎫 Support Tickets</span>
                </a>
                <span class="px-3 py-1.5 rounded-xl bg-slate-900 text-white font-semibold">
                    📅 @ethDate(now())
                </span>
            </div>
        </div>

        <!-- SRS Sections 37 & 127 Mandatory Data-Source Attribution Disclosure -->
        <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-xs text-amber-950 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <strong>Market Intelligence Data Basis:</strong>
                <span>{{ $disclosureNotice ?? 'Platform-Derived Market Indicator: Metrics reflect active asking prices published on the platform and do not represent official government transaction registry valuations (SRS Sections 37 & 127).' }}</span>
            </div>
            <span class="inline-block px-2.5 py-1 rounded-full bg-amber-200/80 font-mono text-[11px] font-bold text-amber-900 self-start sm:self-auto shrink-0">
                {{ $dataBasis ?? 'platform_asking_price_indicator' }}
            </span>
        </div>

        <!-- Live Database-Backed KPI Cards (SRS Section 120 & 214) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Total Users</span>
                <div class="text-2xl font-black text-slate-900 mt-1">
                    {{ number_format($kpis['total_users'] ?? 0) }}
                </div>
                <span class="text-[11px] text-teal-800 font-medium">Registered Accounts</span>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Master Properties</span>
                <div class="text-2xl font-black text-slate-900 mt-1">
                    {{ number_format($kpis['master_properties'] ?? 0) }}
                </div>
                <span class="text-[11px] text-teal-800 font-medium">Property Records</span>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Active Listings</span>
                <div class="text-2xl font-black text-slate-900 mt-1">
                    {{ number_format($kpis['active_listings'] ?? 0) }}
                </div>
                <span class="text-[11px] text-teal-800 font-medium">Published / Live</span>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Inquiry Leads</span>
                <div class="text-2xl font-black text-slate-900 mt-1">
                    {{ number_format($kpis['inquiry_volume'] ?? 0) }}
                </div>
                <span class="text-[11px] text-teal-800 font-medium">Pipeline Volume</span>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Pending Verifications</span>
                <div class="text-2xl font-black text-amber-800 mt-1">
                    {{ number_format($kpis['pending_verifications'] ?? 0) }}
                </div>
                <span class="text-[11px] text-amber-700 font-medium">Officer Review</span>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Open Fraud Signals</span>
                <div class="text-2xl font-black text-rose-700 mt-1">
                    {{ number_format($kpis['open_fraud_signals'] ?? 0) }}
                </div>
                <span class="text-[11px] text-rose-600 font-medium">Requires Action</span>
            </div>
        </div>

        <!-- SRS Section 215: National City Analytics Comparison Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-bold text-slate-900">National City Analytics Breakdown (SRS Section 215)</h2>
                    <p class="text-xs text-slate-500">Aggregated live asking price indicators and active listing liquidity</p>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">
                    Currency: Ethiopian Birr (ETB)
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 border-b border-slate-200 uppercase tracking-wider text-[11px]">
                            <th class="py-3.5 px-6 font-bold">City / Location</th>
                            <th class="py-3.5 px-4 font-bold text-right">Active Sales</th>
                            <th class="py-3.5 px-4 font-bold text-right">Active Rentals</th>
                            <th class="py-3.5 px-4 font-bold text-right">Median Asking Price (Sale)</th>
                            <th class="py-3.5 px-4 font-bold text-right">Avg Asking Price / m²</th>
                            <th class="py-3.5 px-6 font-bold text-right">Inquiry Volume</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-800">
                        @forelse($cityAnalytics as $row)
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-6 font-bold text-slate-900">
                                    {{ $row['name_en'] }}
                                    @if(!empty($row['name_am']))
                                        <span class="font-normal text-slate-500">({{ $row['name_am'] }})</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-right font-medium">
                                    {{ number_format($row['active_sale_count']) }}
                                </td>
                                <td class="py-3.5 px-4 text-right font-medium">
                                    {{ number_format($row['active_rent_count']) }}
                                </td>
                                <td class="py-3.5 px-4 text-right font-semibold text-slate-900">
                                    @if($row['median_sale_price'] > 0)
                                        {{ \App\Support\Money\EtbFormatter::format($row['median_sale_price']) }}
                                    @else
                                        <span class="text-slate-400 font-normal">N/A</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-right text-teal-800 font-bold">
                                    @if($row['avg_price_per_sqm'] > 0)
                                        {{ \App\Support\Money\EtbFormatter::format($row['avg_price_per_sqm']) }}/m²
                                    @else
                                        <span class="text-slate-400 font-normal">N/A</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-6 text-right font-medium">
                                    {{ number_format($row['inquiries_count']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 px-6 text-center text-slate-400">
                                    No city location analytics available yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
