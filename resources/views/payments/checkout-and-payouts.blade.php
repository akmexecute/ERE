<x-app-layout>
    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 dark:border-slate-800 pb-5">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        {{ ($viewMode ?? 'checkout') === 'payouts' ? __('Host & Landlord Payout Console') : __('Secure Unified Checkout') }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ ($viewMode ?? 'checkout') === 'payouts' ? __('Auditable financial waterfall, release schedules, and payout settlement.') : __('Unified Ethiopian payment engine supporting Telebirr, CBE Birr, Chapa cards, and bank transfers.') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('payments.checkout') }}" class="inline-flex items-center rounded-lg px-3.5 py-2 text-xs font-semibold {{ ($viewMode ?? 'checkout') === 'checkout' ? 'bg-amber-600 text-white shadow' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' }}">
                        <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        {{ __('Checkout') }}
                    </a>
                    <a href="{{ route('payments.payouts') }}" class="inline-flex items-center rounded-lg px-3.5 py-2 text-xs font-semibold {{ ($viewMode ?? 'checkout') === 'payouts' ? 'bg-amber-600 text-white shadow' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' }}">
                        <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ __('Payout Console') }}
                    </a>
                </div>
            </div>
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (($viewMode ?? 'checkout') === 'checkout')
                {{-- CHECKOUT WORKFLOW --}}
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-12" x-data="{
                    paymentMethod: 'telebirr',
                    customAmount: '{{ $serverPricing['total_amount_etb'] ?? 0.0 }}',
                    notes: '',
                    idempotencyKey: '{{ (string) \Illuminate\Support\Str::uuid() }}',
                    isSubmitting: false
                }">
                    {{-- Left column: Payable & Payment Selection --}}
                    <div class="lg:col-span-7 space-y-6">
                        @if ($purchaseDetails)
                            {{-- Property Purchase Banner --}}
                            <div class="rounded-2xl border border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 p-6 dark:border-amber-900/60 dark:from-amber-950/20 dark:to-orange-950/20">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <span class="inline-flex items-center rounded-md bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                            {{ __('Property / Land Acquisition') }}
                                        </span>
                                        @if (!empty($purchaseDetails['deal_type']))
                                            <span class="ml-1 text-xs text-slate-500 font-medium">({{ $purchaseDetails['deal_type'] }})</span>
                                        @endif
                                        @if (!empty($purchaseDetails['is_fully_paid']))
                                            <span class="ml-2 inline-flex items-center rounded-md bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                                {{ __('Fully Settled') }}
                                            </span>
                                        @endif
                                        <h3 class="mt-2 text-lg font-bold text-slate-900 dark:text-white">
                                            {{ $purchaseDetails['property_title'] }}
                                        </h3>
                                        <p class="text-xs text-slate-600 dark:text-slate-400">
                                            {{ __('Agreement Code:') }} <span class="font-mono font-bold">{{ $purchaseDetails['offer_code'] }}</span>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs font-medium text-slate-500">{{ __('Agreed Price') }}</div>
                                        <div class="text-lg font-black text-slate-900 dark:text-white">
                                            ETB {{ number_format($purchaseDetails['agreed_price_etb'], 2) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-2 gap-4 border-t border-amber-200/80 pt-4 dark:border-amber-900/40">
                                    <div>
                                        <div class="text-xs text-slate-500">{{ __('Confirmed Paid So Far') }}</div>
                                        <div class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                            ETB {{ number_format($purchaseDetails['total_paid_etb'], 2) }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs text-slate-500">{{ __('Remaining Balance') }}</div>
                                        <div class="text-sm font-bold text-amber-600 dark:text-amber-400">
                                            ETB {{ number_format($purchaseDetails['remaining_balance_etb'], 2) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 rounded-xl bg-white/80 dark:bg-slate-900/80 p-4 border border-amber-200 dark:border-amber-800">
                                    <label for="customAmount" class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                        {{ __('Installment / Payment Amount (ETB)') }}
                                    </label>
                                    <div class="mt-1.5 flex rounded-lg shadow-xs">
                                        <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-xs text-slate-500 font-bold dark:border-slate-700 dark:bg-slate-800">
                                            ETB
                                        </span>
                                        <input type="number" id="customAmount" name="custom_amount_display" x-model="customAmount" min="1" step="0.01" class="block w-full rounded-none rounded-r-lg border border-slate-300 px-3 py-2 text-sm font-mono font-bold text-slate-900 focus:border-amber-500 focus:ring-amber-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                    </div>
                                    <p class="mt-1 text-[11px] text-slate-500">{{ __('Specify deposit, milestone installment, or full remaining balance.') }}</p>
                                </div>

                                <div class="mt-4 rounded-lg bg-amber-100/70 p-3 text-xs text-amber-900 dark:bg-amber-950/50 dark:text-amber-200 flex items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0 text-amber-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                                    <span>{{ __('Notice: Financial settlement records funds on the ledger. Legal title deed transfer remains separate under civil registration.') }}</span>
                                </div>
                            </div>
                        @endif

                        {{-- Payment Method Selection Card --}}
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h2 class="text-base font-bold text-slate-900 dark:text-white">
                                {{ __('Select Payment Method') }}
                            </h2>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ __('All digital transactions are encrypted, idempotent, and authenticated via provider webhooks.') }}
                            </p>

                            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                {{-- Telebirr --}}
                                <label class="relative flex cursor-pointer rounded-xl border p-4 transition-all focus:outline-none"
                                       :class="paymentMethod === 'telebirr' ? 'border-amber-600 bg-amber-50/40 ring-1 ring-amber-600 dark:bg-amber-950/20' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300'">
                                    <input type="radio" name="payment_method" value="telebirr" x-model="paymentMethod" class="sr-only">
                                    <div class="flex w-full items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-100 text-sky-700 font-black text-xs">
                                                TB
                                            </div>
                                            <div>
                                                <div class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Telebirr') }}</div>
                                                <div class="text-xs text-slate-500">{{ __('Ethio Telecom SuperApp') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </label>

                                {{-- CBE Birr --}}
                                <label class="relative flex cursor-pointer rounded-xl border p-4 transition-all focus:outline-none"
                                       :class="paymentMethod === 'cbe_birr' ? 'border-amber-600 bg-amber-50/40 ring-1 ring-amber-600 dark:bg-amber-950/20' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300'">
                                    <input type="radio" name="payment_method" value="cbe_birr" x-model="paymentMethod" class="sr-only">
                                    <div class="flex w-full items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100 text-purple-700 font-black text-xs">
                                                CBE
                                            </div>
                                            <div>
                                                <div class="text-sm font-bold text-slate-900 dark:text-white">{{ __('CBE Birr') }}</div>
                                                <div class="text-xs text-slate-500">{{ __('Commercial Bank *847#') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </label>

                                {{-- Card Payments (Visa / Mastercard via Chapa) --}}
                                <label class="relative flex cursor-pointer rounded-xl border p-4 transition-all focus:outline-none"
                                       :class="paymentMethod === 'card' ? 'border-amber-600 bg-amber-50/40 ring-1 ring-amber-600 dark:bg-amber-950/20' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300'">
                                    <input type="radio" name="payment_method" value="card" x-model="paymentMethod" class="sr-only">
                                    <div class="flex w-full items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 font-black text-xs">
                                                CARD
                                            </div>
                                            <div>
                                                <div class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Visa / Mastercard') }}</div>
                                                <div class="text-xs text-slate-500">{{ __('Debit & Credit Cards') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </label>

                                {{-- Bank Transfer --}}
                                <label class="relative flex cursor-pointer rounded-xl border p-4 transition-all focus:outline-none"
                                       :class="paymentMethod === 'bank_transfer' ? 'border-amber-600 bg-amber-50/40 ring-1 ring-amber-600 dark:bg-amber-950/20' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300'">
                                    <input type="radio" name="payment_method" value="bank_transfer" x-model="paymentMethod" class="sr-only">
                                    <div class="flex w-full items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-700 font-black text-xs">
                                                SLIP
                                            </div>
                                            <div>
                                                <div class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Bank Deposit / Slip') }}</div>
                                                <div class="text-xs text-slate-500">{{ __('CBE / Awash Transfer') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            {{-- Bank Transfer Instructions Panel --}}
                            <div x-show="paymentMethod === 'bank_transfer'" x-cloak class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/60">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    {{ __('Official Escrow Bank Accounts') }}
                                </h4>
                                <div class="mt-3 space-y-2 text-xs text-slate-600 dark:text-slate-300 font-mono">
                                    <div class="rounded-lg bg-white p-2.5 border border-slate-200 dark:bg-slate-900 dark:border-slate-700 flex justify-between items-center">
                                        <div>
                                            <div class="font-bold text-slate-900 dark:text-white">Commercial Bank of Ethiopia (CBE)</div>
                                            <div>A/C: <span class="font-bold">1000293847561</span> (ERE Escrow Trust)</div>
                                        </div>
                                        <span class="text-[10px] bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded text-slate-500">Bole Branch</span>
                                    </div>
                                    <div class="rounded-lg bg-white p-2.5 border border-slate-200 dark:bg-slate-900 dark:border-slate-700 flex justify-between items-center">
                                        <div>
                                            <div class="font-bold text-slate-900 dark:text-white">Awash International Bank</div>
                                            <div>A/C: <span class="font-bold">01425896321400</span> (ERE Real Estate Escrow)</div>
                                        </div>
                                        <span class="text-[10px] bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded text-slate-500">Kazanchis Branch</span>
                                    </div>
                                </div>
                                <p class="mt-3 text-[11px] text-slate-500">
                                    {{ __('Important: Upload your stamped bank deposit receipt after initiating the transfer. Funds are verified before contract status is confirmed.') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Right column: Authoritative Server Breakdown & Action --}}
                    <div class="lg:col-span-5">
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sticky top-6">
                            <h2 class="text-base font-bold text-slate-900 dark:text-white">
                                {{ __('Payment Summary') }}
                            </h2>
                            <p class="text-xs text-slate-500">
                                {{ __('Authoritative backend calculated breakdown in ETB.') }}
                            </p>

                            @if ($serverPricing)
                                <div class="mt-6 space-y-3 border-b border-slate-100 pb-4 dark:border-slate-800 text-sm">
                                    <div class="flex justify-between text-slate-600 dark:text-slate-400">
                                        <span>{{ __('Base Principal') }}</span>
                                        <span class="font-mono font-medium text-slate-900 dark:text-white">
                                            ETB {{ number_format($serverPricing['principal_amount_etb'], 2) }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between text-slate-600 dark:text-slate-400">
                                        <span>{{ __('Service / Processing Fees') }}</span>
                                        <span class="font-mono font-medium text-slate-900 dark:text-white">
                                            ETB {{ number_format($serverPricing['fee_amount_etb'], 2) }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between text-slate-600 dark:text-slate-400">
                                        <span>{{ __('Applicable Tax / VAT (15%)') }}</span>
                                        <span class="font-mono font-medium text-slate-900 dark:text-white">
                                            ETB {{ number_format($serverPricing['tax_amount_etb'], 2) }}
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-4 flex items-baseline justify-between">
                                    <span class="text-base font-bold text-slate-900 dark:text-white">{{ __('Total Due') }}</span>
                                    <span class="text-2xl font-black text-amber-600 dark:text-amber-400 font-mono">
                                        ETB {{ number_format($serverPricing['total_amount_etb'], 2) }}
                                    </span>
                                </div>

                                {{-- Initiation Form --}}
                                <form action="{{ route('payments.initiate') }}" method="POST" class="mt-6" @submit="isSubmitting = true">
                                    @csrf
                                    <input type="hidden" name="payable_type" value="{{ get_class($payable) }}">
                                    <input type="hidden" name="payable_id" value="{{ $payable->id }}">
                                    <input type="hidden" name="payment_method" :value="paymentMethod">
                                    <input type="hidden" name="idempotency_key" :value="idempotencyKey">
                                    <input type="hidden" name="custom_amount_etb" :value="customAmount">

                                    <button type="submit"
                                            :disabled="isSubmitting"
                                            class="w-full rounded-xl bg-amber-600 px-4 py-3.5 text-sm font-bold text-white shadow-lg shadow-amber-600/25 transition-all hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 disabled:opacity-50">
                                        <span x-show="!isSubmitting">{{ __('Proceed with Payment') }}</span>
                                        <span x-show="isSubmitting" x-cloak>{{ __('Securing Payment Intent...') }}</span>
                                    </button>
                                </form>
                            @else
                                <div class="mt-6 rounded-xl border border-dashed border-slate-300 p-6 text-center text-xs text-slate-500 dark:border-slate-700">
                                    {{ __('No active payable transaction selected. Select a booking, reservation, lease schedule, or property purchase agreement to checkout.') }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            @else
                {{-- HOST PAYOUT CONSOLE --}}
                <div class="space-y-8">
                    {{-- Waterfall KPI Metrics Cards --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div class="text-xs font-semibold text-slate-500">{{ __('Gross Booking Revenue') }}</div>
                            <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white font-mono">
                                ETB {{ number_format($summary['gross_revenue_etb'] ?? 0.0, 2) }}
                            </div>
                            <div class="mt-1 text-[11px] text-slate-400">{{ __('100% of guest accommodation charges') }}</div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div class="text-xs font-semibold text-slate-500">{{ __('Platform Deductions') }}</div>
                            <div class="mt-2 text-2xl font-black text-rose-600 dark:text-rose-400 font-mono">
                                -ETB {{ number_format($summary['platform_deductions_etb'] ?? 0.0, 2) }}
                            </div>
                            <div class="mt-1 text-[11px] text-slate-400">{{ __('Host commission & service deductions') }}</div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div class="text-xs font-semibold text-slate-500">{{ __('Authorized Adjustments') }}</div>
                            <div class="mt-2 text-2xl font-black {{ ($summary['adjustments_amount_etb'] ?? 0) >= 0 ? 'text-slate-900 dark:text-white' : 'text-amber-600' }} font-mono">
                                {{ ($summary['adjustments_amount_etb'] ?? 0) >= 0 ? '+' : '' }}ETB {{ number_format($summary['adjustments_amount_etb'] ?? 0.0, 2) }}
                            </div>
                            <div class="mt-1 text-[11px] text-slate-400">{{ __('Dispute deductions / damage credits') }}</div>
                        </div>

                        <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm dark:border-amber-900/40 dark:bg-amber-950/20">
                            <div class="text-xs font-semibold text-amber-800 dark:text-amber-300">{{ __('Net Host Payable') }}</div>
                            <div class="mt-2 text-2xl font-black text-amber-700 dark:text-amber-400 font-mono">
                                ETB {{ number_format($summary['net_payable_etb'] ?? 0.0, 2) }}
                            </div>
                            <div class="mt-1 text-[11px] text-amber-600/80">{{ __('Decimal-safe reconciled payout liability') }}</div>
                        </div>
                    </div>

                    @if ($host && $host->payoutDestinations->isNotEmpty())
                        @php $primaryDest = $host->payoutDestinations->first(); @endphp
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900/40 dark:bg-emerald-950/20 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="h-9 w-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center text-emerald-700 dark:text-emerald-300 font-bold text-sm">
                                    🏦
                                </div>
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">{{ __('Verified Payout Destination') }}</div>
                                    <div class="text-sm font-bold text-slate-900 dark:text-white">
                                        {{ $primaryDest->institution_name }} &bull; <span class="font-mono">{{ $primaryDest->account_number_masked }}</span>
                                    </div>
                                </div>
                            </div>
                            <span class="inline-flex items-center rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">
                                ✓ {{ __('Active Settlement Account') }}
                            </span>
                        </div>
                    @endif

                    {{-- Host Payout Schedules Table --}}
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="border-b border-slate-200 p-5 dark:border-slate-800 flex justify-between items-center">
                            <div>
                                <h3 class="text-base font-bold text-slate-900 dark:text-white">
                                    {{ __('Scheduled & Historical Payouts') }}
                                </h3>
                                <p class="text-xs text-slate-500">
                                    {{ __('Traceable to bookings, double-entry ledger entries, and provider transactions.') }}
                                </p>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                                <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                                    <tr>
                                        <th class="px-5 py-3.5">{{ __('Reference') }}</th>
                                        <th class="px-5 py-3.5">{{ __('Source Booking') }}</th>
                                        <th class="px-5 py-3.5">{{ __('Schedule') }}</th>
                                        <th class="px-5 py-3.5">{{ __('Release Date') }}</th>
                                        <th class="px-5 py-3.5 text-right">{{ __('Net Amount') }}</th>
                                        <th class="px-5 py-3.5 text-center">{{ __('Status') }}</th>
                                        <th class="px-5 py-3.5 text-right">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                    @forelse ($payouts as $payout)
                                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                            <td class="px-5 py-4 font-mono text-xs font-bold text-slate-900 dark:text-white">
                                                {{ $payout->payout_reference }}
                                            </td>
                                            <td class="px-5 py-4 text-xs font-mono">
                                                {{ $payout->booking?->booking_code ?? 'Manual Payout' }}
                                            </td>
                                            <td class="px-5 py-4 text-xs capitalize">
                                                {{ str_replace('_', ' ', $payout->schedule_type->value) }}
                                            </td>
                                            <td class="px-5 py-4 text-xs">
                                                {{ $payout->scheduled_for?->format('M d, Y H:i') ?? 'Immediate' }}
                                            </td>
                                            <td class="px-5 py-4 text-right font-mono font-bold text-slate-900 dark:text-white">
                                                ETB {{ number_format($payout->net_payable_etb, 2) }}
                                            </td>
                                            <td class="px-5 py-4 text-center">
                                                @php
                                                    $statusClasses = match ($payout->status->value) {
                                                        'paid' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300',
                                                        'on_hold' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300',
                                                        'cancelled', 'failed' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300',
                                                        default => 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-300',
                                                    };
                                                @endphp
                                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $statusClasses }}">
                                                    {{ ucfirst(str_replace('_', ' ', $payout->status->value)) }}
                                                </span>
                                            </td>
                                            <td class="px-5 py-4 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    @if ($payout->status->value !== 'paid' && $payout->status->value !== 'cancelled')
                                                        @if ($payout->status->value === 'on_hold')
                                                            <form action="{{ route('payments.payouts.release', $payout->uuid) }}" method="POST">
                                                                @csrf
                                                                <button type="submit" class="rounded px-2 py-1 text-xs font-semibold text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40">
                                                                    {{ __('Release') }}
                                                                </button>
                                                            </form>
                                                        @else
                                                            <form action="{{ route('payments.payouts.hold', $payout->uuid) }}" method="POST" onsubmit="return confirm('Hold payout pending investigation?');">
                                                                @csrf
                                                                <input type="hidden" name="reason" value="Administrative or dispute review">
                                                                <button type="submit" class="rounded px-2 py-1 text-xs font-semibold text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40">
                                                                    {{ __('Hold') }}
                                                                </button>
                                                            </form>
                                                            <form action="{{ route('payments.payouts.process', $payout->uuid) }}" method="POST" onsubmit="return confirm('Confirm settlement disbursement of ETB {{ number_format($payout->net_payable_etb, 2) }}?');">
                                                                @csrf
                                                                <button type="submit" class="rounded bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900">
                                                                    {{ __('Settle') }}
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @else
                                                        <span class="text-xs text-slate-400">{{ __('Settled') }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-5 py-8 text-center text-xs text-slate-500">
                                                {{ __('No host payouts scheduled yet.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
