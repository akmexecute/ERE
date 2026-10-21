<x-app-layout>
    <div class="min-h-[80vh] flex items-center justify-center px-4 py-12">
        <div
            x-data="{
                step: 'phone',
                phone: '',
                name: '',
                selectedRole: 'buyer',
                otpDigits: ['', '', '', '', '', ''],
                countdown: 0,
                loading: false,
                errorMessage: '',
                debugCode: null,
                startCountdown() {
                    this.countdown = 60;
                    const timer = setInterval(() => {
                        if (this.countdown > 0) this.countdown--;
                        else clearInterval(timer);
                    }, 1000);
                },
                async requestOtp() {
                    this.errorMessage = '';
                    this.loading = true;
                    try {
                        const token = document.querySelector('meta[name=csrf-token]')?.getAttribute('content');
                        const res = await fetch('{{ url('/auth/otp/send') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token
                            },
                            body: JSON.stringify({ phone: this.phone, purpose: 'login' })
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.errorMessage = data.message || (data.errors?.phone ? data.errors.phone[0] : 'Failed to send OTP code.');
                            this.loading = false;
                            return;
                        }
                        if (data.debug_code) {
                            this.debugCode = data.debug_code;
                        }
                        this.step = 'otp';
                        this.startCountdown();
                        this.$nextTick(() => {
                            if (this.$refs.otp_0) this.$refs.otp_0.focus();
                        });
                    } catch (e) {
                        this.errorMessage = 'Network error while requesting verification code.';
                    } finally {
                        this.loading = false;
                    }
                },
                fillDebugCode() {
                    if (this.debugCode) {
                        const digits = this.debugCode.split('');
                        this.otpDigits = digits;
                    }
                },
                handleOtpInput(index, event) {
                    const val = event.target.value.replace(/\D/g, '');
                    this.otpDigits[index] = val.slice(-1);
                    if (val && index < 5) {
                        this.$refs['otp_' + (index + 1)].focus();
                    }
                },
                handleBackspace(index, event) {
                    if (!this.otpDigits[index] && index > 0) {
                        this.$refs['otp_' + (index - 1)].focus();
                    }
                }
            }"
            class="w-full max-w-md bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8"
        >
            <div class="mb-6">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-teal-50 text-teal-800">
                    Ethiopia Verified Identity
                </span>
                <h1 class="mt-2 text-2xl font-bold text-slate-900">Sign in or Create Account</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Use your Ethio Telecom (<code class="text-xs bg-slate-100 px-1 rounded">09...</code>) or Safaricom (<code class="text-xs bg-slate-100 px-1 rounded">07...</code>) number.
                </p>
            </div>

            <!-- Error banner -->
            <div x-show="errorMessage" x-cloak class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-xs font-medium text-red-800" x-text="errorMessage"></div>

            @if ($errors->any())
                <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-xs font-medium text-red-800">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Step 1: Role Selection & Phone Input -->
            <form x-show="step === 'phone'" @submit.prevent="requestOtp()" class="space-y-5">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">
                        I am joining primarily to:
                    </label>
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            type="button"
                            @click="selectedRole = 'buyer'"
                            :class="selectedRole === 'buyer' ? 'border-teal-600 bg-teal-50/70 text-teal-900 font-semibold shadow-xs' : 'border-slate-200 text-slate-600 hover:border-slate-300'"
                            class="border rounded-xl p-2.5 text-center text-xs transition"
                        >
                            🏠 Buy / Rent
                        </button>
                        <button
                            type="button"
                            @click="selectedRole = 'property_owner'"
                            :class="selectedRole === 'property_owner' ? 'border-teal-600 bg-teal-50/70 text-teal-900 font-semibold shadow-xs' : 'border-slate-200 text-slate-600 hover:border-slate-300'"
                            class="border rounded-xl p-2.5 text-center text-xs transition"
                        >
                            🔑 List Property
                        </button>
                        <button
                            type="button"
                            @click="selectedRole = 'agent'"
                            :class="selectedRole === 'agent' ? 'border-teal-600 bg-teal-50/70 text-teal-900 font-semibold shadow-xs' : 'border-slate-200 text-slate-600 hover:border-slate-300'"
                            class="border rounded-xl p-2.5 text-center text-xs transition"
                        >
                            💼 Agent / Firm
                        </button>
                    </div>
                </div>

                <div>
                    <label for="name_input" class="block text-sm font-medium text-slate-700 mb-1">Full Name (Optional)</label>
                    <input
                        id="name_input"
                        type="text"
                        x-model="name"
                        placeholder="e.g. Abebe Bikila"
                        class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20"
                    >
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-slate-700 mb-1">Mobile Number</label>
                    <div class="flex rounded-xl shadow-xs border border-slate-300 focus-within:border-teal-600 focus-within:ring-2 focus-within:ring-teal-600/20 overflow-hidden">
                        <span class="inline-flex items-center px-3.5 bg-slate-100 text-slate-700 text-sm font-semibold border-r border-slate-300">
                            🇪🇹 +251
                        </span>
                        <input
                            id="phone"
                            type="tel"
                            x-model="phone"
                            required
                            placeholder="911 234 567 or 711 234 567"
                            class="w-full px-3.5 py-2.5 text-sm border-0 focus:ring-0 focus:outline-hidden"
                        >
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Supports all Ethio Telecom (09) and Safaricom Ethiopia (07) numbers.</p>
                </div>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full py-2.5 px-4 rounded-xl bg-teal-700 hover:bg-teal-800 disabled:bg-teal-400 text-white font-semibold text-sm shadow-sm transition flex items-center justify-center gap-2"
                >
                    <span x-show="loading" class="inline-block animate-spin">&#9696;</span>
                    <span x-text="loading ? 'Sending code...' : 'Send 6-Digit Verification Code'"></span>
                </button>
            </form>

            <!-- Step 2: 6-Digit OTP Verification -->
            <form x-show="step === 'otp'" x-cloak method="POST" action="{{ url('/auth/otp/verify') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="phone" :value="phone">
                <input type="hidden" name="name" :value="name">
                <input type="hidden" name="role" :value="selectedRole">
                <input type="hidden" name="code" :value="otpDigits.join('')">

                <div class="flex items-center justify-between text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-100">
                    <div>
                        <span>Code sent to <strong><span x-text="phone"></span></strong></span>
                        <div x-show="debugCode" class="mt-0.5 text-teal-700 font-mono font-semibold">
                            Demo Code: <span x-text="debugCode"></span>
                            <button type="button" @click="fillDebugCode()" class="underline ml-1 text-slate-600 hover:text-teal-900">(Auto-fill)</button>
                        </div>
                    </div>
                    <button type="button" @click="step = 'phone'" class="text-teal-700 font-semibold hover:underline">Change</button>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Enter 6-Digit Verification Code</label>
                    <div class="grid grid-cols-6 gap-2">
                        <template x-for="(digit, idx) in otpDigits" :key="idx">
                            <input
                                type="text"
                                inputmode="numeric"
                                maxlength="1"
                                :x-ref="'otp_' + idx"
                                :value="otpDigits[idx]"
                                @input="handleOtpInput(idx, $event)"
                                @keydown.backspace="handleBackspace(idx, $event)"
                                class="w-full h-12 text-center text-lg font-bold rounded-xl border border-slate-300 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20"
                            >
                        </template>
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="otpDigits.join('').length !== 6"
                    class="w-full py-2.5 px-4 rounded-xl bg-teal-700 hover:bg-teal-800 disabled:opacity-50 text-white font-semibold text-sm shadow-sm transition"
                >
                    Verify & Authenticate
                </button>

                <div class="text-center text-xs text-slate-500">
                    <span x-show="countdown > 0">Resend code in <strong x-text="countdown"></strong>s</span>
                    <button
                        type="button"
                        x-show="countdown === 0"
                        @click="requestOtp()"
                        class="text-teal-700 font-semibold hover:underline"
                    >
                        Resend OTP Code
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
