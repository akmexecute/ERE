<x-app-layout>
    <div class="min-h-screen bg-[#faf9f5] py-10 px-4 sm:px-6 lg:px-8" x-data="{
        idType: '{{ old('national_id_type', $user->national_id_type ?? 'fayda') }}',
        frontPreview: null,
        backPreview: null,
        frontFileName: '',
        backFileName: '',
        submitting: false,
        handleFront(e) {
            const file = e.target.files[0];
            if (file) {
                this.frontFileName = file.name;
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (ev) => this.frontPreview = ev.target.result;
                    reader.readAsDataURL(file);
                } else {
                    this.frontPreview = null;
                }
            }
        },
        handleBack(e) {
            const file = e.target.files[0];
            if (file) {
                this.backFileName = file.name;
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (ev) => this.backPreview = ev.target.result;
                    reader.readAsDataURL(file);
                } else {
                    this.backPreview = null;
                }
            }
        }
    }">
        <div class="max-w-3xl mx-auto space-y-8">

            <!-- Breadcrumb & Back -->
            <div class="flex items-center justify-between text-xs text-slate-500">
                <a href="{{ $redirectUrl }}" class="inline-flex items-center gap-1.5 font-semibold text-slate-700 hover:text-teal-900 transition">
                    <span>&larr;</span> Return to previous step
                </a>
                <span>Step: Identity &amp; Compliance Verification</span>
            </div>

            <!-- Header -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-4">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-teal-50 text-teal-800 text-sm font-bold border border-teal-200">
                        🇪🇹
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-teal-800">
                        National Trust &amp; Fayda Integration
                    </span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                    Identity Verification
                </h1>
                <p class="text-sm text-slate-600 leading-relaxed">
                    Under Ethiopian real estate and financial regulations, customers participating in high-value transactions (short-stay bookings, rental leases, purchase offers, and unit reservations) must provide verified telephone details and an authenticated National ID / Fayda document.
                </p>

                <!-- Phone Verification Status Banner -->
                @if($user->phone_verified_at)
                    <div class="flex items-center gap-3 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900">
                        <span class="text-base">✓</span>
                        <div>
                            <span class="font-bold">Telephone Verified via OTP:</span>
                            <span class="ml-1 font-mono">{{ $user->phone }}</span>
                            <span class="text-emerald-700 ml-1">({{ $user->phone_verified_at->format('M d, Y') }})</span>
                        </div>
                    </div>
                @else
                    <div class="flex items-center justify-between gap-3 p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900">
                        <div class="flex items-center gap-2">
                            <span class="text-base">⚠️</span>
                            <div>
                                <span class="font-bold">Telephone Unverified:</span>
                                <span>Please verify your phone via SMS OTP to satisfy transaction security.</span>
                            </div>
                        </div>
                        <a href="{{ route('login') }}" class="font-bold underline text-amber-950 hover:text-black">
                            Verify Now
                        </a>
                    </div>
                @endif
            </div>

            <!-- Upload Form -->
            <form action="{{ route('identity.verify.store') }}" method="POST" enctype="multipart/form-data" @submit="submitting = true" class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-8">
                @csrf
                <input type="hidden" name="redirect_url" value="{{ $redirectUrl }}">

                @if($errors->any())
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
                        <p class="font-bold">Please resolve the following submission errors:</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Document Type & Identifier -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-800 mb-1.5">
                            Identification Document Type <span class="text-rose-500">*</span>
                        </label>
                        <select name="national_id_type" x-model="idType" class="w-full rounded-xl border-slate-300 text-sm focus:ring-teal-700 focus:border-teal-700">
                            <option value="fayda">National ID / Fayda (Physical Card)</option>
                            <option value="fayda_digital_qr">Fayda Digital ID (QR Code / Single-Sided)</option>
                            <option value="kebele_id">Resident Kebele ID Card</option>
                            <option value="passport">Ethiopian Passport</option>
                        </select>
                        <p class="text-[11px] text-slate-500 mt-1">Select the official credential you are uploading.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-800 mb-1.5">
                            National ID / Fayda Number (FAN / ID) <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="national_id_number"
                            value="{{ old('national_id_number', $user->national_id_number) }}"
                            placeholder="e.g. FAN-1234-5678-9012"
                            required
                            class="w-full rounded-xl border-slate-300 text-sm focus:ring-teal-700 focus:border-teal-700 font-mono"
                        >
                        @if($user->masked_national_id)
                            <p class="text-[11px] text-slate-500 mt-1">Currently on file: <span class="font-mono text-teal-800 font-semibold">{{ $user->masked_national_id }}</span></p>
                        @else
                            <p class="text-[11px] text-slate-500 mt-1">Enter your authoritative 12 or 16-digit identification number.</p>
                        @endif
                    </div>
                </div>

                <!-- Two Distinct Upload Fields: Front & Back -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-slate-100">

                    <!-- Field 1: National ID / Fayda — Front Image -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-900 uppercase tracking-wider">
                                National ID/Fayda — Front Image <span class="text-rose-500">*</span>
                            </label>
                            @if($user->id_front_document_id)
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    ✓ On File
                                </span>
                            @else
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
                                    Missing
                                </span>
                            @endif
                        </div>

                        <div class="relative border-2 border-dashed border-slate-300 hover:border-teal-600 rounded-2xl p-5 text-center transition bg-slate-50/50">
                            <template x-if="frontPreview">
                                <div class="space-y-3">
                                    <img :src="frontPreview" alt="Front Preview" class="max-h-44 mx-auto rounded-lg object-contain shadow-sm border border-slate-200">
                                    <p class="text-xs text-slate-600 font-medium truncate" x-text="frontFileName"></p>
                                    <label class="inline-block text-xs font-semibold text-teal-800 hover:underline cursor-pointer">
                                        Replace front image
                                        <input type="file" name="id_front_image" accept="image/jpeg,image/png,image/webp,application/pdf" class="hidden" @change="handleFront($event)">
                                    </label>
                                </div>
                            </template>
                            <template x-if="!frontPreview">
                                <div>
                                    <div class="text-3xl mb-2">🪪</div>
                                    <p class="text-xs font-semibold text-slate-800">Upload Front Side</p>
                                    <p class="text-[11px] text-slate-500 mt-1">JPEG, PNG, WebP or PDF (up to 10MB)</p>
                                    <label class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-white border border-slate-300 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 cursor-pointer transition">
                                        <span>Select file</span>
                                        <input type="file" name="id_front_image" accept="image/jpeg,image/png,image/webp,application/pdf" {{ $user->id_front_document_id ? '' : 'required' }} class="hidden" @change="handleFront($event)">
                                    </label>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Field 2: National ID / Fayda — Back Image -->
                    <div class="space-y-3" x-show="idType !== 'fayda_digital_qr'">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-900 uppercase tracking-wider">
                                National ID/Fayda — Back Image <span class="text-rose-500" x-show="idType !== 'fayda_digital_qr'">*</span>
                            </label>
                            @if($user->id_back_document_id)
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    ✓ On File
                                </span>
                            @else
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
                                    Missing
                                </span>
                            @endif
                        </div>

                        <div class="relative border-2 border-dashed border-slate-300 hover:border-teal-600 rounded-2xl p-5 text-center transition bg-slate-50/50">
                            <template x-if="backPreview">
                                <div class="space-y-3">
                                    <img :src="backPreview" alt="Back Preview" class="max-h-44 mx-auto rounded-lg object-contain shadow-sm border border-slate-200">
                                    <p class="text-xs text-slate-600 font-medium truncate" x-text="backFileName"></p>
                                    <label class="inline-block text-xs font-semibold text-teal-800 hover:underline cursor-pointer">
                                        Replace back image
                                        <input type="file" name="id_back_image" accept="image/jpeg,image/png,image/webp,application/pdf" class="hidden" @change="handleBack($event)">
                                    </label>
                                </div>
                            </template>
                            <template x-if="!backPreview">
                                <div>
                                    <div class="text-3xl mb-2">🔄</div>
                                    <p class="text-xs font-semibold text-slate-800">Upload Back Side</p>
                                    <p class="text-[11px] text-slate-500 mt-1">Barcode, address &amp; signature side</p>
                                    <label class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-white border border-slate-300 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 cursor-pointer transition">
                                        <span>Select file</span>
                                        <input type="file" name="id_back_image" accept="image/jpeg,image/png,image/webp,application/pdf" class="hidden" @change="handleBack($event)">
                                    </label>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Privacy & Document Protection Notice -->
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 space-y-1">
                    <p class="font-bold text-slate-900">🛡️ Document Privacy &amp; Access Controls</p>
                    <p class="leading-relaxed">
                        Your identification documents are stored in an encrypted private vault (`s3_private`). Original document images are strictly restricted to authorized Ethiopian verification officers. Counterparties (hosts, sellers, or landlords) only see your <strong>Verified</strong> trust badge and never receive your raw document images or unmasked credentials.
                    </p>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <a href="{{ $redirectUrl }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Cancel
                    </a>
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="px-6 py-2.5 rounded-xl bg-[#0f3a38] hover:bg-[#144c49] text-white text-xs font-bold shadow-sm transition disabled:opacity-50"
                    >
                        <span x-show="!submitting">Submit Identification Documents</span>
                        <span x-show="submitting">Encrypting &amp; Uploading...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

