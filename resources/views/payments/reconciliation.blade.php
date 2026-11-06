<x-app-layout>
    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 dark:border-slate-800 pb-5 gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        {{ __('Payment Reconciliation & Unapplied Funds') }}
                    </h1>
                    <p class="text-sm text-slate-500 mt-1">
                        {{ __('Authorized financial review for late provider callbacks exceeding purchase balance or requiring settlement.') }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('payments.checkout') }}" class="rounded-lg bg-slate-100 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300">
                        {{ __('Checkout Console') }}
                    </a>
                    <a href="{{ route('payments.payouts') }}" class="rounded-lg bg-slate-100 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300">
                        {{ __('Host Payouts') }}
                    </a>
                </div>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Informational Banner -->
            <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-5 dark:border-amber-900/50 dark:bg-amber-950/20 text-sm text-amber-900 dark:text-amber-200 space-y-1">
                <div class="font-bold flex items-center gap-2">
                    <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ __('Unapplied Customer Funds Protocol') }}
                </div>
                <p class="text-xs leading-relaxed text-amber-800 dark:text-amber-300">
                    {{ __('When external payment providers confirm funds received after a 30-minute purchase reservation expires and the contractual balance was already settled, ERE records the money received as an unapplied customer liability (Ledger Account 2040). These funds are never discarded or over-allocated. Financial officers can inspect the audit trail below and execute an authorized refund or manual allocation.') }}
                </p>
            </div>

            <!-- Reconciliation Table -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="p-6 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">
                        {{ __('Payments Requiring Reconciliation') }} ({{ $exceptions->total() }})
                    </h2>
                </div>

                @if ($exceptions->isEmpty())
                    <div class="p-12 text-center">
                        <div class="mx-auto h-12 w-12 text-slate-400">
                            <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">{{ __('All accounts fully reconciled') }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ __('No unapplied customer funds or over-allocation exceptions currently pending.') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-left text-xs">
                            <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 uppercase font-semibold">
                                <tr>
                                    <th class="px-6 py-3.5">{{ __('Reference / ID') }}</th>
                                    <th class="px-6 py-3.5">{{ __('Customer') }}</th>
                                    <th class="px-6 py-3.5">{{ __('Target Agreement') }}</th>
                                    <th class="px-6 py-3.5 text-right">{{ __('Captured (ETB)') }}</th>
                                    <th class="px-6 py-3.5 text-right">{{ __('Unapplied (ETB)') }}</th>
                                    <th class="px-6 py-3.5">{{ __('Reconciliation Notes') }}</th>
                                    <th class="px-6 py-3.5 text-right">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800 font-mono">
                                @foreach ($exceptions as $payment)
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-slate-900 dark:text-white">{{ $payment->reference_code }}</div>
                                            <div class="text-[11px] text-slate-400">{{ $payment->provider_transaction_reference ?? $payment->transaction_id }}</div>
                                            <div class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                                {{ $payment->allocation_status->label() }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 font-sans">
                                            <div class="font-medium text-slate-900 dark:text-white">{{ $payment->customer?->name ?? 'User #' . $payment->customer_user_id }}</div>
                                            <div class="text-[11px] text-slate-500">{{ $payment->customer?->phone ?? $payment->customer?->email }}</div>
                                        </td>
                                        <td class="px-6 py-4 font-sans">
                                            <div class="text-slate-800 dark:text-slate-200 font-medium">
                                                {{ class_basename($payment->payable_type) }} #{{ $payment->payable_id }}
                                            </div>
                                            <div class="text-[11px] text-slate-400 capitalize">
                                                {{ str_replace('_', ' ', $payment->payment_type->value) }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-right font-bold text-slate-900 dark:text-white">
                                            {{ number_format($payment->total_amount_etb, 2) }}
                                        </td>
                                        <td class="px-6 py-4 text-right font-black text-amber-600 dark:text-amber-400">
                                            {{ number_format($payment->unapplied_amount_etb, 2) }}
                                        </td>
                                        <td class="px-6 py-4 font-sans max-w-xs">
                                            <p class="text-xs text-slate-600 dark:text-slate-300 line-clamp-2">
                                                {{ $payment->reconciliation_notes ?? __('Late callback received after balance limit reached.') }}
                                            </p>
                                        </td>
                                        <td class="px-6 py-4 text-right font-sans space-y-1">
                                            <a href="{{ route('payments.show', $payment->uuid) }}" class="inline-block rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300">
                                                {{ __('Inspect') }}
                                            </a>
                                            <form action="{{ route('payments.refund_unapplied', $payment->uuid) }}" method="POST" class="inline-block" onsubmit="return confirm('Authorize full refund of unapplied customer funds?');">
                                                @csrf
                                                <button type="submit" class="rounded-md bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-rose-500">
                                                    {{ __('Refund Funds') }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                        {{ $exceptions->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

