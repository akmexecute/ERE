<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8"
        x-data="{
            paymentModal: false,
            adjustModal: false,
            selectedScheduleUuid: '',
            selectedPeriodLabel: '',
            selectedScheduleDue: 0,
            selectedPaymentUuid: '',
            selectedPaymentAmount: 0,
            selectedPaymentRef: ''
        }">

        <!-- Breadcrumb & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-6">
            <div>
                <nav class="text-xs text-slate-500 mb-1 flex items-center gap-2">
                    <a href="{{ url('/dashboard') }}" class="hover:text-teal-700">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('leases.show', $lease->uuid) }}" class="hover:text-teal-700">Contract #{{ $lease->lease_code }}</a>
                    <span>/</span>
                    <span class="text-slate-800 font-medium">Rent Schedule & Financial Ledger</span>
                </nav>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Rent Schedule & Payments</h1>
                <p class="text-sm text-slate-600 mt-1">
                    Persistent billing periods for {{ $lease->property?->title ?? 'Contract Property' }}
                    @if($lease->projectUnit) (Unit #{{ $lease->projectUnit->unit_number ?? $lease->projectUnit->id }}) @endif
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('leases.show', $lease->uuid) }}" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 text-sm font-semibold transition">
                    &larr; Back to Contract
                </a>
            </div>
        </div>

        <!-- Ledger Financial Overview Cards -->
        @php
            $schedules = $lease->rentSchedules ?? collect();
            $totalRentDue = $schedules->sum('net_amount_due_etb');
            $totalPaid = $schedules->sum('amount_paid_etb');
            $totalOutstanding = $schedules->sum('outstanding_balance_etb');
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Contract Value</span>
                <div class="text-2xl font-bold text-slate-900 mt-1">{{ number_format((float) $totalRentDue, 2) }} <span class="text-xs font-semibold text-slate-400">ETB</span></div>
                <p class="text-xs text-slate-500 mt-1">{{ $schedules->count() }} scheduled payment periods</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Confirmed Paid</span>
                <div class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format((float) $totalPaid, 2) }} <span class="text-xs font-semibold text-slate-400">ETB</span></div>
                <p class="text-xs text-slate-500 mt-1">Reconciled via official receipts</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Outstanding Balance</span>
                <div class="text-2xl font-bold {{ $totalOutstanding > 0 ? 'text-amber-700' : 'text-slate-900' }} mt-1">
                    {{ number_format((float) $totalOutstanding, 2) }} <span class="text-xs font-semibold text-slate-400">ETB</span>
                </div>
                <p class="text-xs text-slate-500 mt-1">Remaining to be settled</p>
            </div>
        </div>

        <!-- Schedule Periods Table -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
            <div class="p-6 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Payment Installments</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Frequency: <span class="capitalize font-semibold text-slate-700">{{ is_object($lease->payment_frequency) ? $lease->payment_frequency->label() : ucwords(str_replace('_', ' ', (string) $lease->payment_frequency)) }}</span> • Due Day: Day {{ $lease->due_day_of_month }}</p>
                </div>
            </div>

            @if($schedules->count() > 0)
                <div class="divide-y divide-slate-100">
                    @foreach($schedules as $period)
                        @php
                            $pStatusVal = is_object($period->status) ? $period->status->value : $period->status;
                            $pStatusLbl = is_object($period->status) ? $period->status->label() : ucwords(str_replace('_', ' ', (string) $period->status));
                        @endphp
                        <div class="p-6 hover:bg-slate-50/60 transition space-y-4">
                            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2.5">
                                        <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                            #{{ $period->sequence_number }}
                                        </span>
                                        <span class="font-bold text-slate-900 text-base">{{ $period->period_label }}</span>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold
                                            @if($pStatusVal === 'paid') bg-emerald-100 text-emerald-800
                                            @elseif($pStatusVal === 'partially_paid') bg-teal-100 text-teal-800
                                            @elseif($pStatusVal === 'pending') bg-amber-100 text-amber-800
                                            @elseif($pStatusVal === 'overdue') bg-red-100 text-red-800
                                            @else bg-slate-100 text-slate-700 @endif">
                                            {{ $pStatusLbl }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-500 flex flex-wrap items-center gap-3">
                                        <span>Period: <x-calendar-date :date="$period->period_start_date" /> – <x-calendar-date :date="$period->period_end_date" /></span>
                                        <span>•</span>
                                        <span>Due Date: <strong class="text-slate-800"><x-calendar-date :date="$period->due_date" /></strong></span>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-6 text-xs">
                                    <div>
                                        <span class="text-slate-400 block">Net Due</span>
                                        <span class="font-bold text-slate-900 text-sm">{{ number_format((float) $period->net_amount_due_etb, 2) }} ETB</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block">Confirmed Paid</span>
                                        <span class="font-bold text-emerald-700 text-sm">{{ number_format((float) $period->amount_paid_etb, 2) }} ETB</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block">Balance</span>
                                        <span class="font-bold {{ $period->outstanding_balance_etb > 0 ? 'text-amber-700' : 'text-slate-900' }} text-sm">
                                            {{ number_format((float) $period->outstanding_balance_etb, 2) }} ETB
                                        </span>
                                    </div>

                                    @if(!empty($isTenant) && $period->outstanding_balance_etb > 0)
                                        <div>
                                            <button
                                                type="button"
                                                @click="
                                                    paymentModal = true;
                                                    selectedScheduleUuid = '{{ $period->uuid }}';
                                                    selectedPeriodLabel = '{{ $period->period_label }}';
                                                    selectedScheduleDue = {{ (float) $period->outstanding_balance_etb }};
                                                "
                                                class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white font-semibold transition text-xs shadow-xs"
                                            >
                                                Make Payment
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Recorded Payments & Adjustments for this period -->
                            @if(($period->payments && $period->payments->count() > 0) || ($period->adjustments && $period->adjustments->count() > 0))
                                <div class="bg-slate-50/80 rounded-xl p-4 border border-slate-200/60 space-y-3">
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600 block">
                                        Transactions & Financial Adjustments (SRS Section 189)
                                    </span>

                                    <!-- Payments -->
                                    @foreach($period->payments as $pmt)
                                        @php
                                            $pmtStatusVal = is_object($pmt->status) ? $pmt->status->value : $pmt->status;
                                            $pmtStatusLbl = is_object($pmt->status) ? $pmt->status->label() : ucwords(str_replace('_', ' ', (string) $pmt->status));
                                        @endphp
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs border-b border-slate-200/50 pb-2.5 last:border-0 last:pb-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono font-semibold text-slate-800">{{ $pmt->payment_reference }}</span>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                                                    {{ $pmtStatusVal === 'confirmed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                                    {{ $pmtStatusLbl }}
                                                </span>
                                                <span class="text-slate-500 capitalize">via {{ str_replace('_', ' ', (string) $pmt->payment_method) }}</span>
                                                <span class="text-slate-400">• {{ $pmt->created_at?->format('M d, Y H:i') ?? 'Recent' }}</span>
                                            </div>

                                            <div class="flex items-center gap-3">
                                                <span class="font-bold text-slate-900">
                                                    {{ number_format((float) $pmt->effectiveAmountEtb(), 2) }} ETB
                                                    @if($pmt->effectiveAmountEtb() != $pmt->amount_etb)
                                                        <span class="text-[10px] text-slate-400 font-normal">(orig: {{ number_format((float) $pmt->amount_etb, 2) }})</span>
                                                    @endif
                                                </span>

                                                @if($pmtStatusVal === 'pending' && (!empty($isLandlord) || !empty($isStaff)))
                                                    <form method="POST" action="{{ route('rent.payments.confirm', $pmt->uuid) }}">
                                                        @csrf
                                                        <button type="submit" class="px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold transition text-[11px]">
                                                            Confirm Receipt
                                                        </button>
                                                    </form>
                                                @endif

                                                @if($pmt->receipt)
                                                    <a href="{{ route('rent.receipt', $pmt->receipt->uuid) }}" class="text-teal-700 hover:text-teal-900 font-semibold underline text-[11px]">
                                                        Receipt #{{ $pmt->receipt->receipt_number }}
                                                    </a>
                                                @endif

                                                @if((!empty($isLandlord) || !empty($isStaff)) && $pmtStatusVal === 'confirmed')
                                                    <button
                                                        type="button"
                                                        @click="
                                                            adjustModal = true;
                                                            selectedPaymentUuid = '{{ $pmt->uuid }}';
                                                            selectedPaymentRef = '{{ $pmt->payment_reference }}';
                                                            selectedPaymentAmount = {{ (float) $pmt->amount_etb }};
                                                        "
                                                        class="text-amber-700 hover:text-amber-900 text-[11px] font-semibold"
                                                    >
                                                        Adjust Amount
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach

                                    <!-- Adjustments List (Append-Only) -->
                                    @if($period->adjustments && $period->adjustments->count() > 0)
                                        <div class="border-t border-slate-200/50 pt-2 space-y-1.5">
                                            <span class="text-[10px] font-bold text-amber-800 uppercase tracking-wider block">Audited Adjustment Entries</span>
                                            @foreach($period->adjustments as $adj)
                                                <div class="text-[11px] text-slate-600 flex justify-between items-center bg-amber-50/50 p-2 rounded-lg border border-amber-200/50">
                                                    <div>
                                                        <span class="font-bold {{ $adj->adjustment_amount_etb > 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                                            {{ $adj->adjustment_amount_etb > 0 ? '+' : '' }}{{ number_format((float) $adj->adjustment_amount_etb, 2) }} ETB
                                                        </span>
                                                        <span class="text-slate-500 ml-1.5">— {{ $adj->reason }}</span>
                                                    </div>
                                                    <div class="text-slate-400 text-[10px]">
                                                        By {{ $adj->authorizedBy?->name ?? 'System Officer' }} on {{ $adj->created_at?->format('M d, Y H:i') ?? 'Recent' }}
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-12 text-center text-xs text-slate-500">
                    No rent schedules generated yet. Once both tenant and landlord complete bilateral signing, schedules are generated automatically.
                </div>
            @endif
        </div>

        <!-- Payment Submission Modal (Alpine.js) -->
        <div
            x-show="paymentModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 flex items-center justify-center p-4"
            x-cloak
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-6 shadow-xl" @click.away="paymentModal = false">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">
                        Record Rental Payment
                    </h3>
                    <button type="button" @click="paymentModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs">
                    <span class="text-slate-500 block">Target Billing Period</span>
                    <span class="font-bold text-slate-900" x-text="selectedPeriodLabel"></span>
                </div>

                <form :action="'/rent-schedules/' + selectedScheduleUuid + '/payments'" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="amount_etb" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Payment Amount (ETB) *
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            min="1"
                            id="amount_etb"
                            name="amount_etb"
                            :value="selectedScheduleDue"
                            required
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
                        >
                    </div>

                    <div>
                        <label for="payment_method" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Payment Channel *
                        </label>
                        <select
                            id="payment_method"
                            name="payment_method"
                            required
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600 bg-white"
                        >
                            <option value="cbe_birr">CBE Birr</option>
                            <option value="telebirr">Telebirr</option>
                            <option value="bank_transfer">Bank Transfer (CBE / Awash / Dashen)</option>
                            <option value="cash">Cash Receipt (Authorized In-Person)</option>
                            <option value="chapa">Chapa Payment Gateway</option>
                        </select>
                    </div>

                    <div>
                        <label for="provider_reference" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Transaction / Reference Number
                        </label>
                        <input
                            type="text"
                            id="provider_reference"
                            name="provider_reference"
                            placeholder="e.g. FT260193492 or Telebirr Trans ID"
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
                        >
                    </div>

                    <div>
                        <label for="notes" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Notes
                        </label>
                        <input
                            type="text"
                            id="notes"
                            name="notes"
                            placeholder="Optional notes or teller reference"
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
                        >
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="paymentModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 text-xs font-semibold">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold shadow-xs">
                            Submit Payment Record
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Append-Only Adjustment Modal (Alpine.js) -->
        <div
            x-show="adjustModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 flex items-center justify-center p-4"
            x-cloak
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-6 shadow-xl" @click.away="adjustModal = false">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">
                        Append Financial Adjustment
                    </h3>
                    <button type="button" @click="adjustModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <div class="bg-amber-50 p-3.5 rounded-xl border border-amber-200 text-xs text-amber-900 space-y-1">
                    <div class="font-bold">SRS Section 189 Compliance: Append-Only</div>
                    <p>The original payment (<span class="font-mono font-semibold" x-text="selectedPaymentRef"></span>) amount will remain strictly immutable. An explicit signed ledger entry will be recorded.</p>
                </div>

                <form :action="'/rental-payments/' + selectedPaymentUuid + '/adjust'" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="adjustment_amount_etb" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Adjustment Amount (ETB) *
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            id="adjustment_amount_etb"
                            name="adjustment_amount_etb"
                            placeholder="Use negative (-500) for refund/correction, positive (+500) for penalty/surcharge"
                            required
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
                        >
                        <span class="text-[10px] text-slate-500 block mt-1">E.g. -1000.00 for partial rebate/refund, 500.00 for late charge</span>
                    </div>

                    <div>
                        <label for="reason" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                            Mandatory Justification / Reason *
                        </label>
                        <textarea
                            id="reason"
                            name="reason"
                            rows="3"
                            required
                            minlength="5"
                            placeholder="State the audit justification (e.g. Utility adjustment agreement, maintenance reimbursement)..."
                            class="w-full rounded-xl border border-slate-300 p-3 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="adjustModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 text-xs font-semibold">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shadow-xs">
                            Append Ledger Adjustment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
