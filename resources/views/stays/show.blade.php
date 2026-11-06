<x-app-layout>
    <div
        x-data="{
            // Stay Baseline Data
            stayId: {{ $stay['id'] ?? 1 }},
            slug: '{{ $stay['slug'] ?? 'modern-garden-villa-bole' }}',
            nightlyRateEtb: {{ $stay['base_nightly_rate_etb'] ?? 8500 }},
            cleaningFeeEtb: {{ $stay['cleaning_fee_etb'] ?? 2000 }},
            serviceFeePct: {{ $stay['guest_service_fee_pct'] ?? 5.618 }},
            taxPct: {{ $stay['tourism_tax_pct'] ?? 15 }},
            maxGuests: {{ $stay['max_guests'] ?? 6 }},

            // Booking Widget State
            checkIn: '2026-10-25',
            checkOut: '2026-10-30',
            adults: 2,
            children: 1,
            infants: 0,
            pets: 0,

            // Quote & Dynamic Pricing State (Live Backend Action)
            loadingQuote: false,
            quoteError: null,
            quoteData: null,

            // 15-Minute Booking Hold State (SRS Section 49 & 193)
            holdLoading: false,
            holdActive: false,
            bookingCode: null,
            idempotencyKey: null,
            holdSecondsRemaining: 900,
            holdInterval: null,

            // Cancellation Refund Simulator State (Live Backend Action)
            refundPolicy: 'flexible',
            cancellationTiming: '48h_before',
            loadingRefund: false,
            refundResult: {
                policy: 'flexible',
                hours_until_checkin: 48,
                accommodation_refund_pct: 100,
                cleaning_fee_refund_etb: 2000,
                accommodation_refund_etb: 42500,
                total_guest_refund_etb: 44500,
                host_retained_etb: 0
            },

            // Unit and Calendar State (SRS Module 14)
            unitId: {{ $stay['unit_id'] ?? 1 }},
            calendarLoading: false,
            calendarMonth: {{ (int) date('n') }},
            calendarYear: {{ (int) date('Y') }},
            calendarDays: [],
            calendarHeading: '',
            calendarMonthName: '',
            get firstDayOffset() {
                return new Date(this.calendarYear, this.calendarMonth - 1, 1).getDay();
            },

            // Digital House Manual Unlock State (SRS Section 61)
            manualUnlocked: {{ ($stay['house_manual']['is_unlocked'] ?? false) ? 'true' : 'false' }},

            // Fallback client calculations
            get nights() {
                if (this.quoteData && this.quoteData.nights_count) {
                    return this.quoteData.nights_count;
                }
                const start = new Date(this.checkIn);
                const end = new Date(this.checkOut);
                const diff = Math.round((end - start) / (1000 * 60 * 60 * 24));
                return diff > 0 ? diff : 1;
            },
            get accommodationSubtotal() {
                if (this.quoteData) {
                    return this.quoteData.accommodation_net_subtotal_etb;
                }
                return this.nights * this.nightlyRateEtb;
            },
            get platformFee() {
                if (this.quoteData) {
                    return this.quoteData.platform_fee_etb;
                }
                return Math.round((this.accommodationSubtotal + this.cleaningFeeEtb) * (this.serviceFeePct / 100));
            },
            get taxAmount() {
                if (this.quoteData) {
                    return this.quoteData.tax_amount_etb;
                }
                return Math.round((this.accommodationSubtotal + this.cleaningFeeEtb + this.platformFee) * (this.taxPct / 100));
            },
            get totalEtb() {
                if (this.quoteData) {
                    return this.quoteData.total_amount_etb;
                }
                return this.accommodationSubtotal + this.cleaningFeeEtb + this.platformFee + this.taxAmount;
            },

            // Live Backend Action: ResolveDynamicStayQuoteAction
            async refreshQuote() {
                if (!this.checkIn || !this.checkOut || this.checkOut <= this.checkIn) {
                    return;
                }
                this.loadingQuote = true;
                this.quoteError = null;
                try {
                    const token = document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content');
                    const res = await fetch('/api/stays/quote', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            check_in_date: this.checkIn,
                            check_out_date: this.checkOut
                        })
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        this.quoteData = data.quote;
                        this.triggerRefundSimulation();
                    } else {
                        this.quoteError = data.message || 'Unable to calculate quote for selected dates.';
                    }
                } catch (e) {
                    console.warn('Backend quote API unreachable, using local reactive calculation.', e);
                } finally {
                    this.loadingQuote = false;
                }
            },

            // Live Backend Action: CalculateCancellationRefundAction
            async triggerRefundSimulation() {
                this.loadingRefund = true;
                try {
                    const token = document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content');
                    let cancelledAt = null;
                    const checkInDate = new Date(this.checkIn + 'T14:00:00');

                    if (this.cancellationTiming === '14d_before') {
                        cancelledAt = new Date(checkInDate.getTime() - 14 * 24 * 3600 * 1000).toISOString();
                    } else if (this.cancellationTiming === '5d_before') {
                        cancelledAt = new Date(checkInDate.getTime() - 5 * 24 * 3600 * 1000).toISOString();
                    } else if (this.cancellationTiming === '48h_before') {
                        cancelledAt = new Date(checkInDate.getTime() - 48 * 3600 * 1000).toISOString();
                    } else if (this.cancellationTiming === '12h_before') {
                        cancelledAt = new Date(checkInDate.getTime() - 12 * 3600 * 1000).toISOString();
                    } else if (this.cancellationTiming === 'after_checkin') {
                        cancelledAt = new Date(checkInDate.getTime() + 2 * 3600 * 1000).toISOString();
                    }

                    const res = await fetch('/api/stays/refund-preview', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            policy: this.refundPolicy,
                            check_in_date: this.checkIn,
                            accommodation_net_subtotal_etb: this.accommodationSubtotal,
                            cleaning_fee_etb: this.cleaningFeeEtb,
                            cancelled_at: cancelledAt
                        })
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        this.refundResult = data.refund;
                    }
                } catch (e) {
                    console.error('Refund calculation error:', e);
                } finally {
                    this.loadingRefund = false;
                }
            },

            // Live Backend Action: Create 15-Minute Booking Hold (SRS Sections 49 & 193)
            async startBookingHold() {
                this.holdLoading = true;
                try {
                    const token = document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content');
                    const res = await fetch('/api/stays/hold', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            check_in_date: this.checkIn,
                            check_out_date: this.checkOut,
                            adults: this.adults,
                            children: this.children
                        })
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        this.holdActive = true;
                        this.bookingCode = data.booking_code;
                        this.idempotencyKey = data.idempotency_key;
                        this.holdSecondsRemaining = data.hold_seconds_remaining || 900;

                        if (this.holdInterval) clearInterval(this.holdInterval);
                        this.holdInterval = setInterval(() => {
                            if (this.holdSecondsRemaining > 0) {
                                this.holdSecondsRemaining--;
                            } else {
                                clearInterval(this.holdInterval);
                                this.holdActive = false;
                            }
                        }, 1000);
                    }
                } catch (e) {
                    console.error('Failed to create booking hold:', e);
                } finally {
                    this.holdLoading = false;
                }
            },

            // Dynamic Availability Calendar API (Module 14)
            async fetchCalendarDays(year, month) {
                this.calendarLoading = true;
                try {
                    const res = await fetch(`/api/stays/${this.slug}/calendar?year=${year}&month=${month}&unit_id=${this.unitId}`);
                    const data = await res.json();
                    if (data.success && data.days) {
                        this.calendarDays = data.days;
                        this.calendarMonth = data.month;
                        this.calendarYear = data.year;
                        this.calendarHeading = data.calendar_heading || data.month_name;
                        this.calendarMonthName = data.calendar_heading || data.month_name;
                    }
                } catch (e) {
                    console.warn('Failed to load dynamic calendar days, generating fallback grid.', e);
                    this.generateFallbackDays(year, month);
                } finally {
                    this.calendarLoading = false;
                }
            },

            generateFallbackDays(year, month) {
                const daysInMonth = new Date(year, month, 0).getDate();
                const days = [];
                const today = new Date().toISOString().split('T')[0];
                for (let d = 1; d <= daysInMonth; d++) {
                    const mm = String(month).padStart(2, '0');
                    const dd = String(d).padStart(2, '0');
                    const dateStr = `${year}-${mm}-${dd}`;
                    days.push({
                        date: dateStr,
                        day: d,
                        status: 'available',
                        price_etb: this.nightlyRateEtb,
                        min_stay: 2,
                        is_past: dateStr < today
                    });
                }
                this.calendarDays = days;
                const d = new Date(year, month - 1, 1);
                this.calendarMonthName = d.toLocaleString('en-US', { month: 'long', year: 'numeric' });
            },

            prevMonth() {
                if (this.calendarMonth === 1) {
                    this.calendarMonth = 12;
                    this.calendarYear--;
                } else {
                    this.calendarMonth--;
                }
                this.fetchCalendarDays(this.calendarYear, this.calendarMonth);
            },

            nextMonth() {
                if (this.calendarMonth === 12) {
                    this.calendarMonth = 1;
                    this.calendarYear++;
                } else {
                    this.calendarMonth++;
                }
                this.fetchCalendarDays(this.calendarYear, this.calendarMonth);
            },

            selectDate(dateStr, status) {
                if (status !== 'available') return;
                if (!this.checkIn || (this.checkIn && this.checkOut)) {
                    this.checkIn = dateStr;
                    this.checkOut = '';
                } else if (this.checkIn && !this.checkOut) {
                    if (dateStr > this.checkIn) {
                        this.checkOut = dateStr;
                        this.refreshQuote();
                    } else {
                        this.checkIn = dateStr;
                        this.checkOut = '';
                    }
                }
            },

            getDateSelectionState(dateStr) {
                if (this.checkIn === dateStr) return 'start';
                if (this.checkOut === dateStr) return 'end';
                if (this.checkIn && this.checkOut && dateStr > this.checkIn && dateStr < this.checkOut) return 'between';
                return '';
            },

            formatFriendlyDate(dateStr) {
                if (!dateStr) return 'Select date';
                const parts = dateStr.split('-');
                if (parts.length !== 3) return dateStr;
                const d = new Date(parts[0], parts[1] - 1, parts[2]);
                return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            },

            init() {
                this.fetchCalendarDays(this.calendarYear, this.calendarMonth);
                this.refreshQuote();
            }
        }"
        x-init="init()"
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
    >
        <!-- Stay Header & Ethiopian Platform Verification Badges -->
        <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
            <div class="space-y-1.5">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full bg-teal-50 text-teal-800 text-xs font-bold border border-teal-200">
                        STAY • {{ $stay['property_type'] ?? 'Serviced Villa' }}
                    </span>
                    <span class="font-mono text-xs text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                        {{ $stay['property_code'] ?? 'ETH-AA-BOLE-RES-00001245' }}
                    </span>
                    <x-verification.badge :level="$stay['verification_level'] ?? 3" />
                    <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 text-[11px] font-semibold border border-emerald-200">
                        ⚡ 30kVA Diesel Generator & Backup Water
                    </span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                    {{ $stay['title_en'] ?? 'Modern Garden Villa with Backup Generator — Bole Medhanialem' }}
                </h1>
                <p class="text-sm text-slate-600 flex items-center gap-2">
                    <span class="text-amber-500 font-bold">★ {{ $stay['rating'] ?? '4.92' }}</span>
                    <span>({{ $stay['reviews_count'] ?? 38 }} two-way verified reviews)</span>
                    <span>•</span>
                    <span class="text-teal-700 font-semibold">Hosted by {{ $stay['host']['name'] ?? 'Sara Host' }}</span>
                    <span>•</span>
                    <span>{{ $stay['neighborhood'] ?? 'Bole Medhanialem, Addis Ababa' }}</span>
                </p>
            </div>

            <!-- iCal Feed & Action Utilities -->
            <div class="flex items-center gap-2">
                <a
                    href="{{ route('stays.ical', $stay['slug']) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-xs font-medium text-slate-700 shadow-xs transition"
                    title="Export RFC 5545 iCal Feed (SRS Section 57)"
                    download
                >
                    <span>📅</span>
                    <span>Export iCal (.ics)</span>
                </a>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold border border-blue-200">
                    PostgreSQL btree_gist Safe
                </span>
            </div>
        </div>

        <!-- Main Layout: Left 8 Cols (Details & Actions), Right 4 Cols (Live Price & Hold Widget) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <!-- LEFT COLUMN (8 Cols) -->
            <div class="lg:col-span-8 space-y-8">

                <!-- MODULE 14: NORMAL AVAILABILITY CALENDAR (Expandable in-page) -->
                <section class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-2 border-b border-slate-100">
                        <div>
                            <div class="flex items-center gap-1.5 text-teal-800 text-xs font-bold uppercase tracking-wider">
                                <span>📅</span>
                                <span>Availability Calendar &amp; Rates (SRS Module 14)</span>
                            </div>
                            <h2 class="text-base font-bold text-slate-900 mt-0.5">
                                Real-Time Availability &amp; Nightly Schedule
                            </h2>
                            <p class="text-xs text-slate-500">
                                Check-in inclusive, check-out exclusive. Click available dates to select your stay.
                            </p>
                        </div>

                        <!-- Calendar Header Controls: Navigation -->
                        <div class="flex items-center gap-2">
                            <div class="flex items-center rounded-xl border border-slate-200 bg-slate-50 p-1">
                                <button
                                    type="button"
                                    @click="prevMonth()"
                                    class="p-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-white transition shadow-xs cursor-pointer"
                                    aria-label="Previous Month"
                                    title="Previous Month"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                    </svg>
                                </button>
                                <span class="px-3 text-xs sm:text-sm font-extrabold text-slate-900 min-w-44 text-center tracking-tight" x-text="calendarHeading || calendarMonthName"></span>
                                <button
                                    type="button"
                                    @click="nextMonth()"
                                    class="p-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-white transition shadow-xs cursor-pointer"
                                    aria-label="Next Month"
                                    title="Next Month"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Days of Week Header -->
                    <div class="grid grid-cols-7 gap-1.5 text-center text-[11px] font-bold text-slate-400 uppercase py-1">
                        <div>Sun</div>
                        <div>Mon</div>
                        <div>Tue</div>
                        <div>Wed</div>
                        <div>Thu</div>
                        <div>Fri</div>
                        <div>Sat</div>
                    </div>

                    <!-- Dual Gregorian & Ethiopian Monthly Grid -->
                    <div class="grid grid-cols-7 gap-1.5 sm:gap-2">
                        <!-- Leading Offset Blanks -->
                        <template x-for="n in firstDayOffset" :key="'normal-offset-' + n">
                            <div class="min-h-[72px] sm:min-h-[78px] p-1.5 rounded-xl bg-slate-50/40 border border-transparent"></div>
                        </template>

                        <!-- Calendar Days: Gregorian Day (large left) + Ethiopian Day (small top-right corner) -->
                        <template x-for="day in calendarDays" :key="'normal-day-' + day.date">
                            <button
                                type="button"
                                @click="selectDate(day.date, day.status)"
                                :disabled="day.status !== 'available' || day.is_past"
                                :class="{
                                    'bg-teal-700 text-white shadow-xs font-bold ring-2 ring-teal-500': getDateSelectionState(day.date) === 'start' || getDateSelectionState(day.date) === 'end',
                                    'bg-teal-50 border-teal-300 text-teal-900 font-medium': getDateSelectionState(day.date) === 'between',
                                    'bg-white border-slate-200 hover:border-teal-400 text-slate-800 cursor-pointer hover:shadow-xs': day.status === 'available' && !getDateSelectionState(day.date) && !day.is_past,
                                    'bg-rose-50/50 border-rose-100 text-slate-300 line-through cursor-not-allowed': day.status === 'booked',
                                    'bg-amber-50/60 border-amber-200 text-amber-800 cursor-not-allowed': day.status === 'held_pending_payment',
                                    'bg-slate-100 border-slate-200 text-slate-400 cursor-not-allowed': day.status === 'blocked_by_host' || day.status === 'external_ical_blocked',
                                    'opacity-40 cursor-not-allowed': day.is_past
                                }"
                                class="min-h-[72px] sm:min-h-[78px] p-1.5 sm:p-2 rounded-xl border text-left flex flex-col justify-between transition relative text-xs group"
                                :title="day.date + ' (Eth: ' + (day.eth_day || '') + ' ' + (day.eth_month_name || '') + ') • ' + day.status + ' • ETB ' + (day.price_etb ? day.price_etb.toLocaleString() : '')"
                            >
                                <!-- Top Row: Prominent Gregorian Day + Top-Right Ethiopian Day -->
                                <div class="flex items-start justify-between w-full gap-1">
                                    <span class="text-sm sm:text-base font-extrabold leading-none" x-text="day.day"></span>
                                    <span
                                        class="text-[10px] sm:text-[11px] font-semibold leading-tight px-1 py-0.5 rounded"
                                        :class="getDateSelectionState(day.date) === 'start' || getDateSelectionState(day.date) === 'end' 
                                            ? 'bg-teal-800 text-amber-200' 
                                            : 'bg-amber-50 text-amber-800 border border-amber-200/80'"
                                        x-text="day.eth_day"
                                        :title="'Ethiopian: ' + day.eth_day + ' ' + (day.eth_month_name || '') + ' ' + (day.eth_year || '')"
                                    ></span>
                                </div>

                                <!-- Status & Price Row -->
                                <div class="mt-1 space-y-0.5">
                                    <div class="flex items-center justify-between">
                                        <template x-if="day.status === 'available' && !day.is_past">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        </template>
                                        <template x-if="day.status === 'booked'">
                                            <span class="text-[9px] text-rose-600 font-bold">Booked</span>
                                        </template>
                                        <template x-if="day.status === 'held_pending_payment'">
                                            <span class="text-[9px] text-amber-700 font-bold">Held</span>
                                        </template>
                                        <template x-if="day.status === 'blocked_by_host' || day.status === 'external_ical_blocked'">
                                            <span class="text-[9px] text-slate-500">Blocked</span>
                                        </template>
                                    </div>
                                    <div
                                        class="text-[10px] sm:text-[11px] font-medium truncate"
                                        :class="getDateSelectionState(day.date) === 'start' || getDateSelectionState(day.date) === 'end' ? 'text-teal-100 font-bold' : 'text-slate-600'"
                                    >
                                        <span x-text="'ETB ' + Math.round(day.price_etb / 1000) + 'k'"></span>
                                    </div>
                                </div>
                            </button>
                        </template>
                    </div>

                    <!-- Legend & Dual Calendar Key -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100 text-xs">
                        <div class="flex flex-wrap items-center gap-3 text-[11px] text-slate-600">
                            <span class="flex items-center gap-1">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>
                                <span>Available</span>
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 inline-block"></span>
                                <span>Booked</span>
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block"></span>
                                <span>Held (15m)</span>
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="w-2.5 h-2.5 rounded-full bg-slate-400 inline-block"></span>
                                <span>Blocked</span>
                            </span>
                        </div>

                        <!-- Calendar Key Explaining Dual Grid Numbers -->
                        <div class="inline-flex items-center gap-2 text-[11px] text-slate-500 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-200">
                            <span class="font-bold text-slate-700">Calendar Key:</span>
                            <span><strong>Large:</strong> Gregorian Day</span>
                            <span>•</span>
                            <span class="text-amber-800 font-semibold"><strong>Top-Right:</strong> Ethiopian Day (ቀን)</span>
                        </div>
                    </div>
                </section>

                <!-- SRS Section 43: Bed Configuration & Sleeping Arrangements -->
                <section class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Sleeping Arrangements (SRS Section 43)</h2>
                            <p class="text-xs text-slate-500">Configured unit layout with dedicated linens and wardrobe space</p>
                        </div>
                        <span class="text-xs text-slate-500 font-medium">Up to 6 Guests</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        @foreach($stay['beds'] ?? [] as $bed)
                            <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/80 hover:bg-slate-50 transition">
                                <div class="text-xl">{{ $bed['icon'] }}</div>
                                <div class="text-xs font-bold text-slate-900 mt-2">{{ $bed['room'] }}</div>
                                <div class="text-xs text-slate-600">{{ $bed['count'] }} {{ $bed['type'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <!-- SRS Section 51 & 52: Ethiopian Holiday & Peak Season Dynamic Pricing Calendar Rules -->
                <section class="bg-gradient-to-br from-amber-50/60 to-white rounded-2xl border border-amber-200 p-6">
                    <div class="flex items-start justify-between gap-4 mb-3">
                        <div>
                            <div class="flex items-center gap-1.5 text-amber-800 text-xs font-bold uppercase tracking-wider">
                                <span>🎉</span>
                                <span>Dynamic Pricing & Holiday Minimum Stay Rules (SRS §§51 & 52)</span>
                            </div>
                            <h3 class="text-base font-bold text-slate-900 mt-1">Ethiopian Holiday Peak Calendar Overrides</h3>
                            <p class="text-xs text-slate-600 mt-0.5">
                                Rates and minimum nights adjust automatically for national and religious festivals via <code class="font-mono text-teal-800">ResolveDynamicStayQuoteAction</code>.
                            </p>
                        </div>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded bg-amber-100 text-amber-900 border border-amber-300">
                            Automated Engine
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                        @foreach($stay['seasonal_rules'] ?? [] as $rule)
                            <div class="p-3 rounded-xl border border-amber-200 bg-white shadow-xs">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-xs text-slate-900">{{ $rule['name'] }}</span>
                                    @if(isset($rule['rate_multiplier']))
                                        <span class="text-xs font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">
                                            +{{ (int)(($rule['rate_multiplier'] - 1) * 100) }}% Rate
                                        </span>
                                    @elseif(isset($rule['nightly_rate_etb']))
                                        <span class="text-xs font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">
                                            ETB {{ number_format($rule['nightly_rate_etb']) }}/night
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-slate-500 mt-1">
                                    📅 <strong>{{ $rule['start_date'] }}</strong> to <strong>{{ $rule['end_date'] }}</strong>
                                </div>
                                <div class="text-[11px] text-slate-700 font-semibold mt-1">
                                    🔒 Requires min. <strong>{{ $rule['min_stay_nights'] }} nights</strong> (overriding standard 2 nights)
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Quick Holiday Date Tester Buttons -->
                    <div class="mt-4 pt-3 border-t border-amber-200 flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold text-slate-700">Test live backend reaction:</span>
                        <button
                            type="button"
                            @click="checkIn = '2027-01-18'; checkOut = '2027-01-21'; refreshQuote()"
                            class="text-xs px-2.5 py-1 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-900 font-medium transition cursor-pointer"
                        >
                            Select Timket (3 nights — triggers 5-night rule error)
                        </button>
                        <button
                            type="button"
                            @click="checkIn = '2027-01-17'; checkOut = '2027-01-22'; refreshQuote()"
                            class="text-xs px-2.5 py-1 rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-900 font-medium transition cursor-pointer"
                        >
                            Select Timket (5 nights — valid holiday rate)
                        </button>
                    </div>
                </section>

                <!-- LIVE BACKEND ACTION: Interactive Cancellation & Refund Simulator (SRS Section 53) -->
                <section class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-blue-50 text-blue-800 text-[11px] font-bold border border-blue-200">
                                <span>⚖️</span>
                                <span>Live Backend Action: CalculateCancellationRefundAction</span>
                            </div>
                            <h2 class="text-base font-bold text-slate-900 mt-1">
                                Cancellation Policy & Interactive Refund Simulator (SRS Section 53)
                            </h2>
                            <p class="text-xs text-slate-500">
                                Simulate exactly how much ETB the guest receives and how much the host retains under different policies and cancellation hours.
                            </p>
                        </div>
                    </div>

                    <!-- Simulator Controls -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-xl bg-slate-50 border border-slate-200">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Select Policy to Test</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    type="button"
                                    @click="refundPolicy = 'flexible'; triggerRefundSimulation()"
                                    :class="refundPolicy === 'flexible' ? 'bg-teal-700 text-white font-bold' : 'bg-white text-slate-700 border border-slate-300'"
                                    class="py-1.5 px-2 rounded-lg text-xs transition"
                                >
                                    Flexible
                                </button>
                                <button
                                    type="button"
                                    @click="refundPolicy = 'moderate'; triggerRefundSimulation()"
                                    :class="refundPolicy === 'moderate' ? 'bg-teal-700 text-white font-bold' : 'bg-white text-slate-700 border border-slate-300'"
                                    class="py-1.5 px-2 rounded-lg text-xs transition"
                                >
                                    Moderate
                                </button>
                                <button
                                    type="button"
                                    @click="refundPolicy = 'strict'; triggerRefundSimulation()"
                                    :class="refundPolicy === 'strict' ? 'bg-teal-700 text-white font-bold' : 'bg-white text-slate-700 border border-slate-300'"
                                    class="py-1.5 px-2 rounded-lg text-xs transition"
                                >
                                    Strict
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Simulate Cancellation Timing</label>
                            <select
                                x-model="cancellationTiming"
                                @change="triggerRefundSimulation()"
                                class="w-full text-xs font-semibold rounded-lg border-slate-300 bg-white py-1.5 px-2.5 focus:ring-teal-600 focus:border-teal-600"
                            >
                                <option value="14d_before">≥ 14 days before check-in (336+ hours)</option>
                                <option value="5d_before">5 days before check-in (120 hours)</option>
                                <option value="48h_before">48 hours before check-in</option>
                                <option value="12h_before">12 hours before check-in (&lt; 24h)</option>
                                <option value="after_checkin">After check-in time (No-show)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Live Calculated Refund Breakdown from Backend Action -->
                    <div class="p-4 rounded-xl border border-teal-200 bg-teal-50/60 space-y-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-teal-900">Calculated by Backend Action:</span>
                            <span class="text-slate-500 font-mono text-[11px]" x-text="'Hours to 14:00 check-in: ' + refundResult.hours_until_checkin + 'h'"></span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                            <div class="p-2.5 bg-white rounded-lg border border-teal-100 shadow-xs">
                                <div class="text-[10px] text-slate-500 uppercase font-bold">Accommodation Refund</div>
                                <div class="text-sm font-extrabold text-teal-700 mt-0.5" x-text="refundResult.accommodation_refund_pct + '%'"></div>
                                <div class="text-[11px] text-slate-600" x-text="'ETB ' + refundResult.accommodation_refund_etb.toLocaleString()"></div>
                            </div>
                            <div class="p-2.5 bg-white rounded-lg border border-teal-100 shadow-xs">
                                <div class="text-[10px] text-slate-500 uppercase font-bold">Cleaning Fee Refund</div>
                                <div class="text-sm font-extrabold text-emerald-700 mt-0.5">100%</div>
                                <div class="text-[11px] text-slate-600" x-text="'ETB ' + refundResult.cleaning_fee_refund_etb.toLocaleString()"></div>
                            </div>
                            <div class="p-2.5 bg-white rounded-lg border border-emerald-200 shadow-xs">
                                <div class="text-[10px] text-emerald-800 uppercase font-bold">Total Guest Refund</div>
                                <div class="text-sm font-extrabold text-emerald-700 mt-0.5" x-text="'ETB ' + refundResult.total_guest_refund_etb.toLocaleString()"></div>
                                <div class="text-[10px] text-emerald-600 font-medium">Credited to Telebirr/Bank</div>
                            </div>
                            <div class="p-2.5 bg-white rounded-lg border border-slate-200 shadow-xs">
                                <div class="text-[10px] text-slate-500 uppercase font-bold">Host Retained</div>
                                <div class="text-sm font-extrabold text-slate-900 mt-0.5" x-text="'ETB ' + refundResult.host_retained_etb.toLocaleString()"></div>
                                <div class="text-[10px] text-slate-500 font-medium">Payout via Schedule</div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- SRS Section 58 & 61: Digital House Manual & Access Credentials (Unlocked upon Confirmation) -->
                <section class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Digital House Manual & Key Access (SRS Section 61)</h2>
                            <p class="text-xs text-slate-500">Wi-Fi passwords and smart lock credentials unlock automatically for confirmed reservations</p>
                        </div>
                        <button
                            type="button"
                            @click="manualUnlocked = !manualUnlocked"
                            class="px-3 py-1.5 rounded-lg border text-xs font-bold transition cursor-pointer"
                            :class="manualUnlocked ? 'bg-emerald-600 text-white border-emerald-700' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-300'"
                        >
                            <span x-text="manualUnlocked ? '🔒 Re-lock Manual' : '🔓 Demo Confirmed Booking Unlock'"></span>
                        </button>
                    </div>

                    <!-- Locked State -->
                    <div x-show="!manualUnlocked" class="p-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 text-center">
                        <div class="text-2xl mb-1">🔐</div>
                        <h4 class="text-xs font-bold text-slate-800">Credentials Protected by Host Vault</h4>
                        <p class="text-xs text-slate-500 max-w-md mx-auto mt-1">
                            Exact Wi-Fi passwords, front gate keypad PINs, and emergency generator instructions become visible immediately after your booking hold is confirmed. Click the <strong>Demo Confirmed Booking Unlock</strong> button above to test the view.
                        </p>
                    </div>

                    <!-- Unlocked State -->
                    <div x-show="manualUnlocked" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-xl border border-emerald-300 bg-emerald-50/50">
                        <div class="p-3 bg-white rounded-lg border border-emerald-200">
                            <span class="text-[10px] font-bold uppercase text-slate-500">High-Speed Wi-Fi</span>
                            <div class="text-xs font-bold text-slate-900 mt-1">{{ $stay['house_manual']['wifi_ssid'] }}</div>
                            <div class="flex items-center justify-between mt-1 pt-1 border-t border-slate-100">
                                <span class="text-xs text-slate-600 font-mono">{{ $stay['house_manual']['wifi_password'] }}</span>
                                <span class="text-[10px] text-teal-700 font-bold">Copy</span>
                            </div>
                        </div>

                        <div class="p-3 bg-white rounded-lg border border-emerald-200">
                            <span class="text-[10px] font-bold uppercase text-slate-500">Access & Check-In</span>
                            <div class="text-xs font-bold text-slate-900 mt-1">{{ ucfirst(str_replace('_', ' ', $stay['house_manual']['check_in_method'] ?? 'Self Check-in')) }}</div>
                            <div class="text-xs text-emerald-700 font-mono font-bold mt-1">
                                {{ $stay['house_manual']['access_instructions'] ?? $stay['house_manual']['keypad_code'] ?? 'Access credentials provided on booking' }}
                            </div>
                        </div>

                        <div class="p-3 bg-white rounded-lg border border-emerald-200 sm:col-span-2">
                            <span class="text-[10px] font-bold uppercase text-slate-500">Backup Generator & Water Supply</span>
                            <p class="text-xs text-slate-700 mt-1 leading-relaxed">
                                {{ $stay['house_manual']['generator_guide'] ?? 'Automatic backup utilities on premises.' }} {{ $stay['house_manual']['parking'] ?? '' }}
                            </p>
                            <div class="text-[11px] text-slate-500 font-medium mt-1">
                                Host Emergency Line: <strong>{{ $stay['house_manual']['emergency_contact'] ?? $stay['house_manual']['emergency_phone'] ?? '+251 91 123 4567' }}</strong>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- SRS Section 62: Host Local Guide -->
                <section class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
                    <h2 class="text-base font-bold text-slate-900 mb-1">Host Local Guide (SRS Section 62)</h2>
                    <p class="text-xs text-slate-500 mb-4">Alemayehu's curated recommendations around Bole Medhanialem</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($stay['local_guide'] ?? [] as $place)
                            <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 transition">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                                        <span>{{ $place['icon'] }}</span>
                                        <span>{{ $place['name'] }}</span>
                                    </span>
                                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-slate-200 text-slate-700">
                                        {{ $place['distance'] }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-slate-500 mt-1 font-medium">{{ $place['category'] }}</div>
                                <p class="text-xs text-slate-600 mt-1.5 italic">
                                    "{{ $place['tip'] }}"
                                </p>
                            </div>
                        @endforeach
                    </div>
                </section>

                <!-- SRS Section 60: House Rules -->
                <section class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
                    <h3 class="text-sm font-bold text-slate-900 mb-3">House Rules (SRS Section 60)</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-slate-700">
                        <div class="flex items-center gap-2">
                            <span>🕒</span>
                            <span>Check-in after <strong>{{ $stay['house_rules']['check_in'] }}</strong> • Check-out before <strong>{{ $stay['house_rules']['check_out'] }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span>🤫</span>
                            <span>Quiet hours: <strong>{{ $stay['house_rules']['quiet_hours'] }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span>🚭</span>
                            <span>No indoor smoking (patio smoking allowed)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span>👨‍👩‍👧</span>
                            <span>Children of all ages welcome</span>
                        </div>
                    </div>
                </section>
            </div>

            <!-- RIGHT COLUMN (4 Cols): Sticky Dynamic Price & Concurrency-Safe Hold Engine -->
            <div class="lg:col-span-4">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-lg p-6 sticky top-24 space-y-5">

                    <!-- Rate & Instant Book Status -->
                    <div class="flex items-baseline justify-between">
                        <div>
                            <span class="text-2xl font-extrabold text-slate-900">
                                <span x-text="'ETB ' + nightlyRateEtb.toLocaleString()">ETB {{ number_format($stay['base_nightly_rate_etb'] ?? 8500) }}</span>
                            </span>
                            <span class="text-xs text-slate-500"> / night</span>
                        </div>
                        <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800 text-xs font-bold border border-emerald-200">
                            ⚡ Instant Book
                        </span>
                    </div>

                    <!-- Check-in / Check-out Date Inputs (triggers Live Backend Quote) -->
                    <div class="grid grid-cols-2 rounded-xl border border-slate-300 overflow-hidden">
                        <div class="p-2.5 border-r border-slate-300 bg-slate-50/50">
                            <label class="block text-[10px] font-bold uppercase text-slate-500">Check-In</label>
                            <input
                                type="date"
                                x-model="checkIn"
                                @change="refreshQuote()"
                                class="w-full border-0 p-0 text-xs font-semibold focus:ring-0 bg-transparent text-slate-900"
                            >
                        </div>
                        <div class="p-2.5 bg-slate-50/50">
                            <label class="block text-[10px] font-bold uppercase text-slate-500">Check-Out</label>
                            <input
                                type="date"
                                x-model="checkOut"
                                @change="refreshQuote()"
                                class="w-full border-0 p-0 text-xs font-semibold focus:ring-0 bg-transparent text-slate-900"
                            >
                        </div>
                    </div>

                    <!-- Validation Error from Backend (e.g. Holiday Min Stay rule) -->
                    <div x-show="quoteError" x-cloak class="p-3 rounded-xl bg-red-50 border border-red-300 text-xs text-red-900 leading-snug">
                        ⚠️ <strong x-text="quoteError"></strong>
                    </div>

                    <!-- Guest Capacity Selector (SRS Section 42) -->
                    <div class="p-3 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-between text-xs">
                        <div>
                            <span class="font-bold text-slate-800">Guests</span>
                            <span class="text-[11px] text-slate-500 block">Max <span x-text="maxGuests"></span> guests</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="if(adults > 1) { adults--; refreshQuote(); }"
                                class="w-7 h-7 rounded-lg bg-white border border-slate-300 text-slate-700 font-bold hover:bg-slate-100 flex items-center justify-center transition"
                            >-</button>
                            <strong class="w-16 text-center text-xs" x-text="(adults + children) + ' Guests'"></strong>
                            <button
                                type="button"
                                @click="if((adults + children) < maxGuests) { adults++; refreshQuote(); }"
                                class="w-7 h-7 rounded-lg bg-white border border-slate-300 text-slate-700 font-bold hover:bg-slate-100 flex items-center justify-center transition"
                            >+</button>
                        </div>
                    </div>

                    <!-- Itemized Price Calculation in ETB (SRS Section 50) -->
                    <div class="space-y-2.5 pt-2 border-t border-slate-200 text-xs text-slate-700">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600">
                                <span x-text="nights"></span> nights × ETB <span x-text="nightlyRateEtb.toLocaleString()"></span>
                            </span>
                            <span class="font-semibold text-slate-900">
                                ETB <span x-text="accommodationSubtotal.toLocaleString()"></span>
                            </span>
                        </div>

                        <!-- Long-stay Discount if applicable -->
                        <template x-if="quoteData && quoteData.discount_amount_etb > 0">
                            <div class="flex items-center justify-between text-emerald-700 font-medium">
                                <span x-text="quoteData.discount_type === 'monthly_discount' ? 'Monthly discount (28+ nights)' : 'Weekly discount (7+ nights)'"></span>
                                <span>- ETB <span x-text="quoteData.discount_amount_etb.toLocaleString()"></span></span>
                            </div>
                        </template>

                        <div class="flex items-center justify-between">
                            <span class="text-slate-600">Cleaning fee</span>
                            <span class="font-semibold text-slate-900">ETB <span x-text="cleaningFeeEtb.toLocaleString()"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600">Platform service fee</span>
                            <span class="font-semibold text-slate-900">ETB <span x-text="platformFee.toLocaleString()"></span></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600">Applicable tax (15%)</span>
                            <span class="font-semibold text-slate-900">ETB <span x-text="taxAmount.toLocaleString()"></span></span>
                        </div>
                        <div class="pt-3 border-t border-slate-200 flex items-center justify-between text-base font-extrabold text-slate-900">
                            <span>Total (ETB)</span>
                            <span class="text-teal-800">ETB <span x-text="totalEtb.toLocaleString()"></span></span>
                        </div>
                    </div>

                    <!-- SRS Section 49 & 193: 15-Minute Temporary Booking Hold Banner -->
                    <div x-show="holdActive" x-cloak class="p-3.5 rounded-xl bg-amber-50 border border-amber-300 text-xs text-amber-950 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="font-bold flex items-center gap-1.5 text-amber-900">
                                <span>⏳</span>
                                <span>Inventory Locked</span>
                            </span>
                            <span class="font-mono font-bold text-amber-800" x-text="bookingCode"></span>
                        </div>
                        <p class="leading-relaxed text-[11px] text-amber-900">
                            Held for <strong x-text="Math.floor(holdSecondsRemaining / 60) + ':' + String(holdSecondsRemaining % 60).padStart(2, '0')"></strong>.
                            Pay via <strong>Telebirr (492019)</strong> or <strong>CBE (1000293849102)</strong>.
                        </p>
                        <div class="text-[10px] text-amber-800 font-mono">
                            Idempotency: <span x-text="idempotencyKey ? idempotencyKey.substring(0, 16) + '...' : ''"></span>
                        </div>
                    </div>

                    <!-- Instant Book & Hold Button -->
                    <button
                        type="button"
                        @click="startBookingHold()"
                        :disabled="loadingQuote || !!quoteError"
                        class="w-full py-3 px-4 rounded-xl bg-teal-700 hover:bg-teal-800 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-bold text-sm shadow-sm transition flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <span x-text="holdActive ? '✓ Hold Active — Proceed to Payment' : (loadingQuote ? 'Calculating...' : 'Instant Book & Hold Dates')"></span>
                    </button>

                    <p class="text-[11px] text-center text-slate-500">
                        Protected by PostgreSQL <code class="font-mono text-teal-800">btree_gist</code> anti-double-booking constraint
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
