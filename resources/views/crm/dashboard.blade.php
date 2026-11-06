<x-app-layout>
    <div
        x-data="{
            selectedBranch: @js($selectedBranch ?? 'ALL'),
            askingPriceEtb: @js($askingPriceEtb ?? 25000000),
            counterAmountEtb: 23500000,
            customCounterInput: 23600000,
            commissionSalePrice: 20000000,
            commissionRatePct: 2.0,
            agentSplitPct: 60.0,
            awaitingParty: @js($awaitingParty ?? 'buyer'),
            offerStatus: @js($offerStatus ?? 'countered'),
            negotiationMessage: '',

            // Branch dataset (SRS Section 100 Multi-Branch Hierarchy)
            branches: @js($branches ?? []),

            // SRS Section 77 Exact Negotiation History Chain: 25M -> 22M -> 24M -> 23M -> 23.5M
            negotiationHistory: @js(!empty($negotiationHistory) ? $negotiationHistory : [
                ['round' => 1, 'party' => 'buyer', 'label' => 'Buyer Initial Offer', 'amount' => 22000000, 'time' => '14:20', 'note' => 'Based on recent Bole comps'],
                ['round' => 2, 'party' => 'seller', 'label' => 'Seller Counteroffer', 'amount' => 24000000, 'time' => '17:05', 'note' => 'Includes premium Italian finishes & generator'],
                ['round' => 3, 'party' => 'buyer', 'label' => 'Buyer Counteroffer', 'amount' => 23000000, 'time' => '09:30', 'note' => 'Willing to expedite cash transfer via CBE'],
                ['round' => 4, 'party' => 'seller', 'label' => 'Seller Counteroffer', 'amount' => 23500000, 'time' => '11:45', 'note' => 'Final seller bottom line with transfer tax split']
            ]),

            // SRS Section 97 & 98 CRM Pipeline Leads
            leads: @js(!empty($leads) ? $leads : []),

            // SRS Section 71 & 72 Property Viewings
            viewings: @js(!empty($viewings) ? $viewings : []),

            get filteredLeads() {
                if (this.selectedBranch === 'ALL') return this.leads;
                return this.leads.filter(l => l.branch === this.selectedBranch);
            },

            get currentOfferAmount() {
                if (this.negotiationHistory.length === 0) return 0;
                return this.negotiationHistory[this.negotiationHistory.length - 1].amount;
            },

            get currentDiscountEtb() {
                return Math.max(0, this.askingPriceEtb - this.currentOfferAmount);
            },

            get currentDiscountPct() {
                if (this.askingPriceEtb <= 0) return 0;
                return ((this.currentDiscountEtb / this.askingPriceEtb) * 100).toFixed(1);
            },

            get computedCommissionTotal() {
                return (this.commissionSalePrice * (this.commissionRatePct / 100));
            },

            get computedAgentShare() {
                return (this.computedCommissionTotal * (this.agentSplitPct / 100));
            },

            get computedCompanyShare() {
                return (this.computedCommissionTotal - this.computedAgentShare);
            },

            submitCounterturn(actor, action) {
                if (this.offerStatus === 'accepted' || this.offerStatus === 'rejected') {
                    alert('This negotiation session is already closed.');
                    return;
                }

                if (actor === this.awaitingParty && action === 'accept') {
                    this.offerStatus = 'accepted';
                    this.negotiationHistory.push({
                        round: this.negotiationHistory.length + 1,
                        party: actor,
                        label: (actor === 'buyer' ? 'Buyer Accepted Deal' : 'Seller Accepted Deal'),
                        amount: this.currentOfferAmount,
                        time: 'Just now • ' + new Date().toLocaleTimeString(),
                        note: 'Contract execution initiated at ETB ' + this.currentOfferAmount.toLocaleString()
                    });
                    this.awaitingParty = 'closed';
                    return;
                }

                if (action === 'reject') {
                    this.offerStatus = 'rejected';
                    this.negotiationHistory.push({
                        round: this.negotiationHistory.length + 1,
                        party: actor,
                        label: (actor === 'buyer' ? 'Buyer Rejected' : 'Seller Rejected'),
                        amount: this.currentOfferAmount,
                        time: 'Just now • ' + new Date().toLocaleTimeString(),
                        note: 'Negotiation terminated without agreement'
                    });
                    this.awaitingParty = 'closed';
                    return;
                }

                // Counteroffer turn
                const amt = parseFloat(this.customCounterInput);
                if (!amt || amt <= 0) {
                    alert('Please enter a valid positive ETB amount.');
                    return;
                }

                const nextRound = this.negotiationHistory.length + 1;
                this.negotiationHistory.push({
                    round: nextRound,
                    party: actor,
                    label: (actor === 'buyer' ? 'Buyer Counteroffer' : 'Seller Counteroffer'),
                    amount: amt,
                    time: 'Just now • ' + new Date().toLocaleTimeString(),
                    note: this.negotiationMessage || ('Round ' + nextRound + ' counterturn')
                });

                this.negotiationMessage = '';
                this.offerStatus = 'countered';
                this.awaitingParty = (actor === 'buyer' ? 'seller' : 'buyer');
                this.customCounterInput = actor === 'buyer' ? (amt + 250000) : (amt - 250000);
            },

            advanceLead(leadCode) {
                const lead = this.leads.find(l => l.code === leadCode);
                if (!lead) return;

                const stages = ['new_lead', 'contacted', 'qualified', 'viewing', 'negotiation', 'offer', 'closed'];
                const currentIndex = stages.indexOf(lead.stage);
                if (currentIndex >= 0 && currentIndex < stages.length - 1) {
                    lead.stage = stages[currentIndex + 1];
                }
            }
        }"
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8"
    >
        <!-- Header & SRS Section 100 Multi-Branch Hierarchy Selector -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-6">
            <div>
                <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md bg-emerald-100 text-emerald-900 text-xs font-semibold mb-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                    Phase 3 Milestone 1 • Real Estate Agency & Broker Network
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">
                    Agent CRM, Multi-Branch & Offer Negotiation Room
                </h1>
                <p class="text-sm text-slate-600 mt-1 max-w-3xl">
                    Multi-branch oversight for Addis Ababa, Harar, Jijiga, and Dire Dawa. Tracks 10 lead acquisition sources, the 7-stage CRM pipeline, property viewing appointments, and the immutable multi-round offer ladder per SRS Sections 71–77 & 96–101.
                </p>
            </div>

            <!-- SRS Section 100: Branch Switcher -->
            <div class="bg-white p-3 rounded-2xl border border-slate-200 shadow-sm flex flex-col gap-1.5 min-w-[280px]">
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Operational Branch</label>
                <select
                    x-model="selectedBranch"
                    class="block w-full rounded-xl border-slate-300 bg-slate-50 text-xs font-semibold text-slate-800 py-2.5 px-3 focus:border-emerald-600 focus:ring-emerald-600"
                >
                    <template x-for="b in branches" :key="b.code">
                        <option :value="b.code" x-text="b.name"></option>
                    </template>
                </select>
                <span class="text-[11px] text-slate-500" x-show="selectedBranch !== 'ALL'">
                    Filtering activity for <strong x-text="selectedBranch"></strong>
                </span>
                <span class="text-[11px] text-slate-500" x-show="selectedBranch === 'ALL'">
                    Showing consolidated Ethiopian network activity
                </span>
            </div>
        </div>

        <!-- SRS Section 96 Exact Agent CRM Dashboard KPI Strip -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:border-slate-300 transition">
                <span class="text-xs font-medium text-slate-500">Total Leads</span>
                <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ number_format($stats['kpis']['total_leads']) }}</div>
                <div class="mt-2 flex items-center justify-between text-[11px]">
                    <span class="text-emerald-700 font-semibold">10 Channels Tracked</span>
                    <span class="text-slate-400">SRS §97</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:border-slate-300 transition">
                <span class="text-xs font-medium text-slate-500">New Leads</span>
                <div class="text-2xl font-extrabold text-sky-700 mt-1">{{ number_format($stats['kpis']['new_leads']) }}</div>
                <div class="mt-2 flex items-center justify-between text-[11px]">
                    <span class="text-slate-500">Awaiting First Contact</span>
                    <span class="text-sky-600 font-medium">SRS §98</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:border-slate-300 transition">
                <span class="text-xs font-medium text-slate-500">Viewings</span>
                <div class="text-2xl font-extrabold text-amber-700 mt-1">{{ number_format($stats['kpis']['viewings_count']) }}</div>
                <div class="mt-2 flex items-center justify-between text-[11px]">
                    <span class="text-slate-500">Confirmed Visits</span>
                    <span class="text-amber-700 font-medium">SRS §72</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:border-slate-300 transition">
                <span class="text-xs font-medium text-slate-500">Active Offers</span>
                <div class="text-2xl font-extrabold text-teal-800 mt-1">{{ number_format($stats['kpis']['active_offers_count']) }}</div>
                <div class="mt-2 flex items-center justify-between text-[11px]">
                    <span class="text-teal-700 font-semibold">In Negotiation Ladder</span>
                    <span class="text-slate-400">SRS §77</span>
                </div>
            </div>

            <div class="bg-slate-900 text-white rounded-2xl p-5 shadow-sm col-span-2 sm:col-span-1">
                <span class="text-xs font-medium text-slate-400">Closed Deals</span>
                <div class="text-2xl font-extrabold text-emerald-400 mt-1">{{ number_format($stats['kpis']['closed_deals_count']) }}</div>
                <div class="mt-2 flex items-center justify-between text-[11px]">
                    <span class="text-slate-300 font-mono">ETB {{ number_format($stats['kpis']['recorded_commission_etb'] > 0 ? $stats['kpis']['recorded_commission_etb'] : $stats['kpis']['estimated_commission_etb']) }}</span>
                    <span class="text-emerald-400 font-semibold">{{ $stats['kpis']['recorded_commission_etb'] > 0 ? 'Recorded' : 'Estimated' }}</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Left 7 Cols: SRS Section 98 CRM Pipeline & Lead Source Tracker -->
            <div class="lg:col-span-7 space-y-6">
                <!-- Lead Pipeline Board -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">CRM Lead Pipeline (SRS Sections 97–98)</h2>
                            <p class="text-xs text-slate-500">
                                Sequential progression: New Lead → Contacted → Qualified → Viewing → Negotiation → Offer → Closed
                            </p>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700" x-text="filteredLeads.length + ' Active Leads'"></span>
                    </div>

                    <!-- Pipeline Stage Ribbon Guide -->
                    <div class="grid grid-cols-7 text-center text-[10px] font-bold text-slate-500 bg-slate-50 rounded-xl p-2 border border-slate-100">
                        <span class="py-1">New</span>
                        <span class="py-1">Contact</span>
                        <span class="py-1">Qual</span>
                        <span class="py-1">Viewing</span>
                        <span class="py-1">Negot.</span>
                        <span class="py-1">Offer</span>
                        <span class="py-1 text-emerald-700">Closed</span>
                    </div>

                    <!-- Lead Cards -->
                    <div class="space-y-3">
                        <template x-for="lead in filteredLeads" :key="lead.code">
                            <div class="p-4 rounded-xl border border-slate-200 bg-white hover:border-slate-300 shadow-xs transition flex flex-col md:flex-row md:items-center justify-between gap-3">
                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-xs font-semibold text-slate-500" x-text="lead.code"></span>
                                        <strong class="text-sm text-slate-900" x-text="lead.client"></strong>
                                        <span
                                            :class="{
                                                'bg-sky-100 text-sky-800': lead.source === 'telegram',
                                                'bg-emerald-100 text-emerald-800': lead.source === 'whatsapp',
                                                'bg-teal-100 text-teal-800': lead.source === 'website',
                                                'bg-amber-100 text-amber-800': lead.source === 'walk_in',
                                                'bg-indigo-100 text-indigo-800': lead.source === 'portal_referral'
                                            }"
                                            class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                            x-text="lead.source"
                                        ></span>
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-mono" x-text="lead.branch"></span>
                                    </div>
                                    <p class="text-xs text-slate-600" x-text="lead.property"></p>
                                    <div class="flex items-center gap-3 text-[11px] text-slate-500">
                                        <span>Target: <strong class="text-slate-800" x-text="lead.budget"></strong></span>
                                        <span>•</span>
                                        <span>Next: <span class="font-medium text-slate-700" x-text="lead.followUp"></span></span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 self-start md:self-center">
                                    <span
                                        :class="{
                                            'bg-slate-100 text-slate-700': lead.stage === 'new_lead',
                                            'bg-sky-100 text-sky-800': lead.stage === 'contacted',
                                            'bg-blue-100 text-blue-800': lead.stage === 'qualified',
                                            'bg-amber-100 text-amber-800': lead.stage === 'viewing',
                                            'bg-teal-100 text-teal-800': lead.stage === 'negotiation',
                                            'bg-purple-100 text-purple-800': lead.stage === 'offer',
                                            'bg-emerald-100 text-emerald-800': lead.stage === 'closed'
                                        }"
                                        class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide"
                                        x-text="lead.stage.replace('_', ' ')"
                                    ></span>

                                    <button
                                        type="button"
                                        @click="advanceLead(lead.code)"
                                        x-show="lead.stage !== 'closed'"
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-900 hover:bg-slate-800 text-white transition cursor-pointer"
                                        title="Advance to next SRS Section 98 stage"
                                    >
                                        Advance →
                                    </button>
                                </div>
                            </div>
                        </template>

                        <div x-show="filteredLeads.length === 0" class="p-8 text-center bg-slate-50 rounded-xl border border-dashed border-slate-200 text-xs text-slate-500">
                            <p class="font-medium text-slate-700">No CRM leads found for this branch selection.</p>
                            <p class="mt-1">Inbound inquiries and new client leads will appear here.</p>
                        </div>
                    </div>
                </div>

                <!-- SRS Section 71 & 72 Viewing Scheduler Strip -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Property Viewing Appointments (SRS Sections 71–72)</h3>
                            <p class="text-xs text-slate-500">Scheduled in-person and live video inspections across branches</p>
                        </div>
                        <span class="text-xs font-medium text-emerald-700">3 Today</span>
                    </div>

                    <div class="space-y-3">
                        <template x-for="v in viewings" :key="v.id">
                            <div class="p-3.5 rounded-xl border border-slate-100 bg-slate-50/70 flex flex-wrap items-center justify-between gap-3 text-xs">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <strong class="text-slate-900 text-sm" x-text="v.client"></strong>
                                        <span class="px-2 py-0.5 rounded bg-slate-200 text-slate-700 text-[10px] font-bold uppercase" x-text="v.type"></span>
                                        <span class="text-slate-500" x-text="'• ' + v.location"></span>
                                    </div>
                                    <p class="text-slate-600 mt-0.5" x-text="v.property + ' (Assigned: ' + v.agent + ')'"></p>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold text-slate-900 block" x-text="v.timeSlot"></span>
                                    <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800" x-text="v.status"></span>
                                </div>
                            </div>
                        </template>

                        <div x-show="viewings.length === 0" class="p-6 text-center bg-slate-50 rounded-xl border border-dashed border-slate-200 text-xs text-slate-500">
                            No viewing appointments scheduled for this selection.
                        </div>
            <div class="lg:col-span-5 space-y-6">
                <!-- SRS Section 76 & 77 Live Negotiation Room -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <div class="flex items-start justify-between border-b border-slate-100 pb-4">
                        <div>
                            <span class="text-[11px] font-mono text-emerald-700 font-bold">OFR-2026-000089 • SRS §76/77</span>
                            <h3 class="text-base font-bold text-slate-900 mt-0.5">Live Offer Negotiation Room</h3>
                            <span class="text-xs text-slate-500">Bole Villa • Listed Asking Price</span>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-extrabold text-slate-900">
                                ETB <span x-text="askingPriceEtb.toLocaleString()"></span>
                            </div>
                            <span
                                :class="{
                                    'bg-amber-100 text-amber-800': offerStatus === 'countered' || offerStatus === 'submitted',
                                    'bg-emerald-100 text-emerald-800': offerStatus === 'accepted',
                                    'bg-red-100 text-red-800': offerStatus === 'rejected'
                                }"
                                class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider mt-1"
                                x-text="offerStatus"
                            ></span>
                        </div>
                    </div>

                    <!-- Current Negotiation Standing / Discount -->
                    <div class="grid grid-cols-2 gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                        <div>
                            <span class="text-slate-500 block">Latest Offer Amount</span>
                            <strong class="text-base font-extrabold text-slate-900" x-text="'ETB ' + currentOfferAmount.toLocaleString()"></strong>
                        </div>
                        <div class="text-right">
                            <span class="text-slate-500 block">Discount from Asking</span>
                            <strong class="text-base font-bold text-emerald-700" x-text="'ETB ' + currentDiscountEtb.toLocaleString() + ' (' + currentDiscountPct + '%)'"></strong>
                        </div>
                    </div>

                    <!-- Immutable Ladder History List -->
                    <div class="space-y-2.5 max-h-[300px] overflow-y-auto pr-1">
                        <template x-for="step in negotiationHistory" :key="step.round">
                            <div
                                :class="step.party === 'buyer' ? 'bg-sky-50/70 border-sky-200' : 'bg-emerald-50/70 border-emerald-200'"
                                class="p-3 rounded-xl border text-xs space-y-1 transition"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            :class="step.party === 'buyer' ? 'bg-sky-600' : 'bg-emerald-600'"
                                            class="w-2 h-2 rounded-full inline-block"
                                        ></span>
                                        <strong class="text-slate-900 font-bold" x-text="'Round ' + step.round + ': ' + step.label"></strong>
                                    </div>
                                    <span class="text-xs font-mono font-extrabold text-slate-900" x-text="'ETB ' + step.amount.toLocaleString()"></span>
                                </div>
                                <p class="text-[11px] text-slate-600 italic" x-text="step.note"></p>
                                <span class="text-[10px] text-slate-400 block" x-text="step.time"></span>
                            </div>
                        </template>
                    </div>

                    <!-- Interactive Turn Submission (Simulating Alternating Turns) -->
                    <div class="border-t border-slate-100 pt-4 space-y-3" x-show="offerStatus !== 'accepted' && offerStatus !== 'rejected'">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-600">Turn to Respond:</span>
                            <span
                                :class="awaitingParty === 'buyer' ? 'bg-sky-100 text-sky-800' : 'bg-emerald-100 text-emerald-800'"
                                class="px-2.5 py-0.5 rounded text-[11px] font-bold uppercase"
                                x-text="awaitingParty === 'buyer' ? 'Buyer Turn' : 'Seller Turn'"
                            ></span>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">
                                Propose Counteroffer (ETB)
                            </label>
                            <div class="flex gap-2">
                                <input
                                    type="number"
                                    x-model="customCounterInput"
                                    step="50000"
                                    class="w-full text-xs font-mono font-bold rounded-xl border-slate-300 bg-slate-50 px-3 py-2 focus:border-emerald-600 focus:ring-emerald-600"
                                />
                                <button
                                    type="button"
                                    @click="submitCounterturn(awaitingParty, 'counteroffer')"
                                    class="px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition whitespace-nowrap cursor-pointer"
                                >
                                    Counter →
                                </button>
                            </div>
                        </div>

                        <div class="flex gap-2 pt-1">
                            <button
                                type="button"
                                @click="submitCounterturn(awaitingParty, 'accept')"
                                class="flex-1 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition cursor-pointer"
                            >
                                ✓ Accept (ETB <span x-text="currentOfferAmount.toLocaleString()"></span>)
                            </button>
                            <button
                                type="button"
                                @click="submitCounterturn(awaitingParty, 'reject')"
                                class="py-2 px-3 rounded-xl bg-slate-100 hover:bg-red-50 hover:text-red-700 text-slate-700 text-xs font-semibold transition cursor-pointer"
                            >
                                Reject
                            </button>
                        </div>
                    </div>

                    <div x-show="offerStatus === 'accepted'" class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-center space-y-1">
                        <span class="text-sm font-bold text-emerald-800 block">✓ Deal Agreement Finalized</span>
                        <p class="text-xs text-emerald-700">All 5 rounds immutably preserved in the ledger for legal signing.</p>
                    </div>
                </div>

                <!-- SRS Section 99 Configurable Commission Engine Widget -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <span class="text-[11px] font-mono text-emerald-700 font-bold">SRS Section 99</span>
                            <h3 class="text-base font-bold text-slate-900">Commission Calculator</h3>
                        </div>
                        <span class="text-xs font-bold text-slate-500">20M ETB × 2% = 400k</span>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div>
                            <label class="text-[11px] text-slate-500 font-semibold block mb-1">Transaction Value (ETB)</label>
                            <input
                                type="number"
                                x-model.number="commissionSalePrice"
                                step="500000"
                                class="w-full text-xs font-mono font-bold rounded-xl border-slate-300 bg-slate-50 px-3 py-2"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-[11px] text-slate-500 font-semibold block mb-1">Platform Rate (%)</label>
                                <input
                                    type="number"
                                    x-model.number="commissionRatePct"
                                    step="0.1"
                                    class="w-full text-xs font-mono rounded-xl border-slate-300 bg-slate-50 px-3 py-2"
                                />
                            </div>
                            <div>
                                <label class="text-[11px] text-slate-500 font-semibold block mb-1">Agent Split (%)</label>
                                <input
                                    type="number"
                                    x-model.number="agentSplitPct"
                                    step="5"
                                    class="w-full text-xs font-mono rounded-xl border-slate-300 bg-slate-50 px-3 py-2"
                                />
                            </div>
                        </div>

                        <!-- Results Breakdown -->
                        <div class="p-3.5 rounded-xl bg-slate-900 text-white space-y-2 mt-2">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Total Gross Commission</span>
                                <strong class="text-emerald-400 text-sm font-mono">
                                    ETB <span x-text="computedCommissionTotal.toLocaleString()"></span>
                                </strong>
                            </div>
                            <div class="flex items-center justify-between text-[11px] border-t border-slate-800 pt-2 text-slate-300">
                                <span>Agent Payout (<span x-text="agentSplitPct"></span>%)</span>
                                <span class="font-mono font-semibold">ETB <span x-text="computedAgentShare.toLocaleString()"></span></span>
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-slate-300">
                                <span>Company Retained (<span x-text="100 - agentSplitPct"></span>%)</span>
                                <span class="font-mono font-semibold">ETB <span x-text="computedCompanyShare.toLocaleString()"></span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
