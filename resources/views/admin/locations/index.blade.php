<x-app-layout>
    <div
        x-data="{
            showAddModal: false,
            showDeactivateModal: false,
            deactivateTargetId: null,
            deactivateTargetName: '',
            deactivateIsActive: true,
            deactivateReferencedCount: 0,
            deactivateReason: '',
            openDeactivateModal(id, name, isActive, refCount) {
                this.deactivateTargetId = id;
                this.deactivateTargetName = name;
                this.deactivateIsActive = isActive;
                this.deactivateReferencedCount = refCount;
                this.deactivateReason = '';
                this.showDeactivateModal = true;
            }
        }"
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6"
    >
        <!-- Urbano Header: Architectural Slate-900 Bar with Subtle Borders -->
        <div class="rounded-3xl bg-slate-900 text-white p-6 sm:p-8 border border-slate-800 shadow-xl relative overflow-hidden">
            <div class="relative z-10 flex flex-wrap items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-teal-400">
                        <a href="{{ url('/admin/dashboard') }}" class="hover:underline text-teal-300">Admin Operations</a>
                        <span class="text-slate-600">/</span>
                        <span>Module 02: Locations &amp; GIS</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight mt-1 text-white">
                        Ethiopian Administrative Location Hierarchy
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-2xl">
                        Manage 8-level national hierarchy: Country → Region → Zone → City → Sub-city → Woreda → Kebele → Neighborhood. Referenced locations are protected from permanent deletion.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="showAddModal = true"
                        class="px-4 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-semibold shadow-md transition flex items-center gap-2"
                    >
                        <span>+</span>
                        <span>Add Location</span>
                    </button>
                    <a
                        href="{{ url('/search') }}"
                        target="_blank"
                        class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold transition"
                    >
                        Marketplace Map ↗
                    </a>
                </div>
            </div>
        </div>

        @if(session('status'))
            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-950 text-xs font-semibold flex items-center gap-2 shadow-xs">
                <span class="text-emerald-700 text-sm">✓</span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-950 text-xs font-medium space-y-1 shadow-xs">
                <p class="font-bold">Error:</p>
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Filter & Search Toolbar -->
        <div class="bg-white rounded-2xl border border-slate-200/90 p-4 shadow-xs space-y-3.5">
            <form method="GET" action="{{ route('admin.locations') }}" class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <!-- Search Input -->
                <div class="relative w-full sm:w-80">
                    <input
                        type="text"
                        name="search"
                        value="{{ $currentSearch }}"
                        placeholder="Search name, code, or slug..."
                        class="w-full pl-9 pr-4 py-2 text-xs rounded-xl border border-slate-300 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20"
                    >
                    <span class="absolute left-3 top-2.5 text-slate-400">🔍</span>
                </div>

                <!-- Level & Status Filter Selectors -->
                <div class="flex items-center gap-2 w-full sm:w-auto flex-wrap">
                    <select name="level" class="px-3 py-1.5 rounded-xl border border-slate-300 text-xs">
                        <option value="all" {{ $currentLevel === 'all' ? 'selected' : '' }}>All Levels</option>
                        @foreach($levels as $lvl)
                            <option value="{{ $lvl }}" {{ $currentLevel === $lvl ? 'selected' : '' }}>
                                Level: {{ ucfirst($lvl) }}
                            </option>
                        @endforeach
                    </select>

                    <select name="status" class="px-3 py-1.5 rounded-xl border border-slate-300 text-xs">
                        <option value="all" {{ $currentStatus === 'all' ? 'selected' : '' }}>All Status</option>
                        <option value="active" {{ $currentStatus === 'active' ? 'selected' : '' }}>Active Only</option>
                        <option value="inactive" {{ $currentStatus === 'inactive' ? 'selected' : '' }}>Deactivated Only</option>
                    </select>

                    <button type="submit" class="px-4 py-1.5 rounded-xl bg-slate-900 text-white font-semibold hover:bg-slate-800 transition">
                        Filter
                    </button>
                    @if($currentSearch || $currentLevel !== 'all' || $currentStatus !== 'all')
                        <a href="{{ route('admin.locations') }}" class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 font-medium">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Locations Hierarchy Table -->
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">National Geographic Hierarchy Catalogue</h2>
                    <span class="text-xs text-slate-500">Showing page {{ $locations->currentPage() }} of {{ $locations->lastPage() }} ({{ $locations->total() }} total entries)</span>
                </div>
                <span class="text-xs text-slate-400 font-mono">PostGIS WGS-84 SRID 4326</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50/70 border-b border-slate-200 text-slate-600 uppercase font-semibold text-[11px]">
                            <th class="py-3 px-4">Name (EN / AM / OM)</th>
                            <th class="py-3 px-3">Level</th>
                            <th class="py-3 px-3">Code / Slug</th>
                            <th class="py-3 px-3">Parent Location</th>
                            <th class="py-3 px-3">Coordinates</th>
                            <th class="py-3 px-3">References</th>
                            <th class="py-3 px-3">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($locations as $loc)
                            @php
                                $refCount = $loc->referencedPropertiesCount();
                            @endphp
                            <tr class="hover:bg-slate-50/70 transition" :class="!{{ $loc->is_active ? 'true' : 'false' }} ? 'bg-slate-50/40 text-slate-400' : ''">
                                <!-- Names -->
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900">{{ $loc->name_en }}</div>
                                    <div class="text-[11px] text-slate-500 font-['Noto_Sans_Ethiopic']">{{ $loc->name_am ?? '—' }} &bull; {{ $loc->name_om ?? '—' }}</div>
                                </td>

                                <!-- Level -->
                                <td class="py-3 px-3">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase
                                        {{ $loc->level === 'country' ? 'bg-purple-100 text-purple-900' : '' }}
                                        {{ $loc->level === 'region' ? 'bg-blue-100 text-blue-900' : '' }}
                                        {{ $loc->level === 'city' ? 'bg-teal-100 text-teal-900' : '' }}
                                        {{ $loc->level === 'subcity' ? 'bg-emerald-100 text-emerald-900' : '' }}
                                        {{ $loc->level === 'neighborhood' ? 'bg-amber-100 text-amber-900' : '' }}
                                        {{ in_array($loc->level, ['zone', 'woreda', 'kebele']) ? 'bg-slate-100 text-slate-800' : '' }}
                                    ">
                                        {{ $loc->level }}
                                    </span>
                                </td>

                                <!-- Code & Slug -->
                                <td class="py-3 px-3 font-mono">
                                    <div class="font-semibold text-slate-800">{{ $loc->code }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $loc->slug }}</div>
                                </td>

                                <!-- Parent -->
                                <td class="py-3 px-3">
                                    @if($loc->parent)
                                        <div class="font-medium text-slate-800">{{ $loc->parent->name_en }}</div>
                                        <span class="text-[10px] text-slate-400 uppercase font-mono">{{ $loc->parent->level }}</span>
                                    @else
                                        <span class="text-slate-400 italic">Root Entity</span>
                                    @endif
                                </td>

                                <!-- Coordinates -->
                                <td class="py-3 px-3 font-mono text-[11px] text-slate-600">
                                    @if($loc->latitude && $loc->longitude)
                                        <span>{{ number_format((float) $loc->latitude, 4) }}, {{ number_format((float) $loc->longitude, 4) }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                <!-- References -->
                                <td class="py-3 px-3">
                                    @if($refCount > 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-teal-800 border border-teal-200">
                                            <span>🏠</span>
                                            <span>{{ $refCount }} properties</span>
                                        </span>
                                    @else
                                        <span class="text-[11px] text-slate-400">0 references</span>
                                    @endif
                                </td>

                                <!-- Active Status -->
                                <td class="py-3 px-3">
                                    @if($loc->is_active)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                            Active
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-200 text-slate-700">
                                            Deactivated
                                        </span>
                                    @endif
                                </td>

                                <!-- Actions -->
                                <td class="py-3 px-4 text-right">
                                    <button
                                        type="button"
                                        @click="openDeactivateModal({{ $loc->id }}, '{{ addslashes($loc->name_en) }}', {{ $loc->is_active ? 'true' : 'false' }}, {{ $refCount }})"
                                        class="px-2.5 py-1 rounded-xl text-xs font-semibold transition border shadow-2xs
                                            {{ $loc->is_active ? 'bg-white hover:bg-slate-50 text-amber-800 border-amber-200' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border-emerald-200' }}
                                        "
                                    >
                                        {{ $loc->is_active ? 'Deactivate' : 'Reactivate' }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-slate-400">
                                    No administrative locations match the specified filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($locations->hasPages())
                <div class="px-6 py-3 border-t border-slate-100 bg-slate-50/50">
                    {{ $locations->links() }}
                </div>
            @endif
        </div>

        <!-- Add Location Modal -->
        <div
            x-show="showAddModal"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div
                @click.away="showAddModal = false"
                class="bg-white rounded-3xl border border-slate-200 max-w-lg w-full p-6 space-y-4 shadow-2xl max-h-[90vh] overflow-y-auto"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Add Administrative Location</h3>
                    <button type="button" @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form method="POST" action="{{ route('admin.locations.store') }}" class="space-y-3.5 text-xs">
                    @csrf

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Administrative Level</label>
                            <select name="level" required class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs">
                                @foreach($levels as $lvl)
                                    @if($lvl !== 'country')
                                        <option value="{{ $lvl }}">{{ ucfirst($lvl) }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Parent Location</label>
                            <select name="parent_id" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs">
                                <option value="">Select Parent...</option>
                                @foreach($candidateParents as $cp)
                                    <option value="{{ $cp->id }}">{{ $cp->name_en }} ({{ strtoupper($cp->level) }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Identifier Code (e.g. BOLE)</label>
                            <input type="text" name="code" required placeholder="e.g. GERJI" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs font-mono uppercase">
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">English Name</label>
                            <input type="text" name="name_en" required placeholder="e.g. Gerji" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Amharic Name (አማርኛ)</label>
                            <input type="text" name="name_am" placeholder="e.g. ገርጂ" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs font-['Noto_Sans_Ethiopic']">
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Afaan Oromo Name</label>
                            <input type="text" name="name_om" placeholder="e.g. Garjii" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Latitude</label>
                            <input type="number" step="any" name="latitude" placeholder="e.g. 8.9965" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs font-mono">
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Longitude</label>
                            <input type="number" step="any" name="longitude" placeholder="e.g. 38.8095" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs font-mono">
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="showAddModal = false" class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 text-xs font-semibold">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold shadow-sm">
                            Save Location
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Safe Deactivation Modal (SRS Section 176 Protection) -->
        <div
            x-show="showDeactivateModal"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div
                @click.away="showDeactivateModal = false"
                class="bg-white rounded-3xl border border-slate-200 max-w-md w-full p-6 space-y-4 shadow-2xl"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900" x-text="deactivateIsActive ? 'Safe Deactivation' : 'Reactivate Location'"></h3>
                    <button type="button" @click="showDeactivateModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form
                    method="POST"
                    :action="'/admin/locations/' + deactivateTargetId + '/deactivate'"
                    class="space-y-3.5 text-xs"
                >
                    @csrf

                    <div>
                        <span class="text-slate-500">Location:</span>
                        <div class="text-sm font-bold text-slate-900" x-text="deactivateTargetName"></div>
                    </div>

                    <template x-if="deactivateReferencedCount > 0">
                        <div class="p-3 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs space-y-1">
                            <div class="font-bold">⚠️ Protected From Permanent Deletion</div>
                            <div>
                                This location is referenced by <strong x-text="deactivateReferencedCount"></strong> property records. It will be hidden from public selection while preserving all historical property links and audit trails.
                            </div>
                        </div>
                    </template>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Administrative Justification / Reason</label>
                        <textarea
                            name="reason"
                            x-model="deactivateReason"
                            required
                            rows="2"
                            placeholder="State reason for deactivation or status change..."
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="showDeactivateModal = false" class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 text-xs font-semibold">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="!deactivateReason"
                            class="px-4 py-2 rounded-xl font-semibold text-xs shadow-sm"
                            :class="deactivateIsActive ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-teal-700 hover:bg-teal-800 text-white'"
                            x-text="deactivateIsActive ? 'Confirm Deactivation' : 'Confirm Reactivation'"
                        >
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
