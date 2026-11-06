<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <!-- Top Bar -->
        <nav class="text-xs text-slate-500 flex items-center justify-between">
            <div>
                <a href="{{ route('admin.moderation.index') }}" class="text-teal-700 hover:underline">← Back to Moderation Workbench</a> /
                <span class="font-mono text-slate-800 font-semibold">{{ $listing->listing_code }}</span>
            </div>
            <div>
                <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase
                    {{ ($listing->status->value ?? (string) $listing->status) === 'published' ? 'bg-emerald-100 text-emerald-800' : '' }}
                    {{ ($listing->status->value ?? (string) $listing->status) === 'pending_review' ? 'bg-amber-100 text-amber-800' : '' }}
                    {{ ($listing->status->value ?? (string) $listing->status) === 'suspended' ? 'bg-red-100 text-red-800' : '' }}
                    {{ ($listing->status->value ?? (string) $listing->status) === 'rejected' ? 'bg-slate-200 text-slate-700' : '' }}
                ">
                    Status: {{ $listing->status instanceof \BackedEnum ? $listing->status->value : $listing->status }}
                </span>
            </div>
        </nav>

        @if(session('error'))
            <div class="p-4 rounded-xl border bg-rose-50 border-rose-200 text-rose-800 text-sm font-medium flex items-center justify-between">
                <span class="flex items-center gap-2">
                    <span class="text-rose-600 font-bold">⚠️</span>
                    <span>{{ session('error') }}</span>
                </span>
            </div>
        @endif
        @if(session('success'))
            <div class="p-4 rounded-xl border bg-emerald-50 border-emerald-200 text-emerald-800 text-sm font-medium flex items-center justify-between">
                <span class="flex items-center gap-2">
                    <span class="text-emerald-600 font-bold">✓</span>
                    <span>{{ session('success') }}</span>
                </span>
            </div>
        @endif

        <!-- Listing Header -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-4">
            <div>
                <span class="text-xs font-mono text-slate-400">{{ $property->property_code }}</span>
                <h1 class="text-xl font-bold text-slate-900">{{ $property->title_en }}</h1>
                <p class="text-xs text-slate-500 mt-1">
                    Seller: <strong>{{ $property->owner->name }}</strong> ({{ $property->owner->phone }}) •
                    Location: {{ $property->subcity?->name_en ?? 'Subcity' }}, {{ $property->city?->name_en ?? 'City' }}
                </p>
            </div>
            <div class="text-right">
                <div class="text-xs text-slate-400">Asking Price</div>
                <div class="text-2xl font-black text-teal-900">
                    {{ \App\Support\Money\EtbFormatter::format($listing->price_etb) }}
                </div>
            </div>
        </div>

        <!-- Automated Fraud Signals Section (SRS Section 134) -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <span>🚨</span>
                    <span>Automated Fraud & Duplicate Signals (Moderation-Assistance Only)</span>
                </h2>
                <span class="text-xs text-slate-400">Human review required before action</span>
            </div>

            <div class="space-y-3">
                @forelse($listing->fraudSignals as $signal)
                    <div class="p-4 rounded-xl border {{ $signal->status === 'open' ? 'border-amber-300 bg-amber-50/50' : 'border-slate-200 bg-slate-50' }} space-y-3 text-xs">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-800 uppercase tracking-tight">
                                    {{ str_replace('_', ' ', $signal->signal_type) }}
                                </span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                    {{ $signal->severity === 'critical' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }}
                                ">
                                    Severity: {{ $signal->severity }}
                                </span>
                                <span class="text-slate-500">Confidence: <strong>{{ $signal->confidence_score }}%</strong></span>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $signal->status === 'open' ? 'bg-amber-200 text-amber-900' : 'bg-slate-200 text-slate-700' }}">
                                {{ $signal->status }}
                            </span>
                        </div>

                        <!-- Structured Evidence View -->
                        @if(!empty($signal->evidence_json))
                            <div class="p-3 bg-white rounded-lg border border-slate-200 font-mono text-[11px] text-slate-700 overflow-x-auto">
                                <pre>{{ json_encode($signal->evidence_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        @endif

                        @if($signal->status === 'open')
                            <div class="pt-2 flex items-center gap-3">
                                <form method="POST" action="{{ route('admin.fraud_signals.resolve', $signal) }}" class="inline-flex gap-2 items-center">
                                    @csrf
                                    <input type="text" name="notes" placeholder="Reason for dismissing..." class="rounded-lg border-slate-300 text-xs py-1 px-2.5">
                                    <input type="hidden" name="resolution" value="dismissed">
                                    <button type="submit" class="px-3 py-1 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-800 font-semibold transition">
                                        Dismiss as False Positive
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.fraud_signals.resolve', $signal) }}" class="inline-flex gap-2 items-center">
                                    @csrf
                                    <input type="hidden" name="resolution" value="confirmed_fraud">
                                    <input type="hidden" name="notes" value="Confirmed fraud violation by moderator">
                                    <button type="submit" class="px-3 py-1 rounded-lg bg-red-600 hover:bg-red-700 text-white font-semibold transition">
                                        Confirm Fraud Violation
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-xs text-slate-400 py-2">No automated fraud signals detected for this listing.</p>
                @endforelse
            </div>
        </div>

        <!-- Public User Reports Section (SRS Section 117) -->
        @if($reports->count() > 0)
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <span>🚩</span>
                    <span>Public Reports Submitted ({{ $reports->count() }})</span>
                </h2>

                <div class="space-y-3 text-xs">
                    @foreach($reports as $rep)
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-red-700 uppercase">Reason: {{ str_replace('_', ' ', $rep->reason_code) }}</span>
                                <span class="text-slate-400">{{ $rep->created_at->diffForHumans() }} • Status: <strong>{{ $rep->status }}</strong></span>
                            </div>
                            <p class="text-slate-700">{{ $rep->description ?? 'No detailed description.' }}</p>

                            @if($rep->status === 'open')
                                <form method="POST" action="{{ route('admin.reports.review', $rep) }}" class="pt-2 flex items-center gap-2">
                                    @csrf
                                    <select name="decision" class="rounded-lg border-slate-300 text-xs py-1 px-2">
                                        <option value="investigating">Mark as Investigating</option>
                                        <option value="resolved">Mark as Resolved</option>
                                        <option value="dismissed">Dismiss Report</option>
                                    </select>
                                    <button type="submit" class="px-3 py-1 rounded-lg bg-teal-700 hover:bg-teal-800 text-white font-semibold">
                                        Update Report Status
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @php
            $canOverride = auth()->check() && app(\App\Domains\Moderation\Actions\ModerateListingAction::class)->canOverrideSubscriptionQuota(auth()->user());
        @endphp

        <!-- Moderator Decision Workbench Panel -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider text-teal-800">
                Moderator Review Decision
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- 1. Approve & Publish -->
                <form method="POST" action="{{ route('admin.listings.approve', $listing) }}"
                      x-data="{ bypassQuota: false }"
                      class="p-4 rounded-xl border border-emerald-200 bg-emerald-50/50 space-y-3 flex flex-col justify-between">
                    @csrf
                    <div>
                        <div class="font-bold text-emerald-900 text-sm">Approve &amp; Publish</div>
                        <p class="text-xs text-emerald-700 mt-1">Listing is clear and ready for the public marketplace.</p>

                        @if($canOverride)
                            <div class="mt-3 pt-2 border-t border-emerald-200/60">
                                <label class="flex items-start gap-1.5 text-[11px] text-emerald-900 font-semibold cursor-pointer">
                                    <input type="checkbox" name="bypass_quota" value="1" x-model="bypassQuota" class="mt-0.5 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500">
                                    <span>Administrative Override: Bypass seller quota</span>
                                </label>
                                <p class="text-[10px] text-emerald-700/80 mt-0.5 pl-5 leading-tight">
                                    Authorized exception for this listing only. Will not upgrade seller plan.
                                </p>

                                <div x-show="bypassQuota" x-cloak class="mt-2 space-y-1">
                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-emerald-950">
                                        Override Reason <span class="text-rose-600">*</span>
                                    </label>
                                    <textarea name="override_reason"
                                              :required="bypassQuota"
                                              rows="2"
                                              placeholder="e.g. Platform-approved demonstration listing..."
                                              class="w-full text-xs rounded-lg border-emerald-300 p-1.5 focus:ring-emerald-500"></textarea>
                                </div>
                            </div>
                        @endif
                    </div>
                    <button type="submit" class="w-full py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition">
                        ✓ Approve Listing
                    </button>
                </form>

                <!-- 2. Request Corrections -->
                <form method="POST" action="{{ route('admin.listings.request_correction', $listing) }}" class="p-4 rounded-xl border border-blue-200 bg-blue-50/50 space-y-3 flex flex-col justify-between">
                    @csrf
                    <div>
                        <div class="font-bold text-blue-900 text-sm">Request Corrections</div>
                        <p class="text-xs text-blue-700 mt-1">Send back to seller draft with actionable feedback.</p>
                        <textarea name="feedback_to_seller" required placeholder="Specify needed changes..." class="mt-2 w-full rounded-lg border-blue-300 text-xs p-2 h-16"></textarea>
                    </div>
                    <button type="submit" class="w-full py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs transition">
                        ✎ Request Changes
                    </button>
                </form>

                <!-- 3. Suspend -->
                <form method="POST" action="{{ route('admin.listings.suspend', $listing) }}" class="p-4 rounded-xl border border-amber-200 bg-amber-50/50 space-y-3 flex flex-col justify-between">
                    @csrf
                    <div>
                        <div class="font-bold text-amber-900 text-sm">Suspend Listing</div>
                        <p class="text-xs text-amber-700 mt-1">Remove from search while investigating fraud or disputes.</p>
                        <input type="text" name="feedback_to_seller" required placeholder="Suspension reason..." class="mt-2 w-full rounded-lg border-amber-300 text-xs p-2">
                    </div>
                    <button type="submit" class="w-full py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs transition">
                        ⏸ Suspend Listing
                    </button>
                </form>

                <!-- 4. Reject -->
                <form method="POST" action="{{ route('admin.listings.reject', $listing) }}" class="p-4 rounded-xl border border-red-200 bg-red-50/50 space-y-3 flex flex-col justify-between">
                    @csrf
                    <div>
                        <div class="font-bold text-red-900 text-sm">Reject Listing</div>
                        <p class="text-xs text-red-700 mt-1">Permanently reject non-compliant listing.</p>
                        <input type="text" name="feedback_to_seller" required placeholder="Rejection reason..." class="mt-2 w-full rounded-lg border-red-300 text-xs p-2">
                    </div>
                    <button type="submit" class="w-full py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white font-bold text-xs transition">
                        ✕ Reject Listing
                    </button>
                </form>
            </div>
        </div>

        <!-- Moderation Audit Trail -->
        @if($listing->moderationActions->count() > 0)
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-3">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider text-slate-600">
                    Moderation History Audit Trail
                </h2>
                <div class="space-y-2 text-xs">
                    @foreach($listing->moderationActions as $act)
                        <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-between">
                            <div>
                                <span class="font-bold uppercase text-slate-800">{{ $act->action_type }}</span>
                                <span class="text-slate-500">({{ $act->from_status }} → {{ $act->to_status }}) by <strong>{{ $act->moderator->name }}</strong></span>
                                @if($act->feedback_to_seller)
                                    <div class="text-slate-600 italic mt-0.5">Note: "{{ $act->feedback_to_seller }}"</div>
                                @endif
                            </div>
                            <span class="text-slate-400">{{ $act->created_at->diffForHumans() }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
