<x-app-layout>
    <div
        x-data="{
            showCreateModal: false,
            editPlan: null,
            openEdit(p) {
                const values = {};
                if (p.feature_values) {
                    p.feature_values.forEach(fv => {
                        values[fv.feature_id] = fv.value;
                    });
                }
                this.editPlan = Object.assign({}, p, { feature_values_map: values });
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
                        <span>Subscriptions</span>
                        <span>/</span>
                        <span class="text-slate-800 font-semibold">Plans &amp; Pricing</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900">Subscription Plans Management</h1>
                    <p class="text-xs text-slate-500">Configure subscriber target tiers, duration, pricing (ETB), and feature quotas.</p>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.subscriptions.features.index') }}" class="px-3.5 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 transition">
                        &larr; Manage Features
                    </a>
                    <button
                        type="button"
                        @click="showCreateModal = true"
                        class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition shadow-sm"
                    >
                        + Create New Plan
                    </button>
                </div>
            </div>

            @if(session('success'))
                <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 font-semibold">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Plans Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($plans as $plan)
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 flex flex-col justify-between shadow-xs">
                        <div class="space-y-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-700 font-mono">
                                        {{ $plan->code }}
                                    </span>
                                    <h3 class="text-lg font-bold text-slate-900 mt-1">{{ $plan->name_en }}</h3>
                                    @if($plan->name_am)
                                        <div class="text-xs text-slate-500 font-ethiopic">{{ $plan->name_am }}</div>
                                    @endif
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $plan->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $plan->is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </div>

                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-baseline justify-between">
                                <div>
                                    <span class="text-2xl font-black text-slate-900">{{ number_format((float) $plan->price_etb, 0) }}</span>
                                    <span class="text-xs font-semibold text-slate-500">ETB</span>
                                    <span class="text-xs text-slate-400">/ {{ $plan->duration_days }}d</span>
                                </div>
                                <div class="text-right text-[11px] text-slate-500">
                                    Target: <strong class="text-slate-800">{{ ucfirst($plan->subscriber_type->value) }}</strong>
                                </div>
                            </div>

                            <p class="text-xs text-slate-600 line-clamp-2">
                                {{ $plan->description_en }}
                            </p>

                            <!-- Key Entitlements Preview -->
                            <div class="pt-2 border-t border-slate-100 space-y-1.5 text-xs text-slate-600">
                                @php
                                    $valuesByFeature = $plan->featureValues->keyBy('feature_id');
                                @endphp
                                @foreach($features->take(5) as $f)
                                    @php
                                        $val = $valuesByFeature->get($f->id)?->value ?? $f->default_value;
                                    @endphp
                                    <div class="flex items-center justify-between text-[11px]">
                                        <span class="text-slate-500 truncate mr-2">{{ $f->name_en }}</span>
                                        <span class="font-bold text-slate-900 font-mono">
                                            @if($val === 'true')
                                                <span class="text-emerald-600">✓ Yes</span>
                                            @elseif($val === 'false' || empty($val))
                                                <span class="text-slate-300">&times; No</span>
                                            @else
                                                {{ $val }}
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>

                            <div class="text-[11px] text-slate-500 pt-2 border-t border-slate-100 flex items-center justify-between">
                                <span>Active subscribers:</span>
                                <span class="font-bold text-slate-800 font-mono">{{ $plan->active_subscribers_count }}</span>
                            </div>
                        </div>

                        <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between">
                            <form method="POST" action="{{ route('admin.subscriptions.plans.toggle', $plan) }}">
                                @csrf
                                <button type="submit" class="text-xs text-slate-500 hover:text-slate-800 font-semibold">
                                    {{ $plan->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>

                            <div class="space-x-2">
                                <button
                                    type="button"
                                    @click="openEdit({{ \Illuminate\Support\Js::from($plan) }})"
                                    class="px-3 py-1.5 rounded-lg bg-amber-50 text-amber-900 border border-amber-200 text-xs font-bold hover:bg-amber-100"
                                >
                                    Edit Features
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Create Plan Modal -->
            <div
                x-show="showCreateModal"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
            >
                <div class="bg-white rounded-2xl border border-slate-200 max-w-2xl w-full p-6 space-y-4 shadow-xl my-8" @click.outside="showCreateModal = false">
                    <div class="flex items-center justify-between border-b pb-3">
                        <h3 class="text-base font-bold text-slate-900">Create Subscription Plan</h3>
                        <button type="button" @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('admin.subscriptions.plans.store') }}" class="space-y-4 text-xs">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Code (slug):</label>
                                <input type="text" name="code" required placeholder="e.g. broker_plus" class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-mono">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Subscriber Target Role:</label>
                                <select name="subscriber_type" class="w-full rounded-xl border-slate-300 py-1.5 text-xs">
                                    @foreach($subscriberTypes as $st)
                                        <option value="{{ $st->value }}">{{ ucfirst($st->value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Plan Name (English):</label>
                                <input type="text" name="name_en" required class="w-full rounded-xl border-slate-300 py-1.5 text-xs">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Plan Name (Amharic):</label>
                                <input type="text" name="name_am" class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-ethiopic">
                            </div>
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Description (English):</label>
                            <textarea name="description_en" rows="2" class="w-full rounded-xl border-slate-300 py-1.5 text-xs"></textarea>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Price (ETB):</label>
                                <input type="number" step="0.01" name="price_etb" required value="0.00" class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-mono">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Duration (Days):</label>
                                <input type="number" name="duration_days" required value="30" class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-mono">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Sort Order:</label>
                                <input type="number" name="sort_order" value="10" class="w-full rounded-xl border-slate-300 py-1.5 text-xs">
                            </div>
                        </div>

                        <!-- Feature Values Assignment -->
                        <div class="border rounded-xl p-3 bg-slate-50 space-y-2">
                            <h4 class="font-bold text-slate-800 text-xs">Assign Feature Values / Quotas</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto p-1">
                                @foreach($features as $f)
                                    <div class="flex items-center justify-between gap-2 p-1.5 bg-white rounded-lg border border-slate-200">
                                        <span class="text-[11px] font-medium text-slate-700 truncate">{{ $f->name_en }}</span>
                                        <input
                                            type="text"
                                            name="feature_values[{{ $f->id }}]"
                                            placeholder="{{ $f->default_value ?? 'val' }}"
                                            class="w-24 rounded border-slate-300 py-1 px-1.5 text-[11px] font-mono"
                                        >
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="pt-3 border-t flex justify-end gap-2">
                            <button type="button" @click="showCreateModal = false" class="px-3 py-1.5 rounded-xl border text-slate-600 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-4 py-1.5 rounded-xl bg-amber-600 text-white font-bold hover:bg-amber-700">Save Plan</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Plan Modal -->
            <div
                x-show="editPlan !== null"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
            >
                <div class="bg-white rounded-2xl border border-slate-200 max-w-2xl w-full p-6 space-y-4 shadow-xl my-8" @click.outside="editPlan = null">
                    <div class="flex items-center justify-between border-b pb-3">
                        <h3 class="text-base font-bold text-slate-900">Edit Plan: <span x-text="editPlan ? editPlan.name_en : ''" class="text-amber-700"></span></h3>
                        <button type="button" @click="editPlan = null" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                    </div>

                    <template x-if="editPlan">
                        <form :action="'/admin/subscriptions/plans/' + editPlan.id" method="POST" class="space-y-4 text-xs">
                            @csrf
                            @method('PUT')

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Plan Name (English):</label>
                                    <input type="text" name="name_en" x-model="editPlan.name_en" required class="w-full rounded-xl border-slate-300 py-1.5 text-xs">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Plan Name (Amharic):</label>
                                    <input type="text" name="name_am" x-model="editPlan.name_am" class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-ethiopic">
                                </div>
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Description (English):</label>
                                <textarea name="description_en" x-model="editPlan.description_en" rows="2" class="w-full rounded-xl border-slate-300 py-1.5 text-xs"></textarea>
                            </div>

                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Subscriber Target:</label>
                                    <select name="subscriber_type" x-model="editPlan.subscriber_type" class="w-full rounded-xl border-slate-300 py-1.5 text-xs">
                                        @foreach($subscriberTypes as $st)
                                            <option value="{{ $st->value }}">{{ ucfirst($st->value) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Price (ETB):</label>
                                    <input type="number" step="0.01" name="price_etb" x-model="editPlan.price_etb" required class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-mono">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Duration (Days):</label>
                                    <input type="number" name="duration_days" x-model="editPlan.duration_days" required class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-mono">
                                </div>
                            </div>

                            <!-- Feature Values Assignment -->
                            <div class="border rounded-xl p-3 bg-slate-50 space-y-2">
                                <h4 class="font-bold text-slate-800 text-xs">Edit Feature Values / Quotas</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto p-1">
                                    @foreach($features as $f)
                                        <div class="flex items-center justify-between gap-2 p-1.5 bg-white rounded-lg border border-slate-200">
                                            <span class="text-[11px] font-medium text-slate-700 truncate">{{ $f->name_en }}</span>
                                            <input
                                                type="text"
                                                name="feature_values[{{ $f->id }}]"
                                                :value="editPlan.feature_values_map ? editPlan.feature_values_map[{{ $f->id }}] : ''"
                                                class="w-24 rounded border-slate-300 py-1 px-1.5 text-[11px] font-mono"
                                            >
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="pt-3 border-t flex justify-end gap-2">
                                <button type="button" @click="editPlan = null" class="px-3 py-1.5 rounded-xl border text-slate-600 hover:bg-slate-50">Cancel</button>
                                <button type="submit" class="px-4 py-1.5 rounded-xl bg-amber-600 text-white font-bold hover:bg-amber-700">Update Plan</button>
                            </div>
                        </form>
                    </template>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

