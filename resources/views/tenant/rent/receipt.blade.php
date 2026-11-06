<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6">
        <!-- Breadcrumb & Top Bar -->
        <div class="flex items-center justify-between no-print border-b border-slate-200 pb-4">
            <nav class="text-xs text-slate-500 flex items-center gap-2">
                <a href="{{ url('/dashboard') }}" class="hover:text-teal-700">Dashboard</a>
                <span>/</span>
                @if($receipt->lease)
                    <a href="{{ route('rent.schedule', $receipt->lease->uuid) }}" class="hover:text-teal-700">Rent Schedule</a>
                    <span>/</span>
                @endif
                <span class="text-slate-800 font-medium">Receipt #{{ $receipt->receipt_number }}</span>
            </nav>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    onclick="window.print()"
                    class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold shadow-xs transition flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print Official Receipt
                </button>
            </div>
        </div>

        <!-- Official Ethiopian Rental Receipt Paper Layout -->
        <div class="bg-white rounded-3xl border border-slate-300 p-8 sm:p-12 shadow-sm space-y-8 relative overflow-hidden">
            <!-- Security Watermark Stamp -->
            <div class="absolute -right-12 -bottom-12 opacity-5 pointer-events-none select-none">
                <div class="w-72 h-72 rounded-full border-8 border-teal-900 flex items-center justify-center font-bold text-5xl rotate-12 text-teal-900">
                    ETHIOPROPERTY
                </div>
            </div>

            <!-- Receipt Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start gap-6 border-b-2 border-slate-900 pb-6">
                <div>
                    <div class="flex items-center gap-2 font-bold text-xl tracking-tight text-teal-800">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-teal-800 text-white font-bold text-sm">EP</span>
                        <span>EthioProperty Marketplace</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Federal Democratic Republic of Ethiopia • Real Estate Platform</p>
                    <div class="mt-3">
                        <span class="px-2.5 py-1 rounded bg-emerald-100 text-emerald-900 text-xs font-bold uppercase tracking-wider">
                            Official Rental Payment Receipt
                        </span>
                    </div>
                </div>

                <div class="text-left sm:text-right space-y-1">
                    <span class="text-xs text-slate-400 uppercase tracking-wider block">Receipt Number</span>
                    <span class="font-mono font-bold text-lg text-slate-900 block">{{ $receipt->receipt_number }}</span>
                    <div class="text-xs text-slate-500 pt-1">
                        <span>Issued: <x-calendar-date :date="$receipt->issued_at" /></span>
                    </div>
                </div>
            </div>

            <!-- Parties & Leased Property -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
                <div class="space-y-1 bg-slate-50 p-4 rounded-2xl border border-slate-200/60">
                    <span class="text-slate-400 font-semibold uppercase tracking-wider block">Received From (Tenant)</span>
                    <div class="font-bold text-slate-900 text-sm">{{ $receipt->payer?->name ?? 'Tenant' }}</div>
                    <div class="text-slate-600">{{ $receipt->payer?->phone ?? 'N/A' }}</div>
                </div>

                <div class="space-y-1 bg-slate-50 p-4 rounded-2xl border border-slate-200/60">
                    <span class="text-slate-400 font-semibold uppercase tracking-wider block">Paid To (Landlord / Agency)</span>
                    <div class="font-bold text-slate-900 text-sm">{{ $receipt->recipient?->name ?? 'Landlord / Agency' }}</div>
                    <div class="text-slate-600">{{ $receipt->recipient?->phone ?? 'N/A' }}</div>
                </div>
            </div>

            <!-- Property & Lease Details -->
            <div class="bg-slate-50/50 rounded-2xl p-4 border border-slate-200/80 text-xs space-y-2">
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Leased Property:</span>
                    <span class="font-bold text-slate-900 text-right">{{ $receipt->lease?->property?->title ?? 'Leased Property' }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Contract Reference:</span>
                    <span class="font-mono font-semibold text-slate-800">{{ $receipt->lease?->lease_code ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Billing Period:</span>
                    <span class="font-semibold text-slate-900">{{ $receipt->rentSchedule?->period_label ?? 'Rental Period' }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Payment Channel:</span>
                    <span class="font-medium text-slate-800 capitalize">{{ str_replace('_', ' ', (string) $receipt->payment_method) }}</span>
                </div>
                @if($receipt->payment?->provider_reference)
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500">Transaction ID / Ref:</span>
                        <span class="font-mono text-slate-700">{{ $receipt->payment->provider_reference }}</span>
                    </div>
                @endif
            </div>

            <!-- Amount Settled Highlight Box -->
            <div class="rounded-2xl bg-teal-900 text-white p-6 sm:p-8 flex flex-col sm:flex-row justify-between items-center gap-4">
                <div>
                    <span class="text-xs uppercase tracking-wider text-teal-200 block">Total Confirmed Payment</span>
                    <span class="text-xs text-teal-300">Ethiopian Birr (ETB) — Net Settled</span>
                </div>
                <div class="text-3xl font-bold font-mono text-emerald-300">
                    {{ number_format((float) $receipt->amount_etb, 2) }} ETB
                </div>
            </div>

            <!-- Verification & Legal Footer -->
            <div class="border-t border-slate-200 pt-6 flex flex-col sm:flex-row justify-between items-center gap-4 text-[11px] text-slate-500">
                <div class="space-y-0.5">
                    <p class="font-medium text-slate-700">Audit & Authentication Signature:</p>
                    <p class="font-mono text-[10px] text-slate-400">{{ hash('sha256', $receipt->receipt_number . '|' . $receipt->amount_etb . '|' . $receipt->issued_at) }}</p>
                </div>
                <div class="text-center sm:text-right">
                    <p class="font-bold text-slate-800">EthioProperty Digital Vault</p>
                    <p>Generated electronically with immutable audit trail</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
