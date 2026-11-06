<x-app-layout>
    <div
        x-data="{
            showCreateModal: false,
            editPackage: null,
            openEdit(pkg) {
                this.editPackage = Object.assign({}, pkg);
            }
        }"
        class="bg-slate-50 min-h-screen py-8 px-4 sm:px-6 lg:px-8 text-slate-800"
    >
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Breadcrumbs & Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                        <a href="{{ route('admin.dashboard') }}" class="hover:underline">Admin</a>
                        <span>/</span>
                        <span>Monetization</span>
                        <span>/</span>
                        <span class="text-slate-800 font-semibold">Listing Promotions</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900">Promoted Listing Packages (Module 33.5)</h1>
                    <p class="text-xs text-slate-500">Configure promotion tiers, ranking boost weights, search badges, and eligible subscription plans.</p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="showCreateModal = true"
                        class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition shadow-sm"
                    >
                        + Create Package
                    </button>
                </div>
            </div>

            @if(session('success'))
                <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 font-semibold">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Packages Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($packages as $pkg)
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 flex flex-col justify-between shadow-xs">
                        <div class="space-y-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-900 font-mono">
                                        {{ $pkg->tier->value }}
                                    </span>
                                    <h3 class="text-lg font-bold text-slate-900 mt-1">{{ $pkg->name_en }}</h3>
                                    @if($pkg->name_am)
                                        <div class="text-xs text-slate-500 font-ethiopic">{{ $pkg->name_am }}</div>
                                    @endif
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $pkg->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $pkg->is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </div>

                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-baseline justify-between">
                                <div>
                                    <span class="text-2xl font-black text-slate-900">{{ number_format((float) $pkg->price_etb, 0) }}</span>
                                    <span class="text-xs font-semibold text-slate-500">ETB</span>
                                    <span class="text-xs text-slate-400">/ {{ $pkg->duration_days }}d</span>
                                </div>
                                <div class="text-right text-[11px] font-bold text-amber-800 bg-amber-50 px-2 py-1 rounded-lg border border-amber-200">
                                    +{{ $pkg->ranking_weight }} Search Weight
                                </div>
                            </div>

                            <p class="text-xs text-slate-600 line-clamp-2">
                                {{ $pkg->description_en }}
                            </p>

                            <div class="pt-2 border-t border-slate-100 space-y-1.5 text-xs text-slate-600">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="text-slate-500">Badge Label:</span>
                                    <span class="font-bold text-slate-900 font-mono">{{ $pkg->badge_label ?? 'None' }}</span>
                                </div>
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="text-slate-500">Active Listings Promoted:</span>
                                    <span class="font-bold text-slate-900 font-mono">{{ $pkg->active_promotions_count }}</span>
                                </div>
                                <div class="flex items-start justify-between text-[11px]">
                                    <span class="text-slate-500">Eligible Plans:</span>
                                    <span class="text-right text-slate-700 font-mono text-[10px]">
                                        @if($pkg->eligible_plan_codes)
                                            {{ implode(', ', $pkg->eligible_plan_codes) }}
                                        @else
                                            All Paid Plans
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between">
                            <form method="POST" action="{{ route('admin.promotions.toggle', $pkg) }}">
                                @csrf
                                <button type="submit" class="text-xs text-slate-500 hover:text-slate-800 font-semibold">
                                    {{ $pkg->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>

                            <button
                                type="button"
                                @click="openEdit({{ \Illuminate\Support\Js::from($pkg) }})"
                                class="px-3 py-1.5 rounded-lg bg-amber-50 text-amber-900 border border-amber-200 text-xs font-bold hover:bg-amber-100"
                            >
                                Edit Package
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Edit Package Modal -->
            <div
                x-show="editPackage !== null"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            >
                <div class="bg-white rounded-2xl border border-slate-200 max-w-lg w-full p-6 space-y-4 shadow-xl" @click.outside="editPackage = null">
                    <div class="flex items-center justify-between border-b pb-3">
                        <h3 class="text-base font-bold text-slate-900">Edit Package: <span x-text="editPackage ? editPackage.name_en : ''" class="text-amber-700"></span></h3>
                        <button type="button" @click="editPackage = null" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                    </div>

                    <template x-if="editPackage">
                        <form :action="'/admin/promotions/' + editPackage.id" method="POST" class="space-y-3 text-xs">
                            @csrf
                            @method('PUT')

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Name (English):</label>
                                    <input type="text" name="name_en" x-model="editPackage.name_en" required class="w-full rounded-xl border-slate-300 py-1.5 text-xs">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Name (Amharic):</label>
                                    <input type="text" name="name_am" x-model="editPackage.name_am" class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-ethiopic">
                                </div>
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Description (English):</label>
                                <textarea name="description_en" x-model="editPackage.description_en" rows="2" class="w-full rounded-xl border-slate-300 py-1.5 text-xs"></textarea>
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Price (ETB):</label>
                                    <input type="number" step="0.01" name="price_etb" x-model="editPackage.price_etb" required class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-mono">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Duration (Days):</label>
                                    <input type="number" name="duration_days" x-model="editPackage.duration_days" required class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-mono">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Search Weight:</label>
                                    <input type="number" name="ranking_weight" x-model="editPackage.ranking_weight" required class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-mono">
                                </div>
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Badge Label (e.g. Featured, Urgent):</label>
                                <input type="text" name="badge_label" x-model="editPackage.badge_label" class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-mono">
                            </div>

                            <div class="pt-3 border-t flex justify-end gap-2">
                                <button type="button" @click="editPackage = null" class="px-3 py-1.5 rounded-xl border text-slate-600 hover:bg-slate-50">Cancel</button>
                                <button type="submit" class="px-4 py-1.5 rounded-xl bg-amber-600 text-white font-bold hover:bg-amber-700">Update Package</button>
                            </div>
                        </form>
                    </template>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

