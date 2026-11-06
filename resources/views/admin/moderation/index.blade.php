<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <!-- Top Breadcrumb & Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <nav class="text-xs text-slate-500 mb-1">
                    <a href="{{ route('admin.dashboard') }}" class="hover:underline">Admin Center</a> /
                    <span class="text-slate-800 font-semibold">Listing Moderation Workbench</span>
                </nav>
                <h1 class="text-2xl font-bold text-slate-900">Listing Moderation & Fraud Intelligence</h1>
            </div>

            <div class="flex items-center gap-2">
                <span class="px-3 py-1.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs font-semibold">
                    🛡️ SRS Section 134 Compliant
                </span>
            </div>
        </div>

        <!-- Filter Status Tabs -->
        <div class="flex items-center gap-2 border-b border-slate-200 pb-3">
            @foreach([
                'pending_review' => 'Pending Review',
                'published' => 'Published',
                'suspended' => 'Suspended',
                'rejected' => 'Rejected',
                'all' => 'All Listings'
            ] as $stKey => $stLabel)
                <a
                    href="{{ route('admin.moderation.index', ['status' => $stKey]) }}"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition {{ $currentStatus === $stKey ? 'bg-teal-700 text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' }}"
                >
                    {{ $stLabel }}
                </a>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Left 8 Columns: Listings Table -->
            <div class="lg:col-span-8 space-y-4">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-slate-900">
                            Listings in Queue ({{ $listings->total() }})
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-500 border-b border-slate-200 uppercase font-semibold">
                                <tr>
                                    <th class="p-3">Listing</th>
                                    <th class="p-3">Seller</th>
                                    <th class="p-3">Price</th>
                                    <th class="p-3">Signals</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($listings as $item)
                                    <tr class="hover:bg-slate-50">
                                        <td class="p-3">
                                            <a href="{{ route('admin.moderation.show', $item) }}" class="font-bold text-teal-800 hover:underline">
                                                {{ $item->property->title_en }}
                                            </a>
                                            <div class="text-[11px] text-slate-400 font-mono">{{ $item->listing_code }}</div>
                                        </td>
                                        <td class="p-3">
                                            <span class="text-slate-800">{{ $item->property->owner->name }}</span>
                                        </td>
                                        <td class="p-3 font-semibold text-slate-900">
                                            {{ \App\Support\Money\EtbFormatter::compact($item->price_etb) }}
                                        </td>
                                        <td class="p-3">
                                            @if($item->open_signals_count > 0)
                                                <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-800 text-[10px] font-bold">
                                                    ⚠️ {{ $item->open_signals_count }} signal(s)
                                                </span>
                                            @else
                                                <span class="text-slate-400 text-[11px]">Clear</span>
                                            @endif
                                        </td>
                                        <td class="p-3">
                                            <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold
                                                {{ ($item->status->value ?? (string) $item->status) === 'published' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                                {{ ($item->status->value ?? (string) $item->status) === 'pending_review' ? 'bg-amber-100 text-amber-800' : '' }}
                                                {{ ($item->status->value ?? (string) $item->status) === 'suspended' ? 'bg-red-100 text-red-800' : '' }}
                                                {{ ($item->status->value ?? (string) $item->status) === 'rejected' ? 'bg-slate-200 text-slate-700' : '' }}
                                            ">
                                                {{ $item->status instanceof \BackedEnum ? $item->status->value : $item->status }}
                                            </span>
                                        </td>
                                        <td class="p-3 text-right">
                                            <a
                                                href="{{ route('admin.moderation.show', $item) }}"
                                                class="px-2.5 py-1 rounded-lg bg-teal-50 text-teal-800 hover:bg-teal-100 font-semibold"
                                            >
                                                Inspect
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="p-6 text-center text-slate-400">
                                            No listings found matching this filter.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($listings->hasPages())
                        <div class="p-4 border-t border-slate-100">
                            {{ $listings->links() }}
                        </div>
                    @endif
                </div>
            </div>

            <!-- Right 4 Columns: Open Fraud Signals & Public Reports Feed -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Open Fraud Signals Feed -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
                            <span>🚨</span>
                            <span>Active Fraud Signals ({{ $openFraudSignals->count() }})</span>
                        </h3>
                    </div>

                    <div class="space-y-2.5">
                        @forelse($openFraudSignals as $sig)
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-800 uppercase tracking-tight text-[11px]">
                                        {{ str_replace('_', ' ', $sig->signal_type) }}
                                    </span>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase
                                        {{ $sig->severity === 'critical' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }}
                                    ">
                                        {{ $sig->severity }}
                                    </span>
                                </div>
                                <p class="text-slate-600 line-clamp-1">
                                    Property: <a href="{{ route('admin.moderation.show', $sig->listing_id) }}" class="text-teal-700 underline font-semibold">{{ $sig->listing?->property?->title_en ?? "Listing #{$sig->listing_id}" }}</a>
                                </p>
                                <div class="text-[11px] text-slate-400">
                                    Confidence: {{ $sig->confidence_score }}% • {{ $sig->created_at->diffForHumans() }}
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-slate-400 py-2">No open automated fraud signals.</p>
                        @endforelse
                    </div>
                </div>

                <!-- Open Public Reports Feed -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
                            <span>🚩</span>
                            <span>User Reports ({{ $openReports->count() }})</span>
                        </h3>
                    </div>

                    <div class="space-y-2.5">
                        @forelse($openReports as $rep)
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-red-700 uppercase tracking-tight text-[11px]">
                                        {{ str_replace('_', ' ', $rep->reason_code) }}
                                    </span>
                                    <span class="text-[10px] text-slate-400">{{ $rep->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-slate-600 line-clamp-2">{{ $rep->description ?? 'No description provided.' }}</p>
                                <div class="pt-1 flex items-center justify-between">
                                    <span class="text-[11px] text-slate-400">Listing #{{ $rep->listing_id }}</span>
                                    <a href="{{ route('admin.moderation.show', $rep->listing_id) }}" class="text-teal-700 font-semibold hover:underline">Inspect</a>
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-slate-400 py-2">No open user property reports.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
