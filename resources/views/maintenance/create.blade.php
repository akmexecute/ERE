<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
        <!-- Header & Breadcrumb -->
        <div class="border-b border-slate-200 pb-6">
            <nav class="text-xs text-slate-500 mb-1 flex items-center gap-2">
                <a href="{{ route('maintenance.index') }}" class="hover:text-teal-700">Maintenance</a>
                <span>/</span>
                <span class="text-slate-800 font-medium">New Request</span>
            </nav>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Report a Maintenance Issue</h1>
            <p class="text-sm text-slate-600 mt-1">Submit a detailed work order for repairs, utility malfunctions, or structural maintenance.</p>
        </div>

        @if ($errors->any())
            <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs space-y-1">
                <div class="font-bold">Please correct the following errors:</div>
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('maintenance.store') }}" class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 space-y-6 shadow-xs">
            @csrf

            <!-- Property Selection -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Select Property <span class="text-red-500">*</span></label>
                <select name="property_id" required class="w-full text-sm rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                    <option value="">-- Choose Managed Property --</option>
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}" {{ (old('property_id', $selectedPropertyId) == $property->id) ? 'selected' : '' }}>
                            {{ $property->title_en }} ({{ $property->property_code }}) — {{ $property->city?->name_en ?? 'Addis Ababa' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Category & Priority Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Maintenance Category <span class="text-red-500">*</span></label>
                    <select name="category" required class="w-full text-sm rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                        @foreach($categories as $category)
                            <option value="{{ $category->value }}" {{ old('category') === $category->value ? 'selected' : '' }}>
                                {{ $category->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Priority Level <span class="text-red-500">*</span></label>
                    <select name="priority" required class="w-full text-sm rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                        @foreach($priorities as $priority)
                            <option value="{{ $priority->value }}" {{ (old('priority', 'normal') === $priority->value) ? 'selected' : '' }}>
                                {{ $priority->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Title -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Issue Summary / Title <span class="text-red-500">*</span></label>
                <input
                    type="text"
                    name="title"
                    value="{{ old('title') }}"
                    required
                    placeholder="e.g. Master bathroom pipe leaking under sink"
                    class="w-full text-sm rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500"
                >
            </div>

            <!-- Description -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Detailed Description <span class="text-red-500">*</span></label>
                <textarea
                    name="description"
                    rows="4"
                    required
                    placeholder="Describe the exact location, symptoms, urgency, and any attempted measures..."
                    class="w-full text-sm rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500"
                >{{ old('description') }}</textarea>
            </div>

            <!-- Estimated Cost & Billable Party -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-4 border-t border-slate-100">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Estimated Cost (ETB)</label>
                    <div class="relative rounded-xl">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-mono text-slate-400">ETB</span>
                        <input
                            type="number"
                            step="0.01"
                            name="estimated_cost_etb"
                            value="{{ old('estimated_cost_etb', '0.00') }}"
                            class="w-full pl-12 text-sm rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500"
                        >
                    </div>
                    <span class="text-[11px] text-slate-400">Can be refined later after contractor assessment.</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Billable Responsibility</label>
                    <select name="billable_party" class="w-full text-sm rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                        <option value="owner" {{ old('billable_party') === 'owner' ? 'selected' : '' }}>Property Owner</option>
                        <option value="tenant" {{ old('billable_party') === 'tenant' ? 'selected' : '' }}>Tenant (Damage / Misuse)</option>
                        <option value="company" {{ old('billable_party') === 'company' ? 'selected' : '' }}>Management Company</option>
                        <option value="warranty" {{ old('billable_party') === 'warranty' ? 'selected' : '' }}>Developer / Contractor Warranty</option>
                    </select>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-6 border-t border-slate-200 flex items-center justify-end gap-3">
                <a href="{{ route('maintenance.index') }}" class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
                    Cancel
                </a>
                <button
                    type="submit"
                    class="px-6 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-sm font-semibold shadow-xs transition"
                >
                    Submit Maintenance Ticket
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
