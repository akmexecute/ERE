<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8" x-data="{ newMandateModal: false }">
        <!-- Breadcrumb & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-6">
            <div>
                <nav class="text-xs text-slate-500 mb-1 flex items-center gap-2">
                    <a href="{{ route('portfolio.dashboard') }}" class="hover:text-teal-700">Portfolio Dashboard</a>
                    <span>/</span>
                    <span class="text-slate-800 font-medium">Management Mandates</span>
                </nav>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Property Management Mandates</h1>
                <p class="text-sm text-slate-600 mt-1">Legally binding management agreements connecting property owners, management firms, and fee structures.</p>
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="newMandateModal = true"
                    class="px-4 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold shadow-xs transition"
                >
                    + Register Management Mandate
                </button>
            </div>
        </div>

        @if ($errors->any())
            <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs space-y-1">
                <div class="font-bold">Mandate validation error:</div>
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Mandates Table -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase font-semibold">
                        <tr>
                            <th class="px-6 py-3.5">Mandate Code</th>
                            <th class="px-6 py-3.5">Property & Location</th>
                            <th class="px-6 py-3.5">Property Owner</th>
                            <th class="px-6 py-3.5">Management Firm / Branch</th>
                            <th class="px-6 py-3.5">Commission / Fee</th>
                            <th class="px-6 py-3.5">Term Dates</th>
                            <th class="px-6 py-3.5">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($mandates as $mandate)
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="px-6 py-4 font-mono font-bold text-purple-800">
                                    {{ $mandate->mandate_code }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-slate-900">{{ $mandate->property?->title_en ?? 'Property #'.$mandate->property_id }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $mandate->property?->property_code }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-medium text-slate-900">{{ $mandate->owner?->name }}</span>
                                    <span class="block text-[11px] text-slate-400 font-mono">{{ $mandate->owner?->phone }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-slate-900">{{ $mandate->company?->legal_name }}</div>
                                    <span class="text-[11px] text-slate-400">{{ $mandate->branch?->name_en ?? 'Head Office' }}</span>
                                </td>
                                <td class="px-6 py-4 font-mono font-bold text-teal-700">
                                    {{ $mandate->management_fee_pct }}%
                                </td>
                                <td class="px-6 py-4">
                                    <div>@ethDate($mandate->start_date)</div>
                                    <span class="text-[11px] text-slate-400">
                                        @if($mandate->end_date)
                                            to @ethDate($mandate->end_date)
                                        @else
                                            to Ongoing
                                        @endif
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        {{ $mandate->status->label() }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                    No property management mandates found. Click "+ Register Management Mandate" to create one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($mandates->hasPages())
                <div class="p-4 border-t border-slate-200">
                    {{ $mandates->links() }}
                </div>
            @endif
        </div>

        <!-- New Mandate Modal -->
        <div
            x-show="newMandateModal"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div
                @click.outside="newMandateModal = false"
                class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-lg w-full p-6 space-y-5"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Appoint Property Management Mandate</h3>
                    <button type="button" @click="newMandateModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
                </div>

                <form method="POST" action="{{ route('portfolio.mandates.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Select Property <span class="text-red-500">*</span></label>
                        <select name="property_id" required class="w-full text-xs rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                            @foreach($properties as $prop)
                                <option value="{{ $prop->id }}">{{ $prop->title_en }} ({{ $prop->property_code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Management Company <span class="text-red-500">*</span></label>
                        <select name="company_id" required class="w-full text-xs rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                            @foreach($companies as $comp)
                                <option value="{{ $comp->id }}">{{ $comp->legal_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Management Fee Percentage (0–100%) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="number" step="0.01" min="0" max="100" name="management_fee_pct" value="10.00" required class="w-full text-xs rounded-xl border-slate-300 pl-3 pr-8 focus:border-teal-500 focus:ring-teal-500 font-mono">
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-xs text-slate-400">%</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Start Date <span class="text-red-500">*</span></label>
                            <input type="date" name="start_date" required value="{{ date('Y-m-d') }}" class="w-full text-xs rounded-xl border-slate-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">End Date (Optional)</label>
                            <input type="date" name="end_date" class="w-full text-xs rounded-xl border-slate-300">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Mandate Terms & Covenants</label>
                        <textarea name="terms" rows="3" placeholder="Full service management including tenant vetting, rent collection, and maintenance dispatch..." class="w-full text-xs rounded-xl border-slate-300"></textarea>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" @click="newMandateModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold">
                            Save Mandate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
