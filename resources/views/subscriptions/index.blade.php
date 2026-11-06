<x-app-layout>
    <div
        x-data="{
            selectedPlanId: {{ $plans->where('code', 'agent_pro')->first()->id ?? $plans->first()->id ?? 1 }},
            selectedPlanCode: '{{ $plans->where('code', 'agent_pro')->first()->code ?? $plans->first()->code ?? 'free' }}',
            selectedPlanPrice: {{ (float) ($plans->where('code', 'agent_pro')->first()->price_etb ?? 0) }},
            selectedPlanName: '{{ $plans->where('code', 'agent_pro')->first()->name_en ?? 'Selected Plan' }}',
            selectedPlanDuration: {{ (int) ($plans->where('code', 'agent_pro')->first()->duration_days ?? 30) }},
            
            selectedPromoId: '',
            selectedPromoPrice: 0.00,
            selectedPromoTitle: '',
            targetListingId: '',
            
            selectedAdId: '',
            selectedAdPrice: 0.00,
            selectedAdTitle: '',
            targetAdTitle: '',
            targetAdUrl: '',
            
            paymentMethod: 'telebirr',
            
            isPlanFree() {
                return this.selectedPlanPrice <= 0 || this.selectedPlanCode === 'free';
            },

            selectPlan(plan) {
                this.selectedPlanId = plan.id;
                this.selectedPlanCode = plan.code;
                this.selectedPlanPrice = parseFloat(plan.price_etb);
                this.selectedPlanName = plan.name_en;
                this.selectedPlanDuration = parseInt(plan.duration_days);

                if (this.isPlanFree()) {
                    this.selectedPromoId = '';
                    this.selectedPromoPrice = 0.00;
                    this.selectedAdId = '';
                    this.selectedAdPrice = 0.00;
                }
            },

            selectPromo(pkg) {
                if (this.isPlanFree()) return;
                if (!pkg || this.selectedPromoId === pkg.id) {
                    this.selectedPromoId = '';
                    this.selectedPromoPrice = 0.00;
                    this.selectedPromoTitle = '';
                } else {
                    this.selectedPromoId = pkg.id;
                    this.selectedPromoPrice = parseFloat(pkg.price_etb);
                    this.selectedPromoTitle = pkg.name_en;
                }
            },

            selectAd(ad) {
                if (this.isPlanFree()) return;
                if (!ad || this.selectedAdId === ad.id) {
                    this.selectedAdId = '';
                    this.selectedAdPrice = 0.00;
                    this.selectedAdTitle = '';
                } else {
                    this.selectedAdId = ad.id;
                    this.selectedAdPrice = parseFloat(ad.price_etb);
                    this.selectedAdTitle = ad.name_en;
                }
            },

            subtotal() {
                return this.selectedPlanPrice + this.selectedPromoPrice + this.selectedAdPrice;
            },

            vatAmount() {
                return this.isPlanFree() ? 0.00 : +(this.subtotal() * 0.15).toFixed(2);
            },

            grandTotal() {
                return +(this.subtotal() + this.vatAmount()).toFixed(2);
            },

            formatMoney(val) {
                return new Intl.NumberFormat('en-ET', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val);
            }
        }"
        class="bg-slate-50 min-h-screen py-10 px-4 sm:px-6 lg:px-8 text-slate-800"
    >
        <div class="max-w-7xl mx-auto space-y-10">

            <!-- Hero Header -->
            <div class="text-center max-w-3xl mx-auto space-y-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-900 border border-amber-200">
                    <span class="h-2 w-2 rounded-full bg-amber-600"></span>
                    የአባላት ዕቅዶች &middot; Membership &amp; Monetization Engine
                </span>
                <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                    Scale Your Real Estate Reach Across Ethiopia
                </h1>
                <p class="text-slate-600 text-sm sm:text-base leading-relaxed">
                    Designed for independent brokers, accredited agencies, property developers, short-stay hosts, and private owners. Choose the right tier to expand your listings, publish 360° virtual tours, and access advanced PostGIS neighborhood heatmaps.
                </p>

                @if($activeSubscription)
                    <div class="mt-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-left inline-flex items-center gap-3">
                        <span class="text-xl">✅</span>
                        <div>
                            <div class="text-xs font-bold text-emerald-900">Current Active Subscription: <span class="uppercase font-mono text-emerald-800">{{ $activeSubscription->plan?->name_en ?? 'Active Plan' }}</span></div>
                            <div class="text-[11px] text-emerald-700">Valid until: <strong>{{ $activeSubscription->expires_at?->format('d M Y') }}</strong> ({{ $activeSubscription->expires_at?->diffForHumans() }})</div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Dynamic Plans Grid (Database-Driven) -->
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                @foreach($plans as $plan)
                    <div
                        data-plan-code="{{ $plan->code }}"
                        @click="selectPlan({{ \Illuminate\Support\Js::from($plan) }})"
                        :class="selectedPlanId === {{ $plan->id }} ? 'ring-2 ring-amber-600 border-amber-600 shadow-md bg-white' : 'border-slate-200 bg-white/90 hover:border-slate-300'"
                        class="rounded-2xl border p-4 sm:p-5 flex flex-col justify-between cursor-pointer transition relative"
                    >
                        @if($plan->code === 'agent_pro')
                            <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-2.5 py-0.5 rounded-full bg-amber-600 text-white text-[10px] font-bold uppercase tracking-wider shadow">
                                Popular
                            </div>
                        @endif

                        <div class="space-y-3">
                            <div class="flex items-start justify-between">
                                <h3 class="font-bold text-slate-900 text-base">{{ $plan->name_en }}</h3>
                                <div
                                    :class="selectedPlanId === {{ $plan->id }} ? 'bg-amber-600 text-white' : 'border-slate-300 text-transparent'"
                                    class="h-5 w-5 rounded-full border flex items-center justify-center text-xs"
                                >
                                    ✓
                                </div>
                            </div>

                            @if($plan->name_am)
                                <div class="text-xs text-slate-500 font-medium font-ethiopic">{{ $plan->name_am }}</div>
                            @endif

                            <div class="pt-2 border-t border-slate-100">
                                @if((float) $plan->price_etb <= 0)
                                    <div class="text-2xl font-black text-slate-900">Free</div>
                                    <div class="text-[11px] text-slate-500">No payment required</div>
                                @else
                                    <div class="text-2xl font-black text-slate-900">
                                        {{ number_format((float) $plan->price_etb, 0) }} <span class="text-xs font-semibold text-slate-500">ETB</span>
                                    </div>
                                    <div class="text-[11px] text-slate-500">per {{ $plan->duration_days }} days</div>
                                @endif
                            </div>

                            <p class="text-xs text-slate-600 leading-snug min-h-[48px]">
                                {{ $plan->description_en }}
                            </p>

                            <!-- Mini Feature Highlights -->
                            <ul class="text-[11px] space-y-1.5 pt-2 border-t border-slate-100 text-slate-600">
                                @php
                                    $valMap = $plan->featureValues->keyBy('feature_id');
                                @endphp
                                @foreach($features->take(4) as $f)
                                    @php
                                        $val = $valMap->get($f->id)?->value ?? $f->default_value;
                                    @endphp
                                    <li class="flex items-center gap-1.5 truncate">
                                        @if($val === 'true')
                                            <span class="text-emerald-600 font-bold">✓</span>
                                            <span class="truncate">{{ $f->name_en }}</span>
                                        @elseif($val === 'false' || empty($val))
                                            <span class="text-slate-300">&ndash;</span>
                                            <span class="text-slate-400 truncate">{{ $f->name_en }}</span>
                                        @else
                                            <span class="font-bold text-slate-800">{{ $val }}</span>
                                            <span class="truncate">{{ $f->name_en }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <button
                            type="button"
                            :class="selectedPlanId === {{ $plan->id }} ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-800 hover:bg-slate-200'"
                            class="mt-4 w-full py-1.5 px-3 rounded-xl text-xs font-semibold transition"
                        >
                            <span x-text="selectedPlanId === {{ $plan->id }} ? 'Selected' : 'Select Plan'"></span>
                        </button>
                    </div>
                @endforeach
            </div>

            <!-- Complete Feature Comparison Matrix (Module 33.1) -->
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
                <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold">Feature Comparison Matrix</h2>
                        <p class="text-xs text-slate-300">Detailed breakdown of quotas and privileges across all tiers.</p>
                    </div>
                    <span class="text-xs bg-slate-800 px-3 py-1 rounded-full text-slate-200 font-mono">SRS Module 33</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-slate-700">
                                <th class="p-3.5 pl-6 font-semibold w-1/4">Platform Capability / Feature</th>
                                @foreach($plans as $plan)
                                    <th class="p-3.5 text-center font-bold {{ $plan->code === 'agent_pro' ? 'bg-amber-50 text-amber-950' : '' }}">
                                        {{ $plan->name_en }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach($features as $f)
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="p-3.5 pl-6">
                                        <div class="font-semibold text-slate-900">{{ $f->name_en }}</div>
                                        @if($f->name_am)
                                            <div class="text-[11px] text-slate-400 font-ethiopic">{{ $f->name_am }}</div>
                                        @endif
                                        <div class="text-[11px] text-slate-500">{{ $f->description_en }}</div>
                                    </td>

                                    @foreach($plans as $plan)
                                        @php
                                            $fv = $plan->featureValues->firstWhere('feature_id', $f->id);
                                            $val = $fv ? $fv->value : $f->default_value;
                                        @endphp
                                        <td class="p-3.5 text-center {{ $plan->code === 'agent_pro' ? 'bg-amber-50/50' : '' }}">
                                            @if($f->value_type->value === 'boolean')
                                                @if($val === 'true' || $val === '1')
                                                    <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-emerald-100 text-emerald-800 font-bold">✓</span>
                                                @else
                                                    <span class="text-slate-300 font-bold text-base">&times;</span>
                                                @endif
                                            @elseif($val === 'unlimited')
                                                <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 font-semibold text-[11px]">Unlimited</span>
                                            @else
                                                <span class="font-bold text-slate-900">{{ $val }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Interactive Checkout Configuration Drawer / Form -->
            <form method="POST" action="{{ route('subscriptions.checkout') }}" class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-8">
                @csrf
                <input type="hidden" name="plan_id" :value="selectedPlanId">
                <input type="hidden" name="promotion_package_id" :value="selectedPromoId">
                <input type="hidden" name="ad_placement_id" :value="selectedAdId">
                <input type="hidden" name="payment_method" :value="paymentMethod">

                <div class="border-b border-slate-200 pb-4">
                    <h2 class="text-xl font-bold text-slate-900">Configure Subscription Checkout</h2>
                    <p class="text-xs text-slate-500">Review selected tier, add optional promotional marketing boosts, and complete payment.</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <!-- Left: Selected Plan & Add-ons Options (8 cols) -->
                    <div class="lg:col-span-8 space-y-6">

                        <!-- Selected Plan Summary Card -->
                        <div class="p-4 rounded-xl border border-amber-200 bg-amber-50/50 flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800">Selected Plan</span>
                                <div class="text-lg font-bold text-slate-900" x-text="selectedPlanName"></div>
                                <div class="text-xs text-slate-600">Validity: <span x-text="selectedPlanDuration"></span> days &middot; Currency: ETB</div>
                            </div>
                            <div class="text-right">
                                <div class="text-xl font-black text-slate-900" x-text="isPlanFree() ? 'Free' : formatMoney(selectedPlanPrice) + ' ETB'"></div>
                                <div class="text-[11px] text-slate-500">Base membership fee</div>
                            </div>
                        </div>

                        <!-- Optional Promoted Listing Upgrade Section (Module 33.5) -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                    <span>🚀</span> Promoted Listing Upgrade (Optional)
                                </h3>
                                <span class="text-xs text-slate-500">Higher rank &amp; visual badges</span>
                            </div>

                            <template x-if="isPlanFree()">
                                <div class="p-4 rounded-xl bg-slate-100 border border-slate-200 text-xs text-slate-600">
                                    <span class="font-semibold text-slate-800">Notice:</span> Standard Free tier listings are indexed at base relevance. Promotional ranking packages and highlight badges require an Agent Pro, Agency, Developer, or Enterprise plan.
                                </div>
                            </template>

                            <template x-if="!isPlanFree()">
                                <div class="space-y-3">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                                        @foreach($promotionPackages->where('tier.value', '!=', 'standard') as $promo)
                                            <div
                                                @click="selectPromo({{ \Illuminate\Support\Js::from($promo) }})"
                                                :class="selectedPromoId == {{ $promo->id }} ? 'ring-2 ring-amber-600 border-amber-600 bg-amber-50/30' : 'border-slate-200 hover:border-slate-300'"
                                                class="rounded-xl border p-3 cursor-pointer transition flex flex-col justify-between"
                                            >
                                                <div>
                                                    <div class="flex items-center justify-between">
                                                        <span class="font-bold text-xs text-slate-900">{{ $promo->name_en }}</span>
                                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-900">+{{ $promo->ranking_weight }} wt</span>
                                                    </div>
                                                    <div class="text-[11px] text-slate-500 mt-1">{{ $promo->duration_days }} days validity</div>
                                                </div>
                                                <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between">
                                                    <span class="font-extrabold text-slate-900 text-xs">+{{ number_format((float) $promo->price_etb, 0) }} ETB</span>
                                                    <span class="text-xs" :class="selectedPromoId == {{ $promo->id }} ? 'text-amber-600 font-bold' : 'text-slate-400'">
                                                        <span x-text="selectedPromoId == {{ $promo->id }} ? 'Added' : '+ Add'"></span>
                                                    </span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    @if(auth()->check() && $userListings->count() > 0)
                                        <div x-show="selectedPromoId" class="pt-2">
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">Apply Promotion to Property Listing:</label>
                                            <select name="target_listing_id" x-model="targetListingId" class="w-full rounded-xl border-slate-300 text-xs py-2">
                                                <option value="">-- Select Listing to Boost --</option>
                                                @foreach($userListings as $listing)
                                                    <option value="{{ $listing->id }}">#{{ $listing->id }} - {{ $listing->title_en }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                </div>
                            </template>
                        </div>

                        <!-- Optional Multi-Placement Advertising Section (Module 33.6) -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                    <span>📢</span> Multi-Placement Display Advertising (Optional)
                                </h3>
                                <span class="text-xs text-slate-500">Homepage, Search &amp; Category Banners</span>
                            </div>

                            <template x-if="isPlanFree()">
                                <div class="p-4 rounded-xl bg-slate-100 border border-slate-200 text-xs text-slate-600">
                                    <span class="font-semibold text-slate-800">Notice:</span> Display advertising slots across ERE high-traffic surfaces are exclusive to active commercial members.
                                </div>
                            </template>

                            <template x-if="!isPlanFree()">
                                <div class="space-y-3">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                        @foreach($adPlacements->take(6) as $ad)
                                            <div
                                                @click="selectAd({{ \Illuminate\Support\Js::from($ad) }})"
                                                :class="selectedAdId == {{ $ad->id }} ? 'ring-2 ring-amber-600 border-amber-600 bg-amber-50/30' : 'border-slate-200 hover:border-slate-300'"
                                                class="rounded-xl border p-3 cursor-pointer transition flex flex-col justify-between"
                                            >
                                                <div>
                                                    <div class="flex items-center justify-between">
                                                        <span class="font-bold text-xs text-slate-900">{{ $ad->name_en }}</span>
                                                    </div>
                                                    <div class="text-[11px] text-slate-500 mt-0.5">{{ $ad->duration_days }} days &middot; {{ number_format($ad->impression_limit ?? 0) }} max views</div>
                                                </div>
                                                <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between">
                                                    <span class="font-extrabold text-slate-900 text-xs">+{{ number_format((float) $ad->price_etb, 0) }} ETB</span>
                                                    <span class="text-xs" :class="selectedAdId == {{ $ad->id }} ? 'text-amber-600 font-bold' : 'text-slate-400'">
                                                        <span x-text="selectedAdId == {{ $ad->id }} ? 'Added' : '+ Add'"></span>
                                                    </span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div x-show="selectedAdId" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">Ad Campaign Headline:</label>
                                            <input type="text" name="target_ad_title" x-model="targetAdTitle" placeholder="e.g. Luxury Bole Villas - 15% Off Off-Plan" class="w-full rounded-xl border-slate-300 text-xs py-2">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">Destination Web Link (URL):</label>
                                            <input type="url" name="target_ad_url" x-model="targetAdUrl" placeholder="https://..." class="w-full rounded-xl border-slate-300 text-xs py-2">
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Payment Method Selection -->
                        <div x-show="!isPlanFree()" class="space-y-3">
                            <h3 class="text-sm font-bold text-slate-900">Select Ethiopian Payment Gateway</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <label
                                    @click="paymentMethod = 'telebirr'"
                                    :class="paymentMethod === 'telebirr' ? 'ring-2 ring-emerald-600 border-emerald-600 bg-emerald-50/20' : 'border-slate-200'"
                                    class="border rounded-xl p-3 flex flex-col items-center justify-center text-center cursor-pointer transition hover:border-slate-300"
                                >
                                    <span class="text-2xl mb-1">📱</span>
                                    <span class="font-bold text-xs text-slate-900">Telebirr</span>
                                    <span class="text-[10px] text-slate-500">Ethio Telecom</span>
                                </label>

                                <label
                                    @click="paymentMethod = 'cbe_birr'"
                                    :class="paymentMethod === 'cbe_birr' ? 'ring-2 ring-purple-600 border-purple-600 bg-purple-50/20' : 'border-slate-200'"
                                    class="border rounded-xl p-3 flex flex-col items-center justify-center text-center cursor-pointer transition hover:border-slate-300"
                                >
                                    <span class="text-2xl mb-1">🏦</span>
                                    <span class="font-bold text-xs text-slate-900">CBE Birr</span>
                                    <span class="text-[10px] text-slate-500">Commercial Bank</span>
                                </label>

                                <label
                                    @click="paymentMethod = 'chapa'"
                                    :class="paymentMethod === 'chapa' ? 'ring-2 ring-blue-600 border-blue-600 bg-blue-50/20' : 'border-slate-200'"
                                    class="border rounded-xl p-3 flex flex-col items-center justify-center text-center cursor-pointer transition hover:border-slate-300"
                                >
                                    <span class="text-2xl mb-1">💳</span>
                                    <span class="font-bold text-xs text-slate-900">Chapa</span>
                                    <span class="text-[10px] text-slate-500">Cards &amp; Amole</span>
                                </label>

                                <label
                                    @click="paymentMethod = 'bank_transfer'"
                                    :class="paymentMethod === 'bank_transfer' ? 'ring-2 ring-amber-600 border-amber-600 bg-amber-50/20' : 'border-slate-200'"
                                    class="border rounded-xl p-3 flex flex-col items-center justify-center text-center cursor-pointer transition hover:border-slate-300"
                                >
                                    <span class="text-2xl mb-1">🧾</span>
                                    <span class="font-bold text-xs text-slate-900">Bank Transfer</span>
                                    <span class="text-[10px] text-slate-500">CBE / Awash / Dashen</span>
                                </label>
                            </div>
                        </div>

                    </div>

                    <!-- Right: Live Pricing Ledger & Checkout CTA (4 cols) -->
                    <div class="lg:col-span-4 bg-slate-50 rounded-2xl border border-slate-200 p-5 space-y-4 flex flex-col justify-between">
                        <div class="space-y-4">
                            <h3 class="font-bold text-slate-900 text-sm pb-2 border-b border-slate-200">
                                Order Summary &middot; የክፍያ ማጠቃለያ
                            </h3>

                            <div class="space-y-2 text-xs">
                                <div class="flex justify-between text-slate-600">
                                    <span x-text="selectedPlanName + ' Plan'"></span>
                                    <span class="font-mono font-medium" x-text="isPlanFree() ? '0.00 ETB' : formatMoney(selectedPlanPrice) + ' ETB'"></span>
                                </div>

                                <div x-show="selectedPromoPrice > 0" class="flex justify-between text-slate-600">
                                    <span>Promotion: <span x-text="selectedPromoTitle"></span></span>
                                    <span class="font-mono font-medium" x-text="formatMoney(selectedPromoPrice) + ' ETB'"></span>
                                </div>

                                <div x-show="selectedAdPrice > 0" class="flex justify-between text-slate-600">
                                    <span>Ad: <span x-text="selectedAdTitle"></span></span>
                                    <span class="font-mono font-medium" x-text="formatMoney(selectedAdPrice) + ' ETB'"></span>
                                </div>

                                <div class="pt-2 border-t border-slate-200 flex justify-between text-slate-700">
                                    <span>Subtotal (Excl. Tax)</span>
                                    <span class="font-mono font-bold" x-text="formatMoney(subtotal()) + ' ETB'"></span>
                                </div>

                                <div class="flex justify-between text-slate-500">
                                    <span>Ethiopian VAT (15%)</span>
                                    <span class="font-mono" x-text="formatMoney(vatAmount()) + ' ETB'"></span>
                                </div>

                                <div class="pt-3 border-t-2 border-slate-300 flex items-baseline justify-between">
                                    <span class="font-extrabold text-slate-900 text-sm">Grand Total (ጠቅላላ ድምር)</span>
                                    <span class="text-xl font-black text-amber-900 font-mono" x-text="formatMoney(grandTotal()) + ' ETB'"></span>
                                </div>
                            </div>

                            <div class="p-3 rounded-xl bg-slate-100 text-[11px] text-slate-500 space-y-1">
                                <div class="font-semibold text-slate-700">Financial Ledger Assurance (Module 32)</div>
                                <div>Automatic double-entry posting to account <code>4020 Listing &amp; Subscription Revenue</code> upon payment verification.</div>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-200">
                            @guest
                                <a
                                    href="{{ route('login') }}"
                                    class="w-full block text-center py-3 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs tracking-wide transition shadow-sm"
                                >
                                    Sign In to Complete Subscription &rarr;
                                </a>
                            @else
                                <button
                                    type="submit"
                                    class="w-full py-3 px-4 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs tracking-wide transition shadow-md flex items-center justify-center gap-2"
                                >
                                    <span x-show="isPlanFree()">Activate Standard Free Plan Now</span>
                                    <span x-show="!isPlanFree()">Proceed to Payment &middot; <span x-text="formatMoney(grandTotal()) + ' ETB'"></span></span>
                                </button>
                            @endguest
                            <div class="text-[10px] text-slate-400 text-center mt-2">
                                By confirming, you agree to ERE's Subscription &amp; Advertising Terms of Service.
                            </div>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>

