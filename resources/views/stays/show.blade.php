<x-app-layout>
    <div
        x-data="{
            checkIn: '2026-10-25',
            checkOut: '2026-10-30',
            adults: 2,
            children: 1,
            infants: 0,
            pets: 0,
            maxGuests: 6,
            nightlyRateEtb: 8500,
            cleaningFeeEtb: 2000,
            serviceFeePct: 5.5882, // 2,500 ETB on 44,500 ETB (matches SRS Section 50 example)
            taxPct: 15,
            instantBook: true,
            holdActive: false,
            holdSecondsRemaining: 900, // 15 minutes (SRS Section 193)

            get nights() {
                const start = new Date(this.checkIn);
                const end = new Date(this.checkOut);
                const diff = Math.round((end - start) / (1000 * 60 * 60 * 24));
                return diff > 0 ? diff : 1;
            },
            get accommodationSubtotal() {
                return this.nights * this.nightlyRateEtb;
            },
            get platformFee() {
                return 2500; // SRS Section 50 example
            },
            get taxAmount() {
                return Math.round((this.accommodationSubtotal + this.cleaningFeeEtb) * 0.15);
            },
            get totalEtb() {
                return this.accommodationSubtotal + this.cleaningFeeEtb + this.platformFee + this.taxAmount;
            },
            startBookingHold() {
                this.holdActive = true;
                const timer = setInterval(() => {
                    if (this.holdSecondsRemaining > 0) this.holdSecondsRemaining--;
                    else { clearInterval(timer); this.holdActive = false; }
                }, 1000);
            }
        }"
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
    >
        <!-- Stay Header -->
        <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full bg-teal-50 text-teal-800 text-xs font-bold">
                        STAY • Serviced Villa
                    </span>
                    <span class="font-mono text-xs text-slate-500">ETH-AA-BOLE-RES-00001245</span>
                    <x-verification.badge :level="3" />
                </div>
                <h1 class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900">
                    Modern Garden Villa with Backup Generator — Bole Medhanialem
                </h1>
                <p class="text-sm text-slate-600 mt-1">
                    ★ 4.92 (38 two-way reviews) • Superhost • Bole, Addis Ababa, Ethiopia
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Left 8 Cols: Bed Configuration, Amenities, House Rules & Cancellation Policy -->
            <div class="lg:col-span-8 space-y-8">
                <!-- SRS Section 43: Bed Configuration -->
                <section class="bg-white rounded-2xl border border-slate-200 p-6">
                    <h2 class="text-base font-bold text-slate-900 mb-4">Sleeping Arrangements (SRS Section 43)</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50">
                            <div class="text-lg">🛏️</div>
                            <div class="text-xs font-bold text-slate-900 mt-1">Bedroom 1</div>
                            <div class="text-xs text-slate-600">1 King Bed</div>
                        </div>
                        <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50">
                            <div class="text-lg">🛏️</div>
                            <div class="text-xs font-bold text-slate-900 mt-1">Bedroom 2</div>
                            <div class="text-xs text-slate-600">1 Queen Bed</div>
                        </div>
                        <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50">
                            <div class="text-lg">🛏️🛏️</div>
                            <div class="text-xs font-bold text-slate-900 mt-1">Bedroom 3</div>
                            <div class="text-xs text-slate-600">2 Single Beds</div>
                        </div>
                        <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50">
                            <div class="text-lg">🛋️</div>
                            <div class="text-xs font-bold text-slate-900 mt-1">Living Room</div>
                            <div class="text-xs text-slate-600">1 Sofa Bed</div>
                        </div>
                    </div>
                </section>

                <!-- SRS Section 60 & 53: House Rules & Cancellation Policy -->
                <section class="bg-white rounded-2xl border border-slate-200 p-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 mb-2">House Rules (SRS Section 60)</h3>
                        <ul class="space-y-1.5 text-xs text-slate-700">
                            <li>🕒 Check-in after <strong>14:00</strong> • Check-out before <strong>11:00</strong></li>
                            <li>🚭 No indoor smoking • 🎉 No unauthorized parties</li>
                            <li>🤫 Quiet hours: <strong>22:00 – 07:00</strong></li>
                            <li>👨‍👩‍👧 Children welcome • 🐾 Pets: Ask host</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 mb-2">Cancellation Policy (SRS Section 53)</h3>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            <strong>Flexible Policy:</strong> 100% accommodation + cleaning fee refund if cancelled at least 24 hours before check-in. Digital House Manual & Wi-Fi credentials unlock automatically upon confirmation.
                        </p>
                    </div>
                </section>
            </div>

            <!-- Right 4 Cols: Sticky Short-Stay Price Calculation & Booking Hold Widget (SRS Sections 49 & 50) -->
            <div class="lg:col-span-4">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-lg p-6 sticky top-24 space-y-5">
                    <div class="flex items-baseline justify-between">
                        <div>
                            <span class="text-2xl font-extrabold text-slate-900">ETB <span x-text="nightlyRateEtb.toLocaleString()"></span></span>
                            <span class="text-xs text-slate-500"> / night</span>
                        </div>
                        <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800 text-xs font-bold">
                            ⚡ Instant Book
                        </span>
                    </div>

                    <!-- Check-in / Check-out Date Inputs -->
                    <div class="grid grid-cols-2 rounded-xl border border-slate-300 overflow-hidden">
                        <div class="p-2.5 border-r border-slate-300">
                            <label class="block text-[10px] font-bold uppercase text-slate-500">Check-In</label>
                            <input type="date" x-model="checkIn" class="w-full border-0 p-0 text-xs font-semibold focus:ring-0">
                        </div>
                        <div class="p-2.5">
                            <label class="block text-[10px] font-bold uppercase text-slate-500">Check-Out</label>
                            <input type="date" x-model="checkOut" class="w-full border-0 p-0 text-xs font-semibold focus:ring-0">
                        </div>
                    </div>

                    <!-- SRS Section 42: Guest Capacity Selector -->
                    <div class="p-3 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-between text-xs">
                        <span>Guests (Max <strong x-text="maxGuests"></strong>)</span>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="if(adults > 1) adults--" class="w-6 h-6 rounded bg-white border border-slate-300 font-bold">-</button>
                            <strong x-text="(adults + children) + ' Guests'"></strong>
                            <button type="button" @click="if((adults + children) < maxGuests) adults++" class="w-6 h-6 rounded bg-white border border-slate-300 font-bold">+</button>
                        </div>
                    </div>

                    <!-- SRS Section 50: Itemized Short-Stay Price Calculation -->
                    <div class="space-y-2.5 pt-2 border-t border-slate-200 text-xs text-slate-700">
                        <div class="flex items-center justify-between">
                            <span><span x-text="nights"></span> nights × ETB <span x-text="nightlyRateEtb.toLocaleString()"></span></span>
                            <span class="font-semibold">ETB <span x-text="accommodationSubtotal.toLocaleString()"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Cleaning fee</span>
                            <span class="font-semibold">ETB <span x-text="cleaningFeeEtb.toLocaleString()"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Platform service fee</span>
                            <span class="font-semibold">ETB <span x-text="platformFee.toLocaleString()"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Applicable tax (15%)</span>
                            <span class="font-semibold">ETB <span x-text="taxAmount.toLocaleString()"></span></span>
                        </div>
                        <div class="pt-3 border-t border-slate-200 flex items-center justify-between text-sm font-extrabold text-slate-900">
                            <span>Total (ETB)</span>
                            <span>ETB <span x-text="totalEtb.toLocaleString()"></span></span>
                        </div>
                    </div>

                    <!-- SRS Section 49 & 193: 15-Minute Temporary Booking Hold Banner -->
                    <div x-show="holdActive" x-cloak class="p-3 rounded-xl bg-amber-50 border border-amber-300 text-xs text-amber-950">
                        ⏳ <strong>Inventory Held:</strong> Dates locked for
                        <strong x-text="Math.floor(holdSecondsRemaining / 60) + ':' + String(holdSecondsRemaining % 60).padStart(2, '0')"></strong>
                        while you complete Telebirr / CBE Birr / Bank payment.
                    </div>

                    <button
                        type="button"
                        @click="startBookingHold()"
                        class="w-full py-3 px-4 rounded-xl bg-teal-700 hover:bg-teal-800 text-white font-bold text-sm shadow-sm transition"
                    >
                        <span x-text="holdActive ? 'Proceed to Payment →' : 'Instant Book & Hold Dates'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
