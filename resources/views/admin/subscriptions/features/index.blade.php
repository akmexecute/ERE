<x-app-layout>
    <div
        x-data="{
            showCreateModal: false,
            editFeature: null,
            openEdit(feat) {
                this.editFeature = Object.assign({}, feat);
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
                        <span class="text-slate-800 font-semibold">Features Catalog</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900">Dynamic Subscription Features</h1>
                    <p class="text-xs text-slate-500">Define and manage capabilities, quotas, and feature flags available in subscription tiers.</p>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.subscriptions.plans.index') }}" class="px-3.5 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 transition">
                        View Plans &rarr;
                    </a>
                    <button
                        type="button"
                        @click="showCreateModal = true"
                        class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition shadow-sm"
                    >
                        + Create New Feature
                    </button>
                </div>
            </div>

            @if(session('success'))
                <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 font-semibold">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Features Table -->
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-slate-700">
                                <th class="p-3.5 pl-6 font-semibold">Feature Key &amp; Name</th>
                                <th class="p-3.5 font-semibold">Value Type</th>
                                <th class="p-3.5 font-semibold">Default Value</th>
                                <th class="p-3.5 font-semibold">Sort</th>
                                <th class="p-3.5 font-semibold">Status</th>
                                <th class="p-3.5 pr-6 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach($features as $feature)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="p-3.5 pl-6">
                                        <div class="font-bold text-slate-900 font-mono text-xs">{{ $feature->key }}</div>
                                        <div class="font-medium text-slate-700">{{ $feature->name_en }}</div>
                                        @if($feature->name_am)
                                            <div class="text-[11px] text-slate-400 font-ethiopic">{{ $feature->name_am }}</div>
                                        @endif
                                        <div class="text-[11px] text-slate-500 max-w-md truncate">{{ $feature->description_en }}</div>
                                    </td>
                                    <td class="p-3.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-800">
                                            {{ $feature->value_type->value }}
                                        </span>
                                    </td>
                                    <td class="p-3.5 font-mono text-slate-800">
                                        {{ $feature->default_value ?? '—' }}
                                    </td>
                                    <td class="p-3.5 font-mono text-slate-500">
                                        {{ $feature->sort_order }}
                                    </td>
                                    <td class="p-3.5">
                                        <form method="POST" action="{{ route('admin.subscriptions.features.toggle', $feature) }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="px-2 py-0.5 rounded text-[11px] font-bold transition {{ $feature->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}"
                                            >
                                                {{ $feature->is_active ? 'Active' : 'Inactive' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="p-3.5 pr-6 text-right space-x-2">
                                        <button
                                            type="button"
                                            @click="openEdit({{ \Illuminate\Support\Js::from($feature) }})"
                                            class="text-amber-700 hover:text-amber-900 font-semibold"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('admin.subscriptions.features.destroy', $feature) }}" class="inline" onsubmit="return confirm('Archive this feature?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-600 hover:text-rose-800 font-semibold">
                                                Archive
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Create Feature Modal -->
            <div
                x-show="showCreateModal"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            >
                <div class="bg-white rounded-2xl border border-slate-200 max-w-lg w-full p-6 space-y-4 shadow-xl" @click.outside="showCreateModal = false">
                    <div class="flex items-center justify-between border-b pb-3">
                        <h3 class="text-base font-bold text-slate-900">Create Subscription Feature</h3>
                        <button type="button" @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('admin.subscriptions.features.store') }}" class="space-y-3 text-xs">
                        @csrf
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Unique Key (e.g. property.virtual_tours):</label>
                            <input type="text" name="key" required placeholder="module.feature_name" class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-mono">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Name (English):</label>
                                <input type="text" name="name_en" required class="w-full rounded-xl border-slate-300 py-1.5 text-xs">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Name (Amharic):</label>
                                <input type="text" name="name_am" class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-ethiopic">
                            </div>
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Description (English):</label>
                            <textarea name="description_en" rows="2" class="w-full rounded-xl border-slate-300 py-1.5 text-xs"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Value Type:</label>
                                <select name="value_type" class="w-full rounded-xl border-slate-300 py-1.5 text-xs">
                                    @foreach($valueTypes as $vt)
                                        <option value="{{ $vt->value }}">{{ ucfirst($vt->value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Default Value:</label>
                                <input type="text" name="default_value" placeholder="e.g. false, 10, unlimited" class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-mono">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Sort Order:</label>
                                <input type="number" name="sort_order" value="10" class="w-full rounded-xl border-slate-300 py-1.5 text-xs">
                            </div>
                            <div class="flex items-center pt-5">
                                <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-slate-700">
                                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-amber-600">
                                    <span>Active</span>
                                </label>
                            </div>
                        </div>

                        <div class="pt-3 border-t flex justify-end gap-2">
                            <button type="button" @click="showCreateModal = false" class="px-3 py-1.5 rounded-xl border text-slate-600 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-4 py-1.5 rounded-xl bg-amber-600 text-white font-bold hover:bg-amber-700">Save Feature</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Feature Modal -->
            <div
                x-show="editFeature !== null"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            >
                <div class="bg-white rounded-2xl border border-slate-200 max-w-lg w-full p-6 space-y-4 shadow-xl" @click.outside="editFeature = null">
                    <div class="flex items-center justify-between border-b pb-3">
                        <h3 class="text-base font-bold text-slate-900">Edit Feature: <span x-text="editFeature ? editFeature.key : ''" class="font-mono text-amber-700"></span></h3>
                        <button type="button" @click="editFeature = null" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                    </div>

                    <template x-if="editFeature">
                        <form :action="'/admin/subscriptions/features/' + editFeature.id" method="POST" class="space-y-3 text-xs">
                            @csrf
                            @method('PUT')

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Name (English):</label>
                                    <input type="text" name="name_en" x-model="editFeature.name_en" required class="w-full rounded-xl border-slate-300 py-1.5 text-xs">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Name (Amharic):</label>
                                    <input type="text" name="name_am" x-model="editFeature.name_am" class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-ethiopic">
                                </div>
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Description (English):</label>
                                <textarea name="description_en" x-model="editFeature.description_en" rows="2" class="w-full rounded-xl border-slate-300 py-1.5 text-xs"></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Value Type:</label>
                                    <select name="value_type" x-model="editFeature.value_type" class="w-full rounded-xl border-slate-300 py-1.5 text-xs">
                                        @foreach($valueTypes as $vt)
                                            <option value="{{ $vt->value }}">{{ ucfirst($vt->value) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Default Value:</label>
                                    <input type="text" name="default_value" x-model="editFeature.default_value" class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-mono">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Sort Order:</label>
                                    <input type="number" name="sort_order" x-model="editFeature.sort_order" class="w-full rounded-xl border-slate-300 py-1.5 text-xs">
                                </div>
                                <div class="flex items-center pt-5">
                                    <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-slate-700">
                                        <input type="checkbox" name="is_active" value="1" :checked="editFeature.is_active" class="rounded border-slate-300 text-amber-600">
                                        <span>Active</span>
                                    </label>
                                </div>
                            </div>

                            <div class="pt-3 border-t flex justify-end gap-2">
                                <button type="button" @click="editFeature = null" class="px-3 py-1.5 rounded-xl border text-slate-600 hover:bg-slate-50">Cancel</button>
                                <button type="submit" class="px-4 py-1.5 rounded-xl bg-amber-600 text-white font-bold hover:bg-amber-700">Update Feature</button>
                            </div>
                        </form>
                    </template>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

