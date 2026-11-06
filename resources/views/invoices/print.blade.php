<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tax Invoice {{ $invoice->invoice_number }} — EthioProperty</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', 'Noto Sans Ethiopic', system-ui, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
        }
        @media print {
            body {
                background-color: #ffffff;
                color: #000000;
            }
            .no-print {
                display: none !important;
            }
            .print-container {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body class="py-8 px-4 sm:px-6">

    <!-- Action Toolbar (Hidden in Print) -->
    <div class="max-w-3xl mx-auto mb-6 flex items-center justify-between no-print">
        <a href="{{ route('invoices.show', $invoice->uuid) }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900 transition flex items-center gap-1">
            &larr; Return to Invoices
        </a>
        <button
            type="button"
            onclick="window.print()"
            class="px-5 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold shadow-sm transition flex items-center gap-2"
        >
            <span>🖨️</span>
            <span>Print or Save as PDF</span>
        </button>
    </div>

    <!-- Official Printable Invoice Container -->
    <div class="max-w-3xl mx-auto bg-white rounded-2xl border border-slate-200 p-8 sm:p-12 shadow-sm print-container space-y-8">
        <!-- Top Official Header -->
        <div class="flex items-start justify-between border-b-2 border-slate-900 pb-6">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-teal-800 text-white font-bold text-sm">EP</span>
                    <span class="text-lg font-bold tracking-tight text-slate-900">EthioProperty</span>
                </div>
                <div class="mt-3 text-xs text-slate-600 space-y-0.5">
                    <div class="font-bold text-slate-900">{{ $invoice->company->legal_name }}</div>
                    @if($invoice->company->tin_number)
                        <div class="font-mono">TIN: <strong>{{ $invoice->company->tin_number }}</strong></div>
                    @endif
                    <div>{{ $invoice->branch?->name_en ?? 'National Head Office' }}, Addis Ababa, Ethiopia</div>
                </div>
            </div>

            <div class="text-right space-y-1">
                <span class="text-[10px] uppercase font-bold tracking-widest text-slate-400 block">OFFICIAL TAX INVOICE / የግብር ደረሰኝ</span>
                <div class="text-xl font-bold font-mono text-slate-900">{{ $invoice->invoice_number }}</div>
                <div class="text-xs text-slate-500 font-medium">
                    <div>📅 Gregorian: <strong>{{ $invoice->issue_date->format('F d, Y') }}</strong></div>
                    <div class="text-teal-800 font-semibold">🇪🇹 Ethiopian: <strong>{{ $ethDateAm }}</strong> ({{ $ethDateEn }})</div>
                </div>
            </div>
        </div>

        <!-- Customer & Due Details Grid -->
        <div class="grid grid-cols-2 gap-8 text-xs">
            <div>
                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block mb-1">Billed To (Customer / ተከራይ)</span>
                <div class="text-sm font-bold text-slate-900">{{ $invoice->customer_name_snapshot }}</div>
                @if($invoice->customer_tin_snapshot)
                    <div class="font-mono text-slate-500 mt-0.5">Customer TIN: {{ $invoice->customer_tin_snapshot }}</div>
                @endif
                <div class="font-mono text-slate-500 mt-0.5">Phone: {{ $invoice->customer?->phone }}</div>
            </div>

            <div class="text-right space-y-1">
                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block mb-1">Invoice Status & Terms</span>
                <div>Payment Terms: <strong>Due in 30 Days</strong></div>
                @if($invoice->due_date)
                    <div>Due Date: <strong>{{ $invoice->due_date->format('M d, Y') }}</strong> (@ethDate($invoice->due_date))</div>
                @endif
                <div class="pt-1">
                    <span class="px-2.5 py-0.5 rounded text-[11px] font-bold uppercase border {{ $invoice->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-900 border-emerald-300' : 'bg-amber-50 text-amber-900 border-amber-300' }}">
                        {{ strtoupper($invoice->payment_status) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Line Items Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-800">
                <thead class="border-b-2 border-slate-200 uppercase font-semibold text-[11px] text-slate-500">
                    <tr>
                        <th class="py-2.5">Item & Description</th>
                        <th class="py-2.5 text-center">Qty</th>
                        <th class="py-2.5 text-right">Unit Price (ETB)</th>
                        <th class="py-2.5 text-right">Tax Applied</th>
                        <th class="py-2.5 text-right">Total (ETB)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($invoice->lineItems as $item)
                        <tr>
                            <td class="py-3 font-medium">
                                {{ $item->description }}
                            </td>
                            <td class="py-3 text-center font-mono">
                                {{ number_format((float) $item->quantity, 2) }}
                            </td>
                            <td class="py-3 text-right font-mono">
                                {{ number_format((float) $item->unit_price_etb, 2) }}
                            </td>
                            <td class="py-3 text-right font-mono text-slate-500">
                                {{ $item->tax_code ? $item->tax_code.' ('.(float)$item->tax_rate_pct.'%)' : '0.00%' }}
                            </td>
                            <td class="py-3 text-right font-mono font-bold text-slate-900">
                                {{ number_format((float) $item->line_total_etb, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Tax Breakdown & Grand Totals -->
        <div class="grid grid-cols-2 gap-8 pt-4 border-t border-slate-200 text-xs">
            <!-- Applied Statutory Tax Breakdown -->
            <div class="space-y-2">
                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block">Tax Reconciliation & Breakdown</span>
                @if(!empty($invoice->tax_snapshots_json))
                    <div class="space-y-1 text-slate-600">
                        @foreach($invoice->tax_snapshots_json as $tax)
                            <div class="flex justify-between py-0.5 border-b border-slate-100 font-mono text-[11px]">
                                <span>{{ $tax['tax_code'] }} ({{ $tax['rate_pct'] }}%):</span>
                                <span>ETB {{ number_format((float) $tax['tax_amount_etb'], 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <span class="text-slate-400 italic">Zero-rated statutory transaction.</span>
                @endif

                @if($invoice->notes)
                    <div class="pt-2 text-slate-500 text-[11px]">
                        <strong>Notes:</strong> {{ $invoice->notes }}
                    </div>
                @endif
            </div>

            <!-- Ledger Summary -->
            <div class="space-y-2 text-slate-700">
                <div class="flex justify-between font-mono">
                    <span>Subtotal:</span>
                    <span>ETB {{ number_format((float) $invoice->subtotal_amount_etb, 2) }}</span>
                </div>
                <div class="flex justify-between font-mono">
                    <span>Statutory Taxes:</span>
                    <span>ETB {{ number_format((float) $invoice->tax_amount_etb, 2) }}</span>
                </div>
                <div class="flex justify-between text-sm font-bold text-slate-900 pt-2 border-t-2 border-slate-900 font-mono">
                    <span>Invoice Total:</span>
                    <span>ETB {{ number_format((float) $invoice->total_amount_etb, 2) }}</span>
                </div>
                <div class="flex justify-between font-mono text-emerald-700 font-semibold pt-1 border-t border-slate-200">
                    <span>Amount Paid:</span>
                    <span>ETB {{ number_format((float) $invoice->paid_amount_etb, 2) }}</span>
                </div>
                <div class="flex justify-between font-mono text-red-700 font-bold">
                    <span>Balance Due:</span>
                    <span>ETB {{ number_format((float) $invoice->outstanding_balance_etb, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Statutory Footer Notice (SRS Section 177) -->
        <div class="pt-6 border-t border-slate-200 text-center text-[10px] text-slate-400 space-y-1">
            <p>
                This invoice is issued under the Ethiopian Real Estate Proclamation No. 1357/2024 and Ethiopian Tax Authority regulations.
            </p>
            <p class="font-mono">
                Verification Hash: {{ hash('sha256', $invoice->invoice_number . '|' . $invoice->total_amount_etb . '|' . $invoice->issue_date->toDateString()) }}
            </p>
        </div>
    </div>

</body>
</html>
