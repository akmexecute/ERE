<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
        <!-- Header & Breadcrumbs -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-6">
            <div>
                <nav class="text-xs text-slate-500 mb-1 flex items-center gap-2">
                    <a href="{{ route('invoices.index') }}" class="hover:text-teal-700">Invoices</a>
                    <span>/</span>
                    <span class="font-mono text-slate-800 font-semibold">{{ $invoice->invoice_number }}</span>
                </nav>
                <div class="flex items-center gap-3">
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Invoice {{ $invoice->invoice_number }}</h1>
                    @php
                        $statusClass = match($invoice->payment_status) {
                            'paid' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                            'partially_paid' => 'bg-amber-100 text-amber-800 border-amber-200',
                            'unpaid' => 'bg-red-50 text-red-700 border-red-200',
                            'voided' => 'bg-slate-100 text-slate-500 border-slate-200',
                            default => 'bg-slate-100 text-slate-700',
                        };
                    @endphp
                    <span class="px-3 py-1 rounded-full text-xs font-semibold border capitalize {{ $statusClass }}">
                        {{ str_replace('_', ' ', $invoice->payment_status) }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Issued on <strong>@ethDate($invoice->issue_date)</strong> ({{ $invoice->issue_date->format('F d, Y') }})
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('invoices.print', $invoice->uuid) }}" target="_blank" class="px-4 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold shadow-xs transition flex items-center gap-2">
                    <span>🖨️</span>
                    <span>Print Official Invoice</span>
                </a>

                @if($invoice->payment_status !== 'voided' && $invoice->payment_status !== 'paid' && \App\Domains\Portfolio\Services\MultiTenantOrganizationGuard::canAccess(auth()->user(), $invoice))
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" class="px-3.5 py-2.5 rounded-xl border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 text-xs font-semibold shadow-xs transition flex items-center gap-1.5">
                            <span>🚫</span>
                            <span>Void Invoice</span>
                        </button>

                        <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-2 w-80 bg-white rounded-2xl border border-slate-200 shadow-xl p-5 z-50 space-y-4">
                            <div>
                                <h4 class="text-sm font-bold text-slate-900">Void Invoice {{ $invoice->invoice_number }}</h4>
                                <p class="text-xs text-slate-500 mt-1">This will permanently cancel the outstanding balance. Recorded payments and audit history will be preserved.</p>
                            </div>
                            <form method="POST" action="{{ route('invoices.void', $invoice->uuid) }}" class="space-y-3">
                                @csrf
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Reason for Voiding</label>
                                    <textarea name="reason" required rows="2" class="w-full text-xs rounded-xl border border-slate-300 p-2.5 focus:border-red-500 focus:ring-1 focus:ring-red-500" placeholder="e.g. Issued in error as duplicate, contract canceled..."></textarea>
                                </div>
                                <div class="flex justify-end gap-2 pt-1">
                                    <button type="button" @click="open = false" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-600 hover:bg-slate-50">Cancel</button>
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-semibold">Confirm Void</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Invoice Card -->
        <div class="bg-white rounded-2xl border border-slate-200 p-8 shadow-xs space-y-8">
            <!-- Header Grid: Company vs Customer -->
            <div class="grid grid-cols-2 gap-8 border-b border-slate-100 pb-6">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Billed By / Issuer</span>
                    <div class="text-base font-bold text-slate-900">{{ $invoice->company->legal_name }}</div>
                    @if($invoice->company->tin_number)
                        <div class="text-xs text-slate-500 font-mono mt-0.5">TIN: {{ $invoice->company->tin_number }}</div>
                    @endif
                    <div class="text-xs text-slate-500 mt-0.5">{{ $invoice->branch?->name_en ?? 'Head Office' }}</div>
                </div>

                <div class="text-right">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Billed To / Customer</span>
                    <div class="text-base font-bold text-slate-900">{{ $invoice->customer_name_snapshot }}</div>
                    @if($invoice->customer_tin_snapshot)
                        <div class="text-xs text-slate-500 font-mono mt-0.5">Customer TIN: {{ $invoice->customer_tin_snapshot }}</div>
                    @endif
                    <div class="text-xs text-slate-500 font-mono mt-0.5">{{ $invoice->customer?->phone }}</div>
                </div>
            </div>

            <!-- Itemized Line Items -->
            <div>
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase font-semibold">
                        <tr>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3 text-center">Qty</th>
                            <th class="px-4 py-3 text-right">Unit Price</th>
                            <th class="px-4 py-3 text-right">Tax Applied</th>
                            <th class="px-4 py-3 text-right">Line Total (ETB)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($invoice->lineItems as $item)
                            <tr>
                                <td class="px-4 py-3.5 font-medium text-slate-900">
                                    {{ $item->description }}
                                </td>
                                <td class="px-4 py-3.5 text-center font-mono">
                                    {{ number_format((float) $item->quantity, 2) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono">
                                    {{ number_format((float) $item->unit_price_etb, 2) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-slate-500">
                                    {{ $item->tax_code ? $item->tax_code.' ('.(float)$item->tax_rate_pct.'%)' : 'None' }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono font-bold text-slate-900">
                                    {{ number_format((float) $item->line_total_etb, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Financial Totals & Tax Snapshots -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-4 border-t border-slate-100">
                <!-- Applied Tax Breakdown -->
                <div class="space-y-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Applied Tax Rules Snapshot</span>
                    @if(!empty($invoice->tax_snapshots_json))
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1.5">
                            @foreach($invoice->tax_snapshots_json as $rule)
                                <div class="flex justify-between text-slate-600">
                                    <span>{{ $rule['name_en'] ?? $rule['tax_code'] }} ({{ $rule['rate_pct'] }}%):</span>
                                    <span class="font-mono font-semibold text-slate-900">ETB {{ number_format((float) $rule['tax_amount_etb'], 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <span class="text-xs text-slate-400 italic">No statutory taxes applied (Zero-rated or exempt).</span>
                    @endif
                </div>

                <!-- Ledger Totals -->
                <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 space-y-2.5 text-xs">
                    <div class="flex justify-between text-slate-600">
                        <span>Subtotal:</span>
                        <span class="font-mono font-semibold text-slate-900">ETB {{ number_format((float) $invoice->subtotal_amount_etb, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Total Statutory Taxes:</span>
                        <span class="font-mono font-semibold text-slate-900">ETB {{ number_format((float) $invoice->tax_amount_etb, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm font-bold text-slate-900 pt-2 border-t border-slate-200">
                        <span>Invoice Total:</span>
                        <span class="font-mono text-teal-800">ETB {{ number_format((float) $invoice->total_amount_etb, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-slate-600 pt-1 border-t border-slate-200/60">
                        <span>Amount Paid:</span>
                        <span class="font-mono text-emerald-700 font-semibold">ETB {{ number_format((float) $invoice->paid_amount_etb, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-xs font-bold text-slate-900">
                        <span>Outstanding Balance:</span>
                        <span class="font-mono text-red-700">ETB {{ number_format((float) $invoice->outstanding_balance_etb, 2) }}</span>
                    </div>
                </div>
            </div>

            @if($invoice->notes)
                <div class="pt-4 border-t border-slate-100 text-xs text-slate-500">
                    <strong>Notes:</strong> {{ $invoice->notes }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
