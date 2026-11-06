<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
        <!-- Header & Breadcrumbs -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-6">
            <div>
                <nav class="text-xs text-slate-500 mb-1 flex items-center gap-2">
                    <a href="{{ url('/dashboard') }}" class="hover:text-teal-700">Dashboard</a>
                    <span>/</span>
                    <span class="text-slate-800 font-medium">Invoicing & Tax Engine</span>
                </nav>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">ETB Tax Invoices & Receipts</h1>
                <p class="text-sm text-slate-600 mt-1">Database-driven tax calculations, sequential numbering, and official receipts with dual Ethiopian/Gregorian calendars.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('invoices.create') }}" class="px-4 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold shadow-xs transition flex items-center gap-2">
                    <span>+</span>
                    <span>Issue New Invoice</span>
                </a>
            </div>
        </div>

        <!-- Invoices Table -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase font-semibold">
                        <tr>
                            <th class="px-6 py-3.5">Invoice #</th>
                            <th class="px-6 py-3.5">Customer</th>
                            <th class="px-6 py-3.5">Service Type</th>
                            <th class="px-6 py-3.5">Issue Date</th>
                            <th class="px-6 py-3.5">Subtotal</th>
                            <th class="px-6 py-3.5">Tax (VAT/TOT)</th>
                            <th class="px-6 py-3.5">Total (ETB)</th>
                            <th class="px-6 py-3.5">Status</th>
                            <th class="px-6 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($invoices as $inv)
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="px-6 py-4 font-mono font-bold text-teal-800">
                                    {{ $inv->invoice_number }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-slate-900">{{ $inv->customer_name_snapshot }}</div>
                                    <span class="text-[11px] text-slate-400 font-mono">{{ $inv->customer?->phone }}</span>
                                </td>
                                <td class="px-6 py-4 capitalize font-medium">
                                    {{ str_replace('_', ' ', $inv->service_type) }}
                                </td>
                                <td class="px-6 py-4">
                                    <div>@ethDate($inv->issue_date)</div>
                                    <span class="text-[11px] text-slate-400 font-mono">{{ $inv->issue_date->format('M d, Y') }}</span>
                                </td>
                                <td class="px-6 py-4 font-mono">
                                    {{ number_format((float) $inv->subtotal_amount_etb, 2) }}
                                </td>
                                <td class="px-6 py-4 font-mono text-slate-500">
                                    {{ number_format((float) $inv->tax_amount_etb, 2) }}
                                </td>
                                <td class="px-6 py-4 font-mono font-bold text-slate-900">
                                    ETB {{ number_format((float) $inv->total_amount_etb, 2) }}
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $statusClass = match($inv->payment_status) {
                                            'paid' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                            'partially_paid' => 'bg-amber-100 text-amber-800 border-amber-200',
                                            'unpaid' => 'bg-red-50 text-red-700 border-red-200',
                                            'voided' => 'bg-slate-100 text-slate-500 border-slate-200',
                                            default => 'bg-slate-100 text-slate-700',
                                        };
                                    @endphp
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold border capitalize {{ $statusClass }}">
                                        {{ str_replace('_', ' ', $inv->payment_status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <a href="{{ route('invoices.show', $inv->uuid) }}" class="px-2.5 py-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 font-semibold transition">
                                        View
                                    </a>
                                    <a href="{{ route('invoices.print', $inv->uuid) }}" target="_blank" class="px-2.5 py-1.5 rounded-lg bg-teal-50 text-teal-700 hover:bg-teal-100 font-semibold transition">
                                        🖨️ Print
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                                    No invoices issued yet. Click "+ Issue New Invoice" to create a tax invoice.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($invoices->hasPages())
                <div class="p-4 border-t border-slate-200">
                    {{ $invoices->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
