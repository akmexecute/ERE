<x-app-layout>
    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-5">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        {{ __('Payment Details') }}
                    </h1>
                    <p class="text-sm text-slate-500 font-mono">
                        {{ $payment->reference_code }} &bull; {{ $payment->transaction_id }}
                    </p>
                </div>
                <a href="{{ route('payments.checkout') }}" class="rounded-lg bg-slate-100 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300">
                    &larr; {{ __('Back to Checkout') }}
                </a>
            </div>
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif

            @if ($payment->allocation_status && $payment->allocation_status->value === 'reconciliation_required')
                <div class="rounded-2xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200 space-y-2">
                    <div class="font-bold flex items-center gap-2">
                        <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        {{ __('Payment Held as Unapplied Customer Funds (Reconciliation Required)') }}
                    </div>
                    <p class="text-xs leading-relaxed">
                        {{ $payment->reconciliation_notes ?? __('This payment was confirmed as successfully received from the external provider after the 30-minute purchase reservation expired, and the remaining contractual purchase balance was already consumed. To prevent over-allocation, funds are safely preserved as an unapplied customer liability (Ledger Account 2040) awaiting administrative settlement or refund.') }}
                    </p>
                    <div class="pt-1 flex items-center gap-3">
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">
                            {{ __('Unapplied Balance:') }} <strong>ETB {{ number_format($payment->unapplied_amount_etb, 2) }}</strong>
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">
                            {{ __('Liability:') }} <strong>Ledger Account 2040</strong>
                        </span>
                    </div>
                </div>
            @endif

            {{-- Summary Card --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-6 dark:border-slate-800 flex justify-between items-center">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Payment Status') }}</span>
                        @php
                            $statusClasses = match ($payment->status->value) {
                                'successful' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300',
                                'processing', 'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300',
                                default => 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300',
                            };
                        @endphp
                        <div class="mt-1 flex items-center gap-2">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider {{ $statusClasses }}">
                                {{ $payment->status->value }}
                            </span>
                            @if ($payment->allocation_status)
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold tracking-wide {{ $payment->allocation_status->value === 'allocated' ? 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300' : ($payment->allocation_status->value === 'reconciliation_required' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300') }}">
                                    {{ $payment->allocation_status->label() }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-slate-500">{{ __('Total Charged') }}</div>
                        <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">
                            ETB {{ number_format($payment->total_amount_etb, 2) }}
                        </div>
                    </div>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                    <div>
                        <h4 class="text-xs font-bold uppercase text-slate-400">{{ __('Transaction Information') }}</h4>
                        <dl class="mt-3 space-y-2">
                            <div class="flex justify-between">
                                <dt class="text-slate-500">{{ __('Payment Purpose') }}:</dt>
                                <dd class="font-medium text-slate-900 dark:text-white capitalize">{{ str_replace('_', ' ', $payment->payment_type->value) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">{{ __('Payment Method') }}:</dt>
                                <dd class="font-medium text-slate-900 dark:text-white capitalize">{{ $payment->payment_method->label() }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">{{ __('Initiated At') }}:</dt>
                                <dd class="font-mono text-slate-900 dark:text-white">{{ $payment->created_at->format('M d, Y H:i:s') }}</dd>
                            </div>
                            @if ($payment->confirmed_at)
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">{{ __('Confirmed At') }}:</dt>
                                    <dd class="font-mono text-emerald-600 dark:text-emerald-400 font-bold">{{ $payment->confirmed_at->format('M d, Y H:i:s') }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold uppercase text-slate-400">{{ __('Financial Breakdown') }}</h4>
                        <dl class="mt-3 space-y-2">
                            <div class="flex justify-between">
                                <dt class="text-slate-500">{{ __('Principal Subtotal') }}:</dt>
                                <dd class="font-mono text-slate-900 dark:text-white">ETB {{ number_format($payment->principal_amount_etb, 2) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">{{ __('Customer Fees') }}:</dt>
                                <dd class="font-mono text-slate-900 dark:text-white">ETB {{ number_format($payment->fee_amount_etb, 2) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">{{ __('VAT / Taxes') }}:</dt>
                                <dd class="font-mono text-slate-900 dark:text-white">ETB {{ number_format($payment->tax_amount_etb, 2) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- Bank Slip Upload Form if Bank Transfer & Pending --}}
                @if ($payment->payment_method->value === 'bank_transfer')
                    <div class="border-t border-slate-200 bg-slate-50 p-6 dark:border-slate-800 dark:bg-slate-900/60">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">
                            {{ __('Bank Deposit Slip Submission') }}
                        </h3>

                        {{-- Settlement Account Guidelines --}}
                        <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-800/80">
                            <div class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider mb-2">
                                {{ __('Designated Settlement Bank Accounts') }}
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800">
                                    <div class="font-bold text-slate-900 dark:text-white">Commercial Bank of Ethiopia (CBE)</div>
                                    <div class="font-mono text-slate-600 dark:text-slate-400">Account: 1000234567891</div>
                                    <div class="text-[11px] text-slate-400">Branch: Finfine Main Branch</div>
                                </div>
                                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800">
                                    <div class="font-bold text-slate-900 dark:text-white">Awash International Bank</div>
                                    <div class="font-mono text-slate-600 dark:text-slate-400">Account: 01304859382000</div>
                                    <div class="text-[11px] text-slate-400">Branch: Bole Medhanialem</div>
                                </div>
                            </div>
                        </div>

                        @if ($payment->bankTransferProof)
                            <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <div class="text-xs text-slate-500">{{ __('Slip Status') }}:</div>
                                        <div class="font-bold text-slate-900 dark:text-white capitalize">
                                            {{ str_replace('_', ' ', $payment->bankTransferProof->verification_status->value) }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs text-slate-500">{{ __('Bank Reference') }}:</div>
                                        <div class="font-mono font-bold">{{ $payment->bankTransferProof->transfer_reference }}</div>
                                    </div>
                                </div>
                                @if ($payment->bankTransferProof->rejection_reason)
                                    <div class="mt-3 rounded-lg bg-rose-50 p-3 text-xs text-rose-700 dark:bg-rose-950/40 dark:text-rose-300">
                                        {{ __('Rejection Reason:') }} {{ $payment->bankTransferProof->rejection_reason }}
                                    </div>
                                @endif
                            </div>
                        @else
                            <form action="{{ route('payments.slip.submit', $payment->uuid) }}" method="POST" enctype="multipart/form-data" class="mt-4 space-y-4">
                                @csrf
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('Bank Name') }}</label>
                                        <input type="text" name="bank_name" value="Commercial Bank of Ethiopia" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm text-sm dark:border-slate-700 dark:bg-slate-800">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('Transaction / Slip Reference') }}</label>
                                        <input type="text" name="transfer_reference" placeholder="e.g. FT26019928374" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm text-sm dark:border-slate-700 dark:bg-slate-800">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('Attach Deposit Slip (Image or PDF, max 10MB)') }}</label>
                                    <input type="file" name="slip_file" accept="image/jpeg,image/png,image/webp,application/pdf" required class="mt-1 block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
                                </div>

                                <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-xs font-bold text-white shadow hover:bg-amber-700">
                                    {{ __('Submit Slip for Officer Verification') }}
                                </button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
