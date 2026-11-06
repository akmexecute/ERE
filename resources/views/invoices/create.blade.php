<x-app-layout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8" x-data="{
        items: [
            { description: 'Residential Property Lease (Period Installment)', quantity: 1, unit_price: 35000.00 }
        ],
        addItem() {
            this.items.push({ description: '', quantity: 1, unit_price: 0.00 });
        },
        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
        },
        calculateSubtotal() {
            return this.items.reduce((sum, item) => sum + ((parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0)), 0);
        }
    }">
        <!-- Header & Breadcrumbs -->
        <div class="border-b border-slate-200 pb-6">
            <nav class="text-xs text-slate-500 mb-1 flex items-center gap-2">
                <a href="{{ route('invoices.index') }}" class="hover:text-teal-700">Invoices</a>
                <span>/</span>
                <span class="text-slate-800 font-medium">Issue Invoice</span>
            </nav>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Issue Official ETB Tax Invoice</h1>
            <p class="text-sm text-slate-600 mt-1">Itemized service lines, automated Ethiopian tax calculation, and persistent numbering.</p>
        </div>

        @if ($errors->any())
            <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs space-y-1">
                <div class="font-bold">Please correct the following:</div>
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('invoices.store') }}" class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 space-y-8 shadow-xs">
            @csrf

            <!-- Entity & Scope Selection -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Issuing Company <span class="text-red-500">*</span></label>
                    <select name="company_id" required class="w-full text-xs rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->legal_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Billed Customer <span class="text-red-500">*</span></label>
                    <select name="customer_user_id" required class="w-full text-xs rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->phone }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Service Type <span class="text-red-500">*</span></label>
                    <select name="service_type" required class="w-full text-xs rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                        <option value="rent">Rent / Long-term Lease</option>
                        <option value="lease">Commercial Lease</option>
                        <option value="maintenance">Maintenance & Repairs</option>
                        <option value="commission">Agency Commission</option>
                        <option value="short_stay">Short-Stay Accommodation</option>
                        <option value="other">General Professional Service</option>
                    </select>
                </div>
            </div>

            <!-- Dates & Location Scope -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-4 border-t border-slate-100">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Issue Date</label>
                    <input type="date" name="issue_date" value="{{ date('Y-m-d') }}" class="w-full text-xs rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Payment Due Date</label>
                    <input type="date" name="due_date" value="{{ date('Y-m-d', strtotime('+30 days')) }}" class="w-full text-xs rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Tax Location Scope (Optional)</label>
                    <select name="location_id" class="w-full text-xs rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                        <option value="">National Fallback (Addis Ababa / Federal)</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name_en }} ({{ ucfirst($loc->type) }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Dynamic Line Items Section -->
            <div class="space-y-4 pt-4 border-t border-slate-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Itemized Services</h3>
                    <button type="button" @click="addItem()" class="px-3 py-1.5 rounded-lg bg-teal-50 text-teal-700 hover:bg-teal-100 text-xs font-semibold transition">
                        + Add Line Item
                    </button>
                </div>

                <div class="space-y-3">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="grid grid-cols-12 gap-3 items-center p-3 rounded-xl bg-slate-50 border border-slate-200">
                            <div class="col-span-6">
                                <label class="block text-[10px] font-semibold text-slate-500 uppercase mb-1">Description</label>
                                <input type="text" :name="'items[' + index + '][description]'" x-model="item.description" required placeholder="Service or product description..." class="w-full text-xs rounded-lg border-slate-300">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-[10px] font-semibold text-slate-500 uppercase mb-1">Qty</label>
                                <input type="number" step="0.01" min="0.01" :name="'items[' + index + '][quantity]'" x-model="item.quantity" required class="w-full text-xs rounded-lg border-slate-300 font-mono">
                            </div>
                            <div class="col-span-3">
                                <label class="block text-[10px] font-semibold text-slate-500 uppercase mb-1">Unit Price (ETB)</label>
                                <input type="number" step="0.01" min="0" :name="'items[' + index + '][unit_price_etb]'" x-model="item.unit_price" required class="w-full text-xs rounded-lg border-slate-300 font-mono">
                            </div>
                            <div class="col-span-1 text-right pt-4">
                                <button type="button" @click="removeItem(index)" class="text-red-500 hover:text-red-700 font-bold text-sm" title="Remove">&times;</button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Subtotal Summary Box -->
                <div class="flex justify-end pt-2">
                    <div class="w-72 bg-slate-50 rounded-xl p-4 border border-slate-200 space-y-2 text-xs">
                        <div class="flex justify-between text-slate-600">
                            <span>Estimated Subtotal:</span>
                            <span class="font-mono font-bold text-slate-900" x-text="'ETB ' + calculateSubtotal().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                        </div>
                        <p class="text-[11px] text-slate-400">
                            * Official VAT (15%) and applicable municipal taxes will be computed deterministically upon submission based on active database tax rules.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="pt-4 border-t border-slate-100">
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Payment Terms & Notes</label>
                <textarea name="notes" rows="2" placeholder="Payment via CBE Birr, Telebirr, or direct bank wire transfer within 30 days..." class="w-full text-xs rounded-xl border-slate-300"></textarea>
            </div>

            <!-- Submit Buttons -->
            <div class="pt-6 border-t border-slate-200 flex items-center justify-end gap-3">
                <a href="{{ route('invoices.index') }}" class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition">
                    Cancel
                </a>
                <button
                    type="submit"
                    class="px-6 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold shadow-xs transition"
                >
                    Issue & Persist Invoice
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
