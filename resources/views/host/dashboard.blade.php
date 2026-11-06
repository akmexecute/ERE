<x-app-layout>
    <div
        x-data="{
            showKpiSettings: false,
            prefs: {{ json_encode($preferences ?? \App\Domains\Host\Services\HostDashboardKpiCalculator::defaultPreferences()) }},

            // Housekeeping Pipeline State (SRS Section 63)
            cleaningTasks: {{ json_encode($cleaningTasks ?? []) }},

            async advanceCleaning(task) {
                try {
                    const res = await fetch('/api/host/advance-cleaning', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            current_status: task.status,
                            task_code: task.id
                        })
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        task.status = data.new_status;
                    } else {
                        // Fallback transition
                        const order = ['pending', 'assigned', 'cleaning', 'inspection', 'ready'];
                        const idx = order.indexOf(task.status);
                        if (idx >= 0 && idx < order.length - 1) {
                            task.status = order[idx + 1];
                        }
                    }
                } catch (e) {
                    const order = ['pending', 'assigned', 'cleaning', 'inspection', 'ready'];
                    const idx = order.indexOf(task.status);
                    if (idx >= 0 && idx < order.length - 1) {
                        task.status = order[idx + 1];
                    }
                }
            },

            // Security Deposit & Damage Claim Settlement State (SRS Sections 66–68)
            depositAmount: 5000,
            claimAmount: 1500,
            guestDecision: 'accept',
            settlementLoading: false,
            settlementResult: null,

            async runDepositSettlement() {
                this.settlementLoading = true;
                try {
                    const res = await fetch('/api/host/settle-deposit', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            held_deposit_etb: this.depositAmount,
                            claimed_damage_etb: this.claimAmount,
                            decision: this.guestDecision
                        })
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        this.settlementResult = data.result;
                    }
                } catch (e) {
                    console.error('Deposit settlement error', e);
                } finally {
                    this.settlementLoading = false;
                }
            },

            // Two-Way Review Evaluator State (SRS Sections 69, 70, 119)
            reviewDirection: 'guest_to_host',
            reviewScores: { cleanliness: 5, accuracy: 5, communication: 5, location: 4, value: 5 },
            reviewComment: 'Wonderful stay in Bole with reliable backup generator and fast Wi-Fi!',
            counterpartySubmitted: true,
            reviewLoading: false,
            reviewResult: null,

            async runReviewEvaluation() {
                this.reviewLoading = true;
                try {
                    const res = await fetch('/api/host/evaluate-review', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            direction: this.reviewDirection,
                            scores: this.reviewScores,
                            comment: this.reviewComment,
                            counterparty_already_submitted: this.counterpartySubmitted
                        })
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        this.reviewResult = data.result;
                    }
                } catch (e) {
                    console.error('Review evaluation error', e);
                } finally {
                    this.reviewLoading = false;
                }
            },

            init() {
                this.runDepositSettlement();
                this.runReviewEvaluation();
            }
        }"
        x-init="init()"
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8"
    >
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-teal-700 bg-teal-50 px-2.5 py-1 rounded-full border border-teal-200">
                    Short-Stay Host Operations • Phase 2 Milestone 2
                </span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-2">Host Management & Revenue Dashboard</h1>
                <p class="text-xs text-slate-500 mt-0.5">Live housekeeping workflow, security deposit custody, and two-way review reputation</p>
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="showKpiSettings = true"
                    class="px-3.5 py-2 rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-xs flex items-center gap-1.5"
                >
                    <span>⚙️</span> Customize KPI Cards
                </button>
                <a href="{{ url('/stays/modern-garden-villa-bole') }}" class="px-3.5 py-2 rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-xs">
                    👁️ View Guest Stay Page
                </a>
                <a href="{{ url('/stays/modern-garden-villa-bole/calendar.ics') }}" class="px-3.5 py-2 rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-xs">
                    📅 iCal Feed
                </a>
                <a href="{{ url('/listings/create') }}" class="px-4 py-2 rounded-xl bg-teal-700 text-white text-xs font-bold hover:bg-teal-800 transition shadow-xs">
                    + Add Stay Unit
                </a>
            </div>
        </div>

        <!-- Critical Action-Required Alerts (ALWAYS VISIBLE regardless of optional card preferences) -->
        @if(($kpis['tasks_inspection'] ?? 0) > 0 || ($kpis['deposits_disputed_etb'] ?? 0) > 0 || (isset($damageClaims) && $damageClaims->where('status', 'submitted')->count() > 0))
            <div class="space-y-3">
                @if(($kpis['tasks_inspection'] ?? 0) > 0)
                    <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-xs text-amber-900 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-xl">⚠️</span>
                            <div>
                                <span class="font-bold">Cleaning Awaiting Inspection:</span>
                                <span>{{ $kpis['tasks_inspection'] }} unit(s) submitted by cleaners require host inspection and sign-off before marking ready for incoming guests.</span>
                            </div>
                        </div>
                        <span class="px-3 py-1 bg-amber-200 text-amber-950 font-bold rounded-lg text-xs">Action Required</span>
                    </div>
                @endif
                @if(isset($damageClaims) && $damageClaims->where('status', 'submitted')->count() > 0)
                    <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200 text-xs text-blue-900 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-xl">🛡️</span>
                            <div>
                                <span class="font-bold">Pending Damage Claim:</span>
                                <span>{{ $damageClaims->where('status', 'submitted')->count() }} claim(s) currently awaiting guest response within the 48-hour inspection window.</span>
                            </div>
                        </div>
                        <span class="px-3 py-1 bg-blue-200 text-blue-950 font-bold rounded-lg text-xs">Under Review</span>
                    </div>
                @endif
            </div>
        @endif

        <!-- SRS Section 55 Host Dashboard Configurable KPI Strip -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <!-- Properties & Units -->
            <div x-show="prefs.kpi_booking_stats !== false" class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Properties</span>
                <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ $kpis['properties_count'] ?? 12 }}</div>
                <span class="text-[11px] text-teal-700 font-semibold">{{ $kpis['bookable_units_count'] ?? 12 }} Bookable Units ({{ $kpis['ready_units_count'] ?? 0 }} Ready)</span>
            </div>

            <!-- Upcoming Bookings -->
            <div x-show="prefs.kpi_booking_stats !== false" class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Upcoming Bookings</span>
                <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ $kpis['upcoming_bookings_count'] ?? 8 }}</div>
                <span class="text-[11px] text-teal-700 font-semibold">Avg Stay: {{ $kpis['avg_length_of_stay'] ?? 3 }} Nights</span>
            </div>

            <!-- Current Guests & Arrivals -->
            <div x-show="prefs.kpi_arrivals_departures !== false" class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Current In-House</span>
                <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ $kpis['current_guests_count'] ?? 4 }}</div>
                <span class="text-[11px] text-emerald-700 font-semibold">Today: {{ $kpis['today_arrivals_count'] ?? 0 }} In • {{ $kpis['today_departures_count'] ?? 0 }} Out</span>
            </div>

            <!-- Occupancy Rate & ADR -->
            <div x-show="prefs.kpi_occupancy !== false" class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Occupancy Rate</span>
                <div class="text-2xl font-extrabold text-teal-800 mt-1">{{ $kpis['occupancy_pct'] ?? 72 }}%</div>
                <span class="text-[11px] text-slate-500">RevPAR: ETB {{ number_format($kpis['revpar_etb'] ?? 8500, 0) }} • ADR: ETB {{ number_format($kpis['adr_etb'] ?? 11805.56, 0) }}</span>
            </div>

            <!-- Monthly / Net Revenue -->
            <div x-show="prefs.kpi_revenue !== false" class="bg-slate-900 text-white rounded-2xl p-5 shadow-xs col-span-2 sm:col-span-1 border border-slate-800">
                <span class="text-xs font-medium text-slate-400">Net Host Earnings</span>
                <div class="text-xl font-extrabold text-emerald-400 mt-1">ETB {{ number_format($kpis['net_earnings_etb'] ?? $kpis['total_revenue_etb'] ?? 850000) }}</div>
                <span class="text-[11px] text-slate-300">Gross: ETB {{ number_format($kpis['gross_booking_value_etb'] ?? $kpis['total_revenue_etb'] ?? 850000) }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Left 7 Cols: SRS Section 63 Housekeeping Workflow Tracker -->
            <div x-show="prefs.kpi_housekeeping !== false" class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-teal-50 text-teal-800 text-[11px] font-bold border border-teal-200 mb-1">
                            <span>🧹</span>
                            <span>SRS Section 63 State Machine</span>
                        </div>
                        <h2 class="text-base font-bold text-slate-900">Housekeeping & Turnover Pipeline</h2>
                        <p class="text-xs text-slate-500">Checkout → Cleaning Task → Cleaner Assigned → Cleaning → Inspection → Ready</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <template x-for="task in cleaningTasks" :key="task.id">
                        <div class="p-4 rounded-xl border border-slate-200 flex flex-wrap items-center justify-between gap-3 bg-slate-50/70 hover:bg-slate-50 transition">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-[11px] text-slate-500 bg-white px-2 py-0.5 rounded border border-slate-200" x-text="task.id"></span>
                                    <span class="text-xs font-bold text-slate-900" x-text="task.unit"></span>
                                </div>
                                <p class="text-xs text-slate-600">
                                    Cleaner: <strong x-text="task.cleaner"></strong> • Checkout: <span x-text="task.checkout_time"></span>
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <span
                                    :class="{
                                        'bg-emerald-100 text-emerald-900 border-emerald-300': task.status === 'ready',
                                        'bg-amber-100 text-amber-900 border-amber-300': task.status === 'inspection',
                                        'bg-sky-100 text-sky-900 border-sky-300': task.status === 'cleaning' || task.status === 'assigned',
                                        'bg-slate-200 text-slate-700 border-slate-300': task.status === 'pending'
                                    }"
                                    class="px-2.5 py-1 rounded-full text-xs font-bold uppercase border shadow-2xs"
                                    x-text="task.status"
                                ></span>

                                <button
                                    type="button"
                                    x-show="task.status !== 'ready'"
                                    @click="advanceCleaning(task)"
                                    class="px-3 py-1.5 rounded-lg bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold transition cursor-pointer shadow-2xs"
                                >
                                    Advance Stage →
                                </button>
                                <span x-show="task.status === 'ready'" class="text-xs text-emerald-700 font-bold px-2 py-1">
                                    ✓ Ready for Guest
                                </span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Right 5 Cols: Reputation & Security Deposit Custody Summary -->
            <div :class="prefs.kpi_housekeeping === false ? 'lg:col-span-12' : 'lg:col-span-5'" class="space-y-6">
                <!-- SRS Sections 69 & 70: Two-Way Review Summary -->
                <div x-show="prefs.kpi_reputation !== false" class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-bold text-slate-900">Two-Way Reputation (SRS §§69–70)</h3>
                        <span class="text-xs font-bold px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200">
                            ★ {{ number_format($kpis['average_rating'] ?? 4.93, 2) }} Overall
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                            <span class="text-slate-500">Cleanliness</span>
                            <div class="font-bold text-slate-900 text-sm mt-0.5">★ {{ $kpis['rating_breakdown']['cleanliness'] ?? '4.95' }} / 5.0</div>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                            <span class="text-slate-500">Accuracy & Value</span>
                            <div class="font-bold text-slate-900 text-sm mt-0.5">★ {{ $kpis['rating_breakdown']['accuracy'] ?? '4.88' }} / 5.0</div>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                            <span class="text-slate-500">Communication</span>
                            <div class="font-bold text-slate-900 text-sm mt-0.5">★ {{ $kpis['rating_breakdown']['communication'] ?? '4.98' }} / 5.0</div>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                            <span class="text-slate-500">Location</span>
                            <div class="font-bold text-slate-900 text-sm mt-0.5">★ {{ $kpis['rating_breakdown']['location'] ?? '4.91' }} / 5.0</div>
                        </div>
                    </div>
                </div>

                <!-- SRS Sections 66–68: Security Deposits Custody Status -->
                <div x-show="prefs.kpi_deposits !== false" class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-bold text-slate-900">Security Deposit Custody (SRS §66)</h3>
                        <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                            Licensed Escrow
                        </span>
                    </div>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Funds remain held in third-party custody and automatically release 48 hours after checkout unless an evidence-based damage claim is submitted by the host. Missing checkout forms do not forfeit deposits.
                    </p>
                    <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                        <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-950">
                            <span class="text-[10px] uppercase text-emerald-800 font-semibold block">Held in Custody</span>
                            <strong class="text-sm">ETB {{ number_format($kpis['deposits_held_etb'] ?? 20000, 0) }}</strong>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-slate-900">
                            <span class="text-[10px] uppercase text-slate-500 font-semibold block">Released to Guests</span>
                            <strong class="text-sm">ETB {{ number_format($kpis['deposits_released_etb'] ?? 3500, 0) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LIVE INTERACTIVE ENGINES: Deposit Settlement & Review Moderation Simulators -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 pt-4">

            <!-- Simulator 1: SettleSecurityDepositClaimAction (SRS Sections 66, 67, 68) -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-4">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-blue-50 text-blue-800 text-[11px] font-bold border border-blue-200 mb-1">
                            <span>⚖️</span>
                            <span>Live Action: SettleSecurityDepositClaimAction</span>
                        </div>
                        <h3 class="text-base font-bold text-slate-900">Damage Claim & Deposit Settlement Simulator</h3>
                        <p class="text-xs text-slate-500">Test how funds are split upon Guest Acceptance vs Dispute escalation</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500">Held Deposit</label>
                        <input type="number" x-model="depositAmount" @input="runDepositSettlement()" class="w-full text-xs font-semibold rounded border-slate-300 p-1.5 mt-0.5">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500">Claimed Damage</label>
                        <input type="number" x-model="claimAmount" @input="runDepositSettlement()" class="w-full text-xs font-semibold rounded border-slate-300 p-1.5 mt-0.5">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500">Guest Decision</label>
                        <select x-model="guestDecision" @change="runDepositSettlement()" class="w-full text-xs font-semibold rounded border-slate-300 p-1.5 mt-0.5">
                            <option value="accept">Accept Claim</option>
                            <option value="dispute">Dispute Claim</option>
                        </select>
                    </div>
                </div>

                <template x-if="settlementResult">
                    <div class="p-4 rounded-xl border border-teal-200 bg-teal-50/50 space-y-2 text-xs">
                        <div class="flex items-center justify-between font-bold">
                            <span class="text-teal-900">Settlement Engine Output:</span>
                            <span class="font-mono text-xs px-2 py-0.5 rounded uppercase"
                                  :class="settlementResult.deposit_status === 'disputed' ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900'"
                                  x-text="'Deposit: ' + settlementResult.deposit_status"></span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center pt-1">
                            <div class="p-2 bg-white rounded-lg border border-teal-100">
                                <span class="text-[10px] text-slate-500 block uppercase">Host Deducted</span>
                                <strong class="text-xs text-slate-900" x-text="'ETB ' + settlementResult.deducted_amount_etb.toLocaleString()"></strong>
                            </div>
                            <div class="p-2 bg-white rounded-lg border border-teal-100">
                                <span class="text-[10px] text-slate-500 block uppercase">Released to Guest</span>
                                <strong class="text-xs text-emerald-700" x-text="'ETB ' + settlementResult.released_to_guest_etb.toLocaleString()"></strong>
                            </div>
                            <div class="p-2 bg-white rounded-lg border border-teal-100">
                                <span class="text-[10px] text-slate-500 block uppercase">Dispute Opened</span>
                                <strong class="text-xs" :class="settlementResult.dispute_opened ? 'text-amber-700' : 'text-slate-600'" x-text="settlementResult.dispute_opened ? 'Yes (' + settlementResult.dispute_stage + ')' : 'No'"></strong>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Simulator 2: EvaluateTwoWayReviewSubmissionAction (SRS Sections 69, 70, 119) -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-4">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-blue-50 text-blue-800 text-[11px] font-bold border border-blue-200 mb-1">
                            <span>⭐</span>
                            <span>Live Action: EvaluateTwoWayReviewSubmissionAction</span>
                        </div>
                        <h3 class="text-base font-bold text-slate-900">Two-Way Review & Anti-Spam Moderation</h3>
                        <p class="text-xs text-slate-500">Criteria score calculation, spam/contact detection, and double-blind publication</p>
                    </div>
                </div>

                <div class="space-y-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Review Comment (Test with t.me / whatsapp / http to trigger spam filter)</label>
                        <input
                            type="text"
                            x-model="reviewComment"
                            @input="runReviewEvaluation()"
                            class="w-full text-xs font-medium rounded border-slate-300 p-1.5 bg-white"
                        >
                    </div>
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" x-model="counterpartySubmitted" @change="runReviewEvaluation()" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                            <span class="text-slate-700 font-medium">Counterparty has also submitted review</span>
                        </label>
                        <button type="button" @click="reviewComment = 'Contact me on telegram t.me/fake_host for direct booking!'; runReviewEvaluation()" class="text-[11px] text-amber-700 hover:underline">
                            Insert Spam Sample
                        </button>
                    </div>
                </div>

                <template x-if="reviewResult">
                    <div class="p-4 rounded-xl border border-teal-200 bg-teal-50/50 space-y-2 text-xs">
                        <div class="flex items-center justify-between font-bold">
                            <span class="text-teal-900">Review Evaluation Output:</span>
                            <span class="font-mono text-xs px-2 py-0.5 rounded uppercase"
                                  :class="reviewResult.moderation_status === 'approved' ? 'bg-emerald-100 text-emerald-900' : 'bg-red-100 text-red-900'"
                                  x-text="'Moderation: ' + reviewResult.moderation_status"></span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-center pt-1">
                            <div class="p-2 bg-white rounded-lg border border-teal-100">
                                <span class="text-[10px] text-slate-500 block uppercase">Computed Rating</span>
                                <strong class="text-sm text-teal-800" x-text="'★ ' + reviewResult.computed_overall_rating + ' / 5.0'"></strong>
                            </div>
                            <div class="p-2 bg-white rounded-lg border border-teal-100">
                                <span class="text-[10px] text-slate-500 block uppercase">Double-Blind Publication</span>
                                <strong class="text-sm" :class="reviewResult.publish_both_now ? 'text-emerald-700' : 'text-slate-600'" x-text="reviewResult.publish_both_now ? '✓ Publish Both Now' : '🔒 Hold (Double-Blind)'"></strong>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- HOST-CONTROLLED KPI VISIBILITY SETTINGS MODAL (SRS Section 55 & Section 7.6) -->
        <div
            x-show="showKpiSettings"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div
                @click.away="showKpiSettings = false"
                class="bg-white rounded-2xl max-w-lg w-full p-6 sm:p-8 space-y-6 shadow-xl border border-slate-200"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Customize Host KPI Cards</h3>
                        <p class="text-xs text-slate-500">Toggle which optional performance metrics appear on your dashboard.</p>
                    </div>
                    <button type="button" @click="showKpiSettings = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">
                        &times;
                    </button>
                </div>

                <form action="{{ route('host.dashboard.preferences') }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="space-y-2.5 max-h-72 overflow-y-auto pr-1">
                        @foreach($availableCards as $cardKey => $cardLabel)
                            <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition">
                                <span class="text-xs font-semibold text-slate-800">{{ $cardLabel }}</span>
                                <input
                                    type="checkbox"
                                    name="cards[{{ $cardKey }}]"
                                    value="1"
                                    x-model="prefs.{{ $cardKey }}"
                                    class="rounded border-slate-300 text-teal-700 focus:ring-teal-600 h-4 w-4"
                                >
                            </label>
                        @endforeach
                    </div>

                    <div class="p-3 bg-slate-50 rounded-xl text-[11px] text-slate-500">
                        <strong>Note:</strong> Critical action alerts (pending damage claims and tasks awaiting inspection) will always remain visible.
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                        <button
                            type="submit"
                            name="reset"
                            value="1"
                            class="text-xs font-semibold text-slate-600 hover:text-red-700 underline"
                        >
                            Reset to Defaults
                        </button>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="showKpiSettings = false"
                                class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="px-5 py-2 rounded-xl bg-teal-800 text-white text-xs font-bold hover:bg-teal-900 shadow-sm"
                            >
                                Save Preferences
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
