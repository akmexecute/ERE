<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
        <!-- Header & Breadcrumbs -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-6">
            <div>
                <nav class="text-xs text-slate-500 mb-1 flex items-center gap-2">
                    <a href="{{ url('/dashboard') }}" class="hover:text-teal-700">Dashboard</a>
                    <span>/</span>
                    <span class="text-slate-800 font-medium">Enterprise Portfolio Management</span>
                </nav>
                <div class="flex items-center gap-3">
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Property Management Dashboard</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 border border-purple-200">
                        SRS Sec. 196 (Module 29)
                    </span>
                </div>
                <p class="text-sm text-slate-600 mt-1">Multi-unit occupancy, financial performance, maintenance operations, and organizational management.</p>
            </div>

            <!-- Quick Navigation Action Bar -->
            <div class="flex flex-wrap items-center gap-2.5">
                <a href="{{ route('portfolio.matrix') }}" class="px-3.5 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-semibold transition">
                    🏢 Unit Inventory Matrix
                </a>
                <a href="{{ route('portfolio.mandates') }}" class="px-3.5 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-semibold transition">
                    📜 Management Mandates
                </a>
                <a href="{{ route('maintenance.index') }}" class="px-3.5 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-semibold transition">
                    🔧 Maintenance Board
                </a>
                <a href="{{ route('invoices.create') }}" class="px-3.5 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold shadow-xs transition">
                    + Issue Invoice
                </a>
            </div>
        </div>

        <!-- Filter Bar: Date Range & Branch Scope -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
            <form method="GET" action="{{ route('portfolio.dashboard') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                @if($branches->isNotEmpty())
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Branch Scope</label>
                        <select name="branch_id" class="w-full text-xs rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                            <option value="">All Branches (Head Office)</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ (string) request('branch_id') === (string) $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name_en }} ({{ $branch->branch_code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">From Date</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}" class="w-full text-xs rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">To Date</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}" class="w-full text-xs rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="w-full py-2 px-3 rounded-xl bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 transition">
                        Update Report
                    </button>
                    @if(request()->hasAny(['from_date', 'to_date', 'branch_id']))
                        <a href="{{ route('portfolio.dashboard') }}" class="py-2 px-3 rounded-xl border border-slate-300 text-slate-600 text-xs hover:bg-slate-50 transition">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- 11 Standard Business KPIs (SRS Section 196) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <!-- 1. Properties Managed -->
            <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">1. Properties Managed</span>
                <div class="text-2xl font-bold text-slate-900">{{ number_format($kpis['properties_managed']) }}</div>
                <span class="text-[11px] text-slate-500">Under active mandates</span>
            </div>

            <!-- 2. Total Units -->
            <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">2. Total Units</span>
                <div class="text-2xl font-bold text-slate-900">{{ number_format($kpis['total_units']) }}</div>
                <span class="text-[11px] text-slate-500">Inventory portfolio</span>
            </div>

            <!-- 3. Occupied Units -->
            <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">3. Occupied Units</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-emerald-700">{{ number_format($kpis['occupied_units']) }}</span>
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">
                        {{ $kpis['occupancy_rate_pct'] }}%
                    </span>
                </div>
                <span class="text-[11px] text-slate-500">Active leases & stays</span>
            </div>

            <!-- 4. Vacant Units -->
            <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">4. Vacant Units</span>
                <div class="text-2xl font-bold text-slate-700">{{ number_format($kpis['vacant_units']) }}</div>
                <span class="text-[11px] text-slate-500">Ready for listing / lease</span>
            </div>

            <!-- 5. Tenants -->
            <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">5. Distinct Tenants</span>
                <div class="text-2xl font-bold text-slate-900">{{ number_format($kpis['tenants']) }}</div>
                <span class="text-[11px] text-slate-500">Long-term contracts</span>
            </div>

            <!-- 6. Guests -->
            <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">6. Short-Stay Guests</span>
                <div class="text-2xl font-bold text-slate-900">{{ number_format($kpis['guests']) }}</div>
                <span class="text-[11px] text-slate-500">Active today</span>
            </div>

            <!-- 7. Active Bookings -->
            <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">7. Active Bookings</span>
                <div class="text-2xl font-bold text-slate-900">{{ number_format($kpis['active_bookings']) }}</div>
                <span class="text-[11px] text-slate-500">Checked-in & reserved</span>
            </div>

            <!-- 8. Rent Collected -->
            <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">8. Rent Collected</span>
                <div class="text-xl font-bold text-teal-800 font-mono">ETB {{ number_format($kpis['rent_collected_etb'], 2) }}</div>
                <span class="text-[11px] text-emerald-600 font-medium">Confirmed ledger payments</span>
            </div>

            <!-- Expected Rent -->
            <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Expected Rent</span>
                <div class="text-xl font-bold text-slate-800 font-mono">ETB {{ number_format($kpis['expected_rent_etb'] ?? 0.00, 2) }}</div>
                <span class="text-[11px] text-slate-500">Scheduled billing period</span>
            </div>

            <!-- Outstanding Rent / Arrears -->
            <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Outstanding Arrears</span>
                <div class="text-xl font-bold {{ ($kpis['outstanding_rent_etb'] ?? 0) > 0 ? 'text-rose-700' : 'text-slate-800' }} font-mono">
                    ETB {{ number_format($kpis['outstanding_rent_etb'] ?? 0.00, 2) }}
                </div>
                <span class="text-[11px] {{ ($kpis['outstanding_rent_etb'] ?? 0) > 0 ? 'text-rose-600 font-medium' : 'text-slate-500' }}">Overdue & pending balances</span>
            </div>

            <!-- 9. Open Maintenance Requests -->
            <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">9. Open Maintenance</span>
                <div class="text-2xl font-bold text-amber-700">{{ number_format($kpis['open_maintenance_requests']) }}</div>
                <span class="text-[11px] text-slate-500">Tickets awaiting completion</span>
            </div>

            <!-- 10. Active Cleaning Operations -->
            <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">10. Active Cleaning</span>
                <div class="text-2xl font-bold text-blue-700">{{ number_format($kpis['active_cleaning_operations']) }}</div>
                <span class="text-[11px] text-slate-500">In-progress housekeeping</span>
            </div>

            <!-- Active Disputes -->
            <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Disputes</span>
                <div class="text-2xl font-bold {{ ($kpis['active_disputes'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-700' }}">{{ number_format($kpis['active_disputes'] ?? 0) }}</div>
                <span class="text-[11px] text-slate-500">Mediation & review</span>
            </div>

            <!-- 11. Total Portfolio Revenue -->
            <div class="p-5 rounded-2xl bg-slate-900 text-white shadow-sm space-y-1 col-span-2 sm:col-span-1 lg:col-span-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-teal-400">11. Total Portfolio Revenue</span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-slate-800 text-slate-300 font-mono">ETB Realized</span>
                </div>
                <div class="text-2xl sm:text-3xl font-bold font-mono text-white">ETB {{ number_format($kpis['total_portfolio_revenue_etb'], 2) }}</div>
                <div class="text-xs text-slate-400 flex items-center justify-between pt-1 border-t border-slate-800">
                    <span>Rent: {{ number_format($kpis['rent_collected_etb']) }}</span>
                    <span>Stays: {{ number_format($kpis['short_stay_revenue_etb']) }}</span>
                </div>
            </div>
        </div>

        <!-- Recent Workloads & Mandates Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Open Maintenance Tickets -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Recent Work Orders</h3>
                    <a href="{{ route('maintenance.index') }}" class="text-xs font-semibold text-teal-700 hover:underline">
                        View All &rarr;
                    </a>
                </div>

                <div class="divide-y divide-slate-100 text-xs">
                    @forelse($recentTickets as $ticket)
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div>
                                <span class="font-mono font-bold text-teal-800">{{ $ticket->ticket_code }}</span>
                                <div class="font-semibold text-slate-900">{{ $ticket->title }}</div>
                                <span class="text-[11px] text-slate-400">{{ $ticket->property?->title_en }}</span>
                            </div>
                            <div class="text-right">
                                <span class="capitalize px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700">
                                    {{ $ticket->status->label() }}
                                </span>
                                <span class="block text-[11px] text-slate-400 mt-1">@ethDate($ticket->created_at)</span>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-slate-400">No active work orders.</div>
                    @endforelse
                </div>
            </div>

            <!-- Active Management Mandates -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Management Mandates</h3>
                    <a href="{{ route('portfolio.mandates') }}" class="text-xs font-semibold text-teal-700 hover:underline">
                        Manage Mandates &rarr;
                    </a>
                </div>

                <div class="divide-y divide-slate-100 text-xs">
                    @forelse($recentMandates as $mandate)
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div>
                                <span class="font-mono font-bold text-purple-800">{{ $mandate->mandate_code }}</span>
                                <div class="font-semibold text-slate-900">{{ $mandate->property?->title_en }}</div>
                                <span class="text-[11px] text-slate-500">Owner: {{ $mandate->owner?->name }}</span>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-teal-700">{{ $mandate->management_fee_pct }}% fee</span>
                                <span class="block text-[11px] text-slate-400 mt-1">From @ethDate($mandate->start_date)</span>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-slate-400">No mandates recorded yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
