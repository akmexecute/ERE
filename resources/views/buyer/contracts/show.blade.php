<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <!-- Navigation Breadcrumb & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-5">
            <div>
                <nav class="flex items-center space-x-2 text-xs text-slate-500 mb-1">
                    <a href="{{ route('dashboard', ['tab' => 'purchases']) }}" class="hover:text-teal-700 transition">My Purchases</a>
                    <span>/</span>
                    <span class="text-slate-800 font-medium">Sales Contract</span>
                </nav>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $transactionCode ?? $contract?->contract_code ?? $offer?->offer_code }}</h1>
                    @php
                        $st = $contract ? ($contract->status->value ?? (string) $contract->status) : ($offer?->status ?? 'accepted');
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                        {{ $st === 'active' || $st === 'accepted' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-700 border border-slate-200' }}">
                        {{ ucfirst($st) }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">Signed on {{ ($contract?->signed_at ?? $offer?->accepted_at) ? ($contract?->signed_at ?? $offer?->accepted_at)->format('M d, Y') : 'Pending Signature' }} • Statutory Reference: Proclamation No. 1357/2024</p>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-3">
                <a href="#file-complaint" class="inline-flex items-center px-3.5 py-2 rounded-xl text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 transition shadow-xs">
                    <svg class="w-4 h-4 mr-1.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    File Transaction Dispute
                </a>
                <a href="{{ route('dashboard', ['tab' => 'purchases']) }}" class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 transition shadow-xs">
                    Back to Dashboard
                </a>
            </div>
        </div>

        @if(session('status'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @php
            // Statutory Lifecycle Stages: Offer Accepted -> Contract Prepared -> Contract Signed -> Payment Confirmed -> Sale Completed
            $isOffer = !empty($offer);
            $stage1Done = $isOffer ? ($offer->status === 'accepted') : true;
            $stage2Done = !$isOffer && in_array($contract?->status?->value ?? (string) $contract?->status, ['draft', 'active', 'completed'], true);
            $stage3Done = !$isOffer && (!empty($contract?->signed_at) || in_array($contract?->status?->value ?? (string) $contract?->status, ['active', 'completed'], true));
            $stage4Done = !$isOffer && (($contract?->deposit_paid_etb ?? 0) > 0 || ($transactions->where('status', 'verified')->count() > 0));
            $stage5Done = in_array($contract?->status?->value ?? (string) $contract?->status, ['completed'], true);

            // Determine active step index (1 to 5)
            $activeStep = 1;
            if ($stage5Done) { $activeStep = 5; }
            elseif ($stage4Done) { $activeStep = 5; }
            elseif ($stage3Done) { $activeStep = 4; }
            elseif ($stage2Done) { $activeStep = 3; }
            elseif ($stage1Done) { $activeStep = 2; }

            $stages = [
                1 => ['label' => 'Offer Accepted', 'date' => ($isOffer ? $offer->accepted_at : $contract?->created_at), 'done' => $stage1Done],
                2 => ['label' => 'Contract Prepared', 'date' => ($contract?->created_at), 'done' => $stage2Done],
                3 => ['label' => 'Contract Signed', 'date' => ($contract?->signed_at), 'done' => $stage3Done],
                4 => ['label' => 'Payment Confirmed', 'date' => null, 'done' => $stage4Done],
                5 => ['label' => 'Sale Completed & Recorded', 'date' => null, 'done' => $stage5Done],
            ];
        @endphp

        <!-- 5-Stage Transaction Lifecycle Progress Bar -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
            <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4">Statutory Transaction Lifecycle &amp; Legal Conveyance Milestones</h2>
            <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                @foreach($stages as $stepNum => $stg)
                    @php
                        $isCurrent = ($stepNum === $activeStep);
                        $isCompleted = $stg['done'];
                    @endphp
                    <div class="relative flex flex-col p-3 rounded-xl border text-xs transition
                        {{ $isCompleted ? 'bg-emerald-50/60 border-emerald-200 text-emerald-950' : ($isCurrent ? 'bg-teal-50 border-teal-300 text-teal-950 ring-1 ring-teal-400' : 'bg-slate-50 border-slate-200 text-slate-500') }}">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="font-bold text-[10px] uppercase tracking-wide
                                {{ $isCompleted ? 'text-emerald-700' : ($isCurrent ? 'text-teal-700' : 'text-slate-400') }}">
                                Stage 0{{ $stepNum }}
                            </span>
                            @if($isCompleted)
                                <span class="w-4 h-4 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px]">✓</span>
                            @elseif($isCurrent)
                                <span class="w-2 h-2 rounded-full bg-teal-600 animate-pulse"></span>
                            @else
                                <span class="w-3 h-3 rounded-full border border-slate-300"></span>
                            @endif
                        </div>
                        <span class="font-semibold text-xs leading-snug">{{ $stg['label'] }}</span>
                        <span class="text-[10px] mt-1 text-slate-500">
                            @if($isCompleted)
                                {{ $stg['date'] ? $stg['date']->format('M d, Y') : 'Confirmed' }}
                            @elseif($isCurrent)
                                <span class="text-teal-700 font-medium">In Progress</span>
                            @else
                                Pending
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left 2 Cols: Seller Accountability & Unit Details -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Verified Seller Identity & Legal Accountability Card -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-5">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div class="flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-xl bg-teal-50 border border-teal-100 flex items-center justify-center text-teal-700 font-bold">
                                🏢
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-slate-900">Verified Seller &amp; Developer Identity Record</h2>
                                <p class="text-xs text-slate-500">Immutable transaction-time snapshot preserved for consumer accountability</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 mr-1.5"></span>
                            Level {{ $snapshot['verification_level'] ?? '2' }} Verified
                        </span>
                    </div>

                    @php
                        $isIndividual = ($snapshot['seller_type'] ?? '') === 'individual_owner';
                    @endphp

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100 space-y-1">
                            <span class="text-slate-500 font-medium">{{ $isIndividual ? 'Verified Property Owner' : 'Seller / Developer Entity' }}</span>
                            <p class="text-slate-900 font-semibold text-sm">{{ $isIndividual ? ($snapshot['seller_name'] ?? 'Direct Owner') : ($snapshot['company_legal_name'] ?? $contract?->developerCompany?->legal_name ?? 'Gift Real Estate PLC') }}</p>
                            <p class="text-slate-600">{{ $isIndividual ? ('Phone: ' . ($snapshot['masked_phone'] ?? 'Protected')) : ('Trade: ' . ($snapshot['trade_name'] ?? $contract?->developerCompany?->trade_name ?? 'Gift Real Estate')) }}</p>
                        </div>
                        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100 space-y-1">
                            <span class="text-slate-500 font-medium">{{ $isIndividual ? 'National Identity & Authority' : 'Business Registration & Tax' }}</span>
                            @if($isIndividual)
                                <p class="text-slate-900 font-semibold">National ID (Fayda): <span class="font-mono text-slate-800">{{ $snapshot['masked_national_id'] ?? 'FAN-****-0000' }}</span></p>
                                <p class="text-slate-600">Ownership Status: <span class="font-medium text-emerald-700">{{ ucwords(str_replace('_', ' ', $snapshot['property_ownership']['ownership_status'] ?? 'authority_verified')) }}</span></p>
                            @else
                                <p class="text-slate-900 font-semibold">TIN: <span class="font-mono text-slate-800">{{ $snapshot['tax_identification_number'] ?? '0019284710' }}</span></p>
                                <p class="text-slate-600">License: <span class="font-mono">{{ $snapshot['business_license_number'] ?? 'BL/AA/2023/00192' }}</span></p>
                            @endif
                        </div>
                    </div>

                    <!-- Verification Review & Legal Note -->
                    <div class="p-3.5 bg-teal-50/70 rounded-xl border border-teal-100 text-xs text-teal-900 space-y-2">
                        <div class="flex items-center justify-between font-semibold">
                            <span>Verification Basis: {{ ucwords(str_replace('_', ' ', $snapshot['verification_method'] ?? 'internal_manual_review')) }}</span>
                            <span class="font-mono text-[11px] text-teal-800">{{ $snapshot['verification_reference'] ?? 'VER-COMP-001' }}</span>
                        </div>
                        <p class="text-teal-800 text-[11px] leading-relaxed">
                            <strong>Platform Review Distinction:</strong>
                            @if($isIndividual)
                                This seller's identity has been verified through Ethiopian National ID / Fayda review, and legal ownership of this property was confirmed via title deed / leasehold documentation prior to contract execution.
                            @else
                                This developer has been verified through statutory trade license, TIN tax compliance, and construction permit documentation approved by authorized platform compliance officers.
                            @endif
                            @if(empty($snapshot['is_official_fayda_api_verified']))
                                <em>(Note: National ID verification is reviewed internally by compliance officers; direct integration with the National ID / Fayda API is pending statutory portal release).</em>
                            @endif
                        </p>
                    </div>

                    <!-- Authorized Signer / Mandated Representative -->
                    <div class="pt-2 border-t border-slate-100">
                        <h3 class="text-xs font-semibold text-slate-700 mb-2">{{ !empty($snapshot['authorized_representative']) ? 'Authorized Signer & Mandated Agent' : 'Verified Signer & Identity Record' }}</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                            <div>
                                <span class="text-slate-400 block">Name</span>
                                <span class="text-slate-900 font-medium">{{ $snapshot['authorized_representative']['name'] ?? $snapshot['seller_name'] ?? 'Authorized Representative' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block">Contact Phone</span>
                                <span class="text-slate-900 font-mono font-medium">{{ $snapshot['authorized_representative']['masked_phone'] ?? $snapshot['masked_phone'] ?? '+251911***44' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block">Signer National ID</span>
                                <span class="text-slate-900 font-mono font-medium">{{ $snapshot['authorized_representative']['masked_national_id'] ?? $snapshot['masked_national_id'] ?? 'FAN-****-1829' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Unit & Project Specifications -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                    <h3 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-3">Contracted Property Inventory</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                        <div>
                            <span class="text-slate-400 block">Property / Project</span>
                            <span class="text-slate-900 font-semibold">{{ $snapshot['property']['project_name'] ?? $snapshot['property']['property_title'] ?? $contract?->project?->title_en ?? $offer?->property?->title ?? 'Property Record' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block">Identifier / Unit</span>
                            <span class="text-slate-900 font-mono font-semibold">{{ $snapshot['property']['unit_code'] ?? $snapshot['property']['property_code'] ?? $contract?->unit?->unit_number ?? $offer?->property?->property_code ?? 'PROP-001' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block">Property Type</span>
                            <span class="text-slate-900 font-medium">{{ ucfirst($snapshot['property']['unit_type'] ?? $offer?->property?->propertyType?->name_en ?? 'Residential') }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block">Permit / Title Document</span>
                            <span class="text-slate-900 font-mono">{{ $snapshot['property']['construction_permit_number'] ?? $snapshot['property']['ownership_evidence_type'] ?? 'Verified Documentation' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Transaction Dispute / Complaint Filing Section -->
                <div id="file-complaint" class="bg-white rounded-2xl border border-rose-200 p-6 shadow-xs space-y-4">
                    <div class="flex items-center justify-between border-b border-rose-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-rose-600 font-bold text-lg">⚠️</span>
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Lodge Seller Complaint or Transaction Dispute</h3>
                                <p class="text-xs text-slate-500">Report breach of sales contract, delivery failure, or fraudulent seller conduct</p>
                            </div>
                        </div>
                        <span class="text-xs font-mono text-rose-700 bg-rose-50 px-2.5 py-1 rounded-full border border-rose-200">Protected Channel</span>
                    </div>

                    <form action="{{ route('buyer.contracts.complaint', ['contract' => $transactionCode ?? $contract?->contract_code ?? $offer?->offer_code]) }}" method="POST" class="space-y-4 text-xs">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Dispute Subject <span class="text-rose-500">*</span></label>
                                <input type="text" name="subject" required placeholder="e.g. Developer unresponsive after milestone deposit" class="w-full text-xs rounded-xl border border-slate-300 px-3 py-2 focus:border-rose-500 focus:ring-rose-500">
                            </div>
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Issue Category</label>
                                <select name="category" class="w-full text-xs rounded-xl border border-slate-300 px-3 py-2 focus:border-rose-500 focus:ring-rose-500">
                                    <option value="fraud">Fraudulent Seller / Disappearance</option>
                                    <option value="payment">Payment &amp; Deposit Discrepancy</option>
                                    <option value="property">Construction Delay / Defect</option>
                                    <option value="verification">Ownership / Permit Dispute</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Detailed Description &amp; Incident Report <span class="text-rose-500">*</span></label>
                            <textarea name="description" rows="3" required placeholder="Describe what happened, payment references, attempts to contact the seller, and evidence of breach..." class="w-full text-xs rounded-xl border border-slate-300 px-3 py-2 focus:border-rose-500 focus:ring-rose-500"></textarea>
                        </div>

                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-[11px] text-slate-600 flex items-center justify-between">
                            <span>Evidence snapshot including developer TIN, contract code, and deposit receipts will be automatically linked.</span>
                            <button type="submit" class="px-4 py-2 bg-rose-700 hover:bg-rose-800 text-white font-semibold rounded-xl shadow-xs transition">
                                Submit Formal Dispute
                            </button>
                        </div>
                    </form>
                </div>

                @if($complaints->isNotEmpty())
                    <!-- Historical Complaints for this contract -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                        <h3 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-3">Dispute &amp; Complaint History ({{ $complaints->count() }})</h3>
                        <div class="space-y-3 text-xs">
                            @foreach($complaints as $ticket)
                                <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/60 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-slate-900">{{ $ticket->ticket_code }}</span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-100 text-rose-800">{{ ucfirst($ticket->category) }}</span>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-800">{{ ucfirst($ticket->status) }}</span>
                                    </div>
                                    <p class="font-medium text-slate-800">{{ $ticket->subject }}</p>
                                    <p class="text-slate-600">{{ $ticket->description }}</p>
                                    <div class="text-[11px] text-slate-400 flex items-center justify-between pt-1 border-t border-slate-100">
                                        <span>Submitted: {{ $ticket->created_at->format('M d, Y H:i') }}</span>
                                        <span>Staff: {{ $ticket->assignedStaff?->name ?? 'Compliance Review Queue' }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Right Col: Financial Terms & Installment Milestones -->
            <div class="space-y-6">
                @if($contract)
                    <!-- Financial Summary Card -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                        <h3 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-3">Contract Financial Summary</h3>
                        
                        <div class="space-y-3 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Agreed Contract Price</span>
                                <span class="font-bold text-slate-900 font-mono text-sm">ETB {{ number_format((float)$contract->contract_price_etb, 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Deposit Paid</span>
                                <span class="font-semibold text-emerald-700 font-mono">ETB {{ number_format((float)$contract->deposit_paid_etb, 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                                <span class="text-slate-700 font-semibold">Remaining Balance</span>
                                <span class="font-bold text-teal-800 font-mono text-sm">ETB {{ number_format((float)$contract->balance_payable_etb, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Milestone Installment Schedule -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                        <h3 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-3">Installment Schedule ({{ $installments->count() }})</h3>

                        <div class="space-y-3 text-xs">
                            @foreach($installments as $inst)
                                <div class="p-3 rounded-xl border {{ $inst->status === 'paid' ? 'bg-emerald-50/50 border-emerald-200' : 'bg-slate-50 border-slate-200' }} flex items-center justify-between">
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-slate-800">#{{ $inst->sequence_number }}</span>
                                            <span class="text-slate-700 font-medium">{{ $inst->milestone_label_en }}</span>
                                        </div>
                                        <span class="text-[11px] text-slate-500 font-mono">{{ $inst->percentage }}% • ETB {{ number_format((float)$inst->amount_etb, 2) }}</span>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $inst->status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                        {{ ucfirst($inst->status) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @elseif(isset($offer) && $offer)
                    <!-- Property Offer Financial Summary Card -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                        <h3 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-3">Agreed Transaction Summary</h3>
                        
                        <div class="space-y-3 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Agreed Sale Price</span>
                                <span class="font-bold text-slate-900 font-mono text-sm">ETB {{ number_format((float)$offer->latest_amount_etb, 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Listed Asking Price</span>
                                <span class="text-slate-600 font-mono">ETB {{ number_format((float)$offer->listed_asking_price_etb, 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                                <span class="text-slate-700 font-semibold">Negotiated Difference</span>
                                <span class="font-semibold text-emerald-700 font-mono">ETB {{ number_format((float)($offer->listed_asking_price_etb - $offer->latest_amount_etb), 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Property Purchase Balance & Payments Card -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="text-sm font-semibold text-slate-900">Payment Transactions & Ledger Proofs</h3>
                            <a href="{{ route('payments.checkout', ['payable_type' => \App\Domains\Offer\Models\PropertyOffer::class, 'payable_id' => $offer->id]) }}" class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-lg bg-amber-600 text-white hover:bg-amber-700 transition">
                                Make Payment
                            </a>
                        </div>
                        
                        <div class="space-y-3 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Confirmed Paid</span>
                                <span class="font-semibold text-emerald-700 font-mono">ETB {{ number_format($offer->calculateTotalPaidEtb(), 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between pt-1 border-t border-slate-100">
                                <span class="text-slate-700 font-semibold">Remaining Balance</span>
                                <span class="font-bold text-amber-700 font-mono text-sm">ETB {{ number_format($offer->calculateRemainingBalanceEtb(), 2) }}</span>
                            </div>
                        </div>

                        @if($transactions->isNotEmpty())
                            <div class="mt-4 pt-3 border-t border-slate-100 space-y-2">
                                <h4 class="text-xs font-bold text-slate-700">Recorded Transactions</h4>
                                @foreach($transactions as $txn)
                                    <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-between text-xs">
                                        <div>
                                            <span class="font-mono font-bold text-slate-800">{{ $txn->reference_code ?? $txn->transaction_reference }}</span>
                                            <span class="text-slate-500 block text-[10px]">{{ ucfirst($txn->payment_method->value ?? (string)$txn->payment_method) }} • {{ ($txn->confirmed_at ?? $txn->created_at)->format('M d, Y') }}</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="font-mono font-bold text-slate-900">ETB {{ number_format((float)($txn->total_amount_etb ?? $txn->amount_etb), 2) }}</span>
                                            <span class="block text-[10px] font-semibold {{ ($txn->status->value ?? (string)$txn->status) === 'successful' || ($txn->status->value ?? (string)$txn->status) === 'verified' ? 'text-emerald-600' : 'text-amber-600' }}">
                                                {{ ucfirst($txn->status->value ?? (string)$txn->status) }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

