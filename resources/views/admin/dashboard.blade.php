<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <!-- Header & Date/Region Filter Bar (SRS Section 214) -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-teal-700">
                    National Property Platform • Administration
                </span>
                <h1 class="text-2xl font-bold text-slate-900">National Admin & Market Intelligence Center</h1>
            </div>

            <div class="flex items-center gap-2 text-xs">
                <span class="px-3 py-1.5 rounded-xl bg-slate-900 text-white font-semibold">
                    📅 @ethDate(now())
                </span>
            </div>
        </div>

        <!-- SRS Sections 37 & 127 Mandatory Data-Source Attribution Banner -->
        <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-xs text-amber-950 flex items-center justify-between">
            <span>
                <strong>Market Intelligence Data Basis (SRS Sections 37 & 127):</strong> All price and ETB/m² metrics below are labeled as <em>Platform-Derived Asking-Price Indicators</em> based on active marketplace listings.
            </span>
            <span class="px-2.5 py-1 rounded-full bg-amber-200/70 font-mono text-[11px] font-semibold">
                platform_asking_price_indicator
            </span>
        </div>

        <!-- National KPI Cards (SRS Section 120 & 214) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            @foreach([
                ['label' => 'Total Users', 'val' => '24,850', 'sub' => '+14% this month'],
                ['label' => 'Master Properties', 'val' => '6,420', 'sub' => 'Property Core'],
                ['label' => 'Active Listings', 'val' => '5,910', 'sub' => 'Buy / Rent / Lease'],
                ['label' => 'Sales & Rent Leads', 'val' => '18,340', 'sub' => 'Tracked Inquiries'],
                ['label' => 'Pending Verifications', 'val' => '42', 'sub' => 'Officer Queue'],
                ['label' => 'Open Fraud Signals', 'val' => '7', 'sub' => 'Moderator Review'],
            ] as $kpi)
                <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
                    <span class="text-xs font-medium text-slate-500">{{ $kpi['label'] }}</span>
                    <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ $kpi['val'] }}</div>
                    <span class="text-[11px] text-teal-700 font-medium">{{ $kpi['sub'] }}</span>
                </div>
            @endforeach
        </div>

        <!-- SRS Section 215: City Analytics Comparison Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900">National City Analytics Comparison (SRS Section 215)</h2>
                <span class="text-xs text-slate-500">Currency: Ethiopian Birr (ETB)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 border-b border-slate-200 uppercase tracking-wider text-[11px]">
                            <th class="py-3.5 px-6 font-bold">City / Region</th>
                            <th class="py-3.5 px-4 font-bold text-right">Active Sale Listings</th>
                            <th class="py-3.5 px-4 font-bold text-right">Active Rentals</th>
                            <th class="py-3.5 px-4 font-bold text-right">Median Asking Price (Sale)</th>
                            <th class="py-3.5 px-4 font-bold text-right">Avg Asking Price / m²</th>
                            <th class="py-3.5 px-6 font-bold text-right">Monthly Inquiries</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-slate-800">
                        <tr>
                            <td class="py-3.5 px-6 font-bold">Addis Ababa (አዲስ አበባ)</td>
                            <td class="py-3.5 px-4 text-right">3,420</td>
                            <td class="py-3.5 px-4 text-right">1,180</td>
                            <td class="py-3.5 px-4 text-right font-semibold">ETB 18,500,000</td>
                            <td class="py-3.5 px-4 text-right text-teal-800 font-bold">ETB 68,400 / m²</td>
                            <td class="py-3.5 px-6 text-right">12,940</td>
                        </tr>
                        <tr>
                            <td class="py-3.5 px-6 font-bold">Harar (ሐረር)</td>
                            <td class="py-3.5 px-4 text-right">285</td>
                            <td class="py-3.5 px-4 text-right">110</td>
                            <td class="py-3.5 px-4 text-right font-semibold">ETB 9,200,000</td>
                            <td class="py-3.5 px-4 text-right text-teal-800 font-bold">ETB 36,800 / m²</td>
                            <td class="py-3.5 px-6 text-right">1,120</td>
                        </tr>
                        <tr>
                            <td class="py-3.5 px-6 font-bold">Jijiga (ጅጅጋ)</td>
                            <td class="py-3.5 px-4 text-right">310</td>
                            <td class="py-3.5 px-4 text-right">95</td>
                            <td class="py-3.5 px-4 text-right font-semibold">ETB 10,500,000</td>
                            <td class="py-3.5 px-4 text-right text-teal-800 font-bold">ETB 38,500 / m²</td>
                            <td class="py-3.5 px-6 text-right">1,290</td>
                        </tr>
                        <tr>
                            <td class="py-3.5 px-6 font-bold">Dire Dawa (ድሬዳዋ)</td>
                            <td class="py-3.5 px-4 text-right">390</td>
                            <td class="py-3.5 px-4 text-right">140</td>
                            <td class="py-3.5 px-4 text-right font-semibold">ETB 11,000,000</td>
                            <td class="py-3.5 px-4 text-right text-teal-800 font-bold">ETB 41,200 / m²</td>
                            <td class="py-3.5 px-6 text-right">1,540</td>
                        </tr>
                        <tr>
                            <td class="py-3.5 px-6 font-bold">Hawassa (ሀዋሳ)</td>
                            <td class="py-3.5 px-4 text-right">340</td>
                            <td class="py-3.5 px-4 text-right">135</td>
                            <td class="py-3.5 px-4 text-right font-semibold">ETB 12,400,000</td>
                            <td class="py-3.5 px-4 text-right text-teal-800 font-bold">ETB 44,900 / m²</td>
                            <td class="py-3.5 px-6 text-right">1,450</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
