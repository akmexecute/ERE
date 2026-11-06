<x-app-layout>
    <div class="min-h-screen bg-[#faf9f5] py-10 px-4 sm:px-6 lg:px-8" x-data="{
        checkInModal: false,
        checkOutModal: false,
        claimModal: false,
        reviewModal: false,
        propertyCondition: 'good',
        conditionNotes: '',
        reviewScores: { cleanliness: 5, accuracy: 5, communication: 5, location: 5, value: 5 },
        reviewComment: '',
        privateNote: '',
        claimAmount: '',
        claimDescription: '',
        submitting: false
    }">
        <div class="max-w-4xl mx-auto space-y-8">

            <!-- Breadcrumb & Back -->
            <div class="flex items-center justify-between text-xs text-slate-500">
                <a href="{{ route('dashboard', ['tab' => 'stays']) }}" class="inline-flex items-center gap-1.5 font-semibold text-slate-700 hover:text-teal-900 transition">
                    <span>&larr;</span> Back to Dashboard
                </a>
                <span class="font-mono text-slate-400">Reservation: {{ $booking->booking_code }}</span>
            </div>

            <!-- Stay Header Card -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider
                        @if($booking->status->value === 'confirmed') bg-blue-50 text-blue-800 border border-blue-200
                        @elseif($booking->status->value === 'checked_in') bg-emerald-50 text-emerald-800 border border-emerald-200
                        @elseif($booking->status->value === 'checked_out') bg-slate-100 text-slate-800 border border-slate-300
                        @else bg-amber-50 text-amber-800 border border-amber-200 @endif
                    ">
                        Status: {{ ucfirst(str_replace('_', ' ', $booking->status->value)) }}
                    </span>

                    <span class="text-xs text-slate-500">
                        {{ $booking->nights_count }} Nights • {{ $booking->adults_count + $booking->children_count }} Guests
                    </span>
                </div>

                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                    {{ $booking->property?->title_en ?? 'Short Stay Accommodation' }}
                </h1>
                <p class="text-xs text-slate-500">
                    Unit: <strong class="text-slate-800">{{ $booking->unit?->unit_number_or_code ?? 'Entire Property' }}</strong> •
                    Host: <strong class="text-slate-800">{{ $booking->host?->display_name ?? 'Property Host' }}</strong> •
                    Check-in: <strong class="text-slate-800">{{ $booking->check_in_date->format('M d, Y') }}</strong> •
                    Checkout: <strong class="text-slate-800">{{ $booking->check_out_date->format('M d, Y') }}</strong>
                </p>

                <!-- Action Triggers -->
                <div class="flex flex-wrap items-center gap-3 pt-3 border-t border-slate-100">
                    @if($booking->status->value === 'confirmed' && ! $booking->checkIn()->exists())
                        <button type="button" @click="checkInModal = true" class="px-4 py-2 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-xs font-bold shadow-xs transition">
                            🔑 Record Check-In
                        </button>
                    @endif

                    @if($booking->status->value === 'checked_in' && ! $booking->checkOut()->exists())
                        <button type="button" @click="checkOutModal = true" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold shadow-xs transition">
                            🚪 Complete Checkout &amp; Condition Report
                        </button>
                    @endif

                    @if($isHost && $booking->checkOut()->exists() && $booking->checkOut->checked_out_at->diffInHours(now()) <= 48)
                        <button type="button" @click="claimModal = true" class="px-4 py-2 rounded-xl bg-amber-700 hover:bg-amber-800 text-white text-xs font-bold shadow-xs transition">
                            ⚠️ Submit Damage Claim (48h Window)
                        </button>
                    @endif

                    @if($booking->checkOut()->exists() && ! $booking->reviews()->where('reviewer_user_id', $user->id)->exists())
                        <button type="button" @click="reviewModal = true" class="px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition">
                            ⭐ Write Two-Way Review
                        </button>
                    @endif
                </div>
            </div>

            <!-- Wi-Fi, Door PIN & Secure Access Credentials -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-teal-800">Stay Access &amp; Credentials</span>
                        <h2 class="text-lg font-bold text-slate-900">Digital Access &amp; Wi-Fi Vault</h2>
                    </div>
                    @if($canViewSensitiveCredentials)
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                            ✓ Access Active
                        </span>
                    @else
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                            🔒 Access Locked
                        </span>
                    @endif
                </div>

                @if($canViewSensitiveCredentials)
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                        <!-- Wi-Fi -->
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-slate-500">High-Speed Wi-Fi</span>
                            <div class="text-xs font-semibold text-slate-900">SSID: {{ $houseManual?->wifi_network_name ?? 'ERE_STAY_FIBER' }}</div>
                            <div class="text-xs font-mono font-bold text-teal-800">Password: {{ $houseManual?->wifi_password ?? 'AddisStay2026!' }}</div>
                        </div>

                        <!-- Door PIN -->
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-slate-500">Smart Lock Door PIN</span>
                            <div class="text-xs font-mono font-extrabold text-slate-900 text-base">{{ $houseManual?->door_access_code ?? '7392#' }}</div>
                            <p class="text-[10px] text-slate-500">Enter digits followed by '#' on keypad</p>
                        </div>

                        <!-- QR Arrival Code -->
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                            <span class="text-[10px] uppercase font-bold text-slate-500">QR Check-In Token</span>
                            <div class="text-xs font-mono font-bold text-slate-800 truncate">QR-{{ substr($booking->uuid, 0, 8) }}</div>
                            <p class="text-[10px] text-slate-500">Scan at entrance or show to host</p>
                        </div>
                    </div>

                    <!-- Entry Instructions -->
                    <div class="p-4 rounded-xl bg-teal-50/60 border border-teal-200 text-xs text-teal-950 space-y-1">
                        <span class="font-bold">Entry &amp; Arrival Guidance:</span>
                        <p class="leading-relaxed">{{ $houseManual?->access_instructions_en ?? 'Use the private entrance at the front driveway. Enter the Smart Lock PIN at the security gate.' }}</p>
                    </div>
                @else
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 space-y-1">
                        <p class="font-bold text-slate-800">Credentials Protected</p>
                        <p class="leading-relaxed">
                            Under ERE security guidelines, Wi-Fi credentials and door access codes unlock exactly 24 hours prior to check-in for confirmed guests and revoke automatically upon checkout.
                        </p>
                    </div>
                @endif
            </div>

            <!-- Digital House Manual -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs space-y-4">
                <span class="text-xs font-semibold uppercase tracking-wider text-teal-800">House Manual &amp; Amenities</span>
                <h2 class="text-lg font-bold text-slate-900">Appliance &amp; Emergency Information</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                        <span class="font-bold text-slate-900">⚡ Backup Generator &amp; Water</span>
                        <p class="text-slate-600 leading-relaxed">{{ $houseManual?->appliance_and_ac_guide_en ?? 'The generator starts automatically within 45 seconds of a power disruption. Constant water pressure is maintained via the 5,000L rooftop reservoir.' }}</p>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                        <span class="font-bold text-slate-900">🚨 Emergency Contact</span>
                        <p class="text-slate-600 leading-relaxed">Property Host Phone: <strong class="text-slate-900">{{ $houseManual?->emergency_contact_phone ?? '+251 911 22 33 44' }}</strong></p>
                        <p class="text-[11px] text-slate-500">Local emergency police: 991 • Ambulance: 907</p>
                    </div>
                </div>
            </div>

            <!-- Security Deposit & Claims Status -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-teal-800">Financial Integrity</span>
                        <h2 class="text-lg font-bold text-slate-900">Security Deposit Escrow (ETB {{ number_format($booking->security_deposit_etb, 2) }})</h2>
                    </div>
                    @if($booking->securityDeposit)
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider
                            @if($booking->securityDeposit->status === 'held') bg-emerald-50 text-emerald-800 border border-emerald-200
                            @elseif($booking->securityDeposit->status === 'released') bg-blue-50 text-blue-800 border border-blue-200
                            @else bg-amber-50 text-amber-800 border border-amber-200 @endif
                        ">
                            Deposit: {{ ucfirst($booking->securityDeposit->status) }}
                        </span>
                    @endif
                </div>

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 space-y-2">
                    <p class="leading-relaxed">
                        Security deposits are safely held in escrow and released automatically 48 hours after checkout unless the host files an evidence-based damage claim. <strong>Missing checkout condition reports do not forfeit deposits.</strong>
                    </p>
                    @if($booking->securityDeposit && $booking->securityDeposit->damageClaims()->count() > 0)
                        <div class="pt-2 border-t border-slate-200 space-y-2">
                            <span class="font-bold text-slate-900">Filed Claims:</span>
                            @foreach($booking->securityDeposit->damageClaims as $claim)
                                <div class="p-3 bg-white rounded-xl border border-slate-200 flex items-center justify-between">
                                    <div>
                                        <span class="font-bold text-slate-900">{{ $claim->claim_code }}: ETB {{ number_format($claim->claimed_amount_etb, 2) }}</span>
                                        <p class="text-[11px] text-slate-500">{{ $claim->description }}</p>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-50 text-amber-800 border border-amber-200">
                                        {{ str_replace('_', ' ', $claim->status) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- MODALS -->

            <!-- 1. Check-In Modal -->
            <div x-show="checkInModal" x-cloak class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                <div @click.away="checkInModal = false" class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl border border-slate-200">
                    <h3 class="text-lg font-bold text-slate-900">Record Stay Check-In</h3>
                    <p class="text-xs text-slate-500">Confirm your arrival and unlock the digital house manual &amp; Wi-Fi credentials.</p>

                    <form action="{{ route('stays.check-in', $booking->uuid) }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-slate-800 mb-1">Check-In Method</label>
                            <select name="method" class="w-full rounded-xl border-slate-300 text-xs">
                                <option value="guest_self">Guest Self Check-In</option>
                                <option value="smart_lock_code">Smart Lock Door Code</option>
                                <option value="qr_code">QR Arrival Code</option>
                                <option value="host_manual">Host Greets in Person</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-800 mb-1">Access Code (Optional)</label>
                            <input type="text" name="access_code" placeholder="Enter keypad code used" class="w-full rounded-xl border-slate-300 text-xs font-mono">
                        </div>
                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" @click="checkInModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700">Cancel</button>
                            <button type="submit" class="px-5 py-2 rounded-xl bg-teal-800 text-white text-xs font-bold hover:bg-teal-900">Confirm Check-In</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 2. Check-Out Modal -->
            <div x-show="checkOutModal" x-cloak class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                <div @click.away="checkOutModal = false" class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl border border-slate-200">
                    <h3 class="text-lg font-bold text-slate-900">Complete Stay Checkout</h3>
                    <p class="text-xs text-slate-500">Report property condition and dispatch housekeeping.</p>

                    <form action="{{ route('stays.check-out', $booking->uuid) }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-slate-800 mb-1">Property Condition</label>
                            <select name="property_condition" x-model="propertyCondition" class="w-full rounded-xl border-slate-300 text-xs">
                                <option value="excellent">Excellent — Left spotless</option>
                                <option value="good">Good — Normal tidy turnover</option>
                                <option value="minor_issue">Minor Issue — Light stains or extra cleaning</option>
                                <option value="damage_reported">Damage Reported — Item broken or damaged</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-800 mb-1">Condition Notes</label>
                            <textarea name="condition_notes" x-model="conditionNotes" rows="3" placeholder="Describe any items of note or key placement..." class="w-full rounded-xl border-slate-300 text-xs"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" @click="checkOutModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700">Cancel</button>
                            <button type="submit" class="px-5 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-black">Complete Checkout</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 3. Damage Claim Modal (Host) -->
            <div x-show="claimModal" x-cloak class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                <div @click.away="claimModal = false" class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl border border-slate-200">
                    <h3 class="text-lg font-bold text-slate-900">Submit Damage Claim</h3>
                    <p class="text-xs text-slate-500">Claims must be submitted within 48 hours of checkout with itemized description and photo evidence.</p>

                    <form action="{{ route('stays.claims.store', $booking->uuid) }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-slate-800 mb-1">Claimed Amount (ETB) <span class="text-rose-500">*</span></label>
                            <input type="number" step="0.01" name="claimed_amount_etb" x-model="claimAmount" placeholder="e.g. 1500" required class="w-full rounded-xl border-slate-300 text-xs">
                            <p class="text-[10px] text-slate-500 mt-0.5">Maximum eligible: ETB {{ number_format($booking->security_deposit_etb, 2) }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-800 mb-1">Damage Description <span class="text-rose-500">*</span></label>
                            <textarea name="description" x-model="claimDescription" rows="3" placeholder="Provide full description of damage and repair estimates..." required class="w-full rounded-xl border-slate-300 text-xs"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" @click="claimModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700">Cancel</button>
                            <button type="submit" class="px-5 py-2 rounded-xl bg-amber-800 text-white text-xs font-bold hover:bg-amber-900">Submit Claim</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 4. Review Modal -->
            <div x-show="reviewModal" x-cloak class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                <div @click.away="reviewModal = false" class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl border border-slate-200">
                    <h3 class="text-lg font-bold text-slate-900">Write Two-Way Review</h3>
                    <p class="text-xs text-slate-500">Reviews remain double-blind until both parties submit or the 14-day window expires.</p>

                    <form action="{{ route('stays.reviews.store', $booking->uuid) }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="direction" value="{{ $isGuest ? 'guest_to_host' : 'host_to_guest' }}">

                        <div class="space-y-2">
                            @if($isGuest)
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <label class="block text-[10px] text-slate-500 font-bold">Cleanliness (1-5)</label>
                                        <input type="number" min="1" max="5" name="scores[cleanliness]" value="5" class="w-full rounded border-slate-300 p-1 text-xs">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-slate-500 font-bold">Accuracy (1-5)</label>
                                        <input type="number" min="1" max="5" name="scores[accuracy]" value="5" class="w-full rounded border-slate-300 p-1 text-xs">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-slate-500 font-bold">Communication (1-5)</label>
                                        <input type="number" min="1" max="5" name="scores[communication]" value="5" class="w-full rounded border-slate-300 p-1 text-xs">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-slate-500 font-bold">Location (1-5)</label>
                                        <input type="number" min="1" max="5" name="scores[location]" value="5" class="w-full rounded border-slate-300 p-1 text-xs">
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-[10px] text-slate-500 font-bold">Value (1-5)</label>
                                        <input type="number" min="1" max="5" name="scores[value]" value="5" class="w-full rounded border-slate-300 p-1 text-xs">
                                    </div>
                                </div>
                            @else
                                <div class="grid grid-cols-3 gap-2 text-xs">
                                    <div>
                                        <label class="block text-[10px] text-slate-500 font-bold">Communication (1-5)</label>
                                        <input type="number" min="1" max="5" name="scores[communication]" value="5" class="w-full rounded border-slate-300 p-1 text-xs">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-slate-500 font-bold">Property Care (1-5)</label>
                                        <input type="number" min="1" max="5" name="scores[property_care]" value="5" class="w-full rounded border-slate-300 p-1 text-xs">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-slate-500 font-bold">Rule Compliance (1-5)</label>
                                        <input type="number" min="1" max="5" name="scores[rule_compliance]" value="5" class="w-full rounded border-slate-300 p-1 text-xs">
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-800 mb-1">Public Review Comment</label>
                            <textarea name="public_comment" rows="3" placeholder="Share your experience..." required class="w-full rounded-xl border-slate-300 text-xs"></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-800 mb-1">Private Note (Optional)</label>
                            <textarea name="private_feedback_note" rows="2" placeholder="Private feedback only visible to the recipient..." class="w-full rounded-xl border-slate-300 text-xs"></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" @click="reviewModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700">Cancel</button>
                            <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-800 text-white text-xs font-bold hover:bg-emerald-900">Submit Review</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

