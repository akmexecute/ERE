<x-app-layout>
    <div
        x-data="{
            showAddSceneModal: false,
            showAddHotspotModal: false,
            selectedSourceSceneId: {{ $tour?->scenes?->first()?->id ?? 'null' }}
        }"
        class="bg-slate-50 min-h-screen py-8 px-4 sm:px-6 lg:px-8 text-slate-800"
    >
        <div class="max-w-6xl mx-auto space-y-6">

            <!-- Breadcrumbs & Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                        <a href="{{ route('dashboard') }}" class="hover:underline">Dashboard</a>
                        <span>/</span>
                        <a href="{{ route('listings.show', $property->listing?->slug ?? $property->id) }}" class="hover:underline">Property #{{ $property->id }}</a>
                        <span>/</span>
                        <span class="text-slate-800 font-semibold">360° Virtual Tour Studio</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900">Virtual Tour Workspace</h1>
                    <p class="text-xs text-slate-500">Property: <strong>{{ $property->title_en }}</strong> &middot; Sub-city: {{ $property->neighborhood?->name_en ?? 'Addis Ababa' }}</p>
                </div>

                @if($tour)
                    <div class="flex items-center gap-2">
                        <a
                            href="{{ route('virtual-tours.show', $tour->uuid) }}"
                            target="_blank"
                            class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold transition shadow-sm flex items-center gap-1.5"
                        >
                            <span>🌐</span> View Public 360° Tour &rarr;
                        </a>
                    </div>
                @endif
            </div>

            @if(session('success'))
                <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 font-semibold">
                    {{ session('success') }}
                </div>
            @endif

            @if(! $tour)
                <!-- Step 1: Create Virtual Tour Header -->
                <div class="bg-white rounded-2xl border border-slate-200 p-8 max-w-xl mx-auto shadow-sm space-y-5">
                    <div class="text-center space-y-2">
                        <div class="text-4xl">📸</div>
                        <h2 class="text-lg font-bold text-slate-900">Enable 360° Virtual Tour for this Property</h2>
                        <p class="text-xs text-slate-500">Create an interactive room-to-room walkthrough with equirectangular panoramas.</p>
                    </div>

                    <form method="POST" action="{{ route('virtual-tours.store', $property) }}" class="space-y-4 text-xs">
                        @csrf
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Tour Title:</label>
                            <input type="text" name="title" value="{{ $property->title_en }} - 360° Virtual Tour" required class="w-full rounded-xl border-slate-300 py-2 text-xs">
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Brief Description (Optional):</label>
                            <textarea name="description" rows="2" placeholder="Experience the spacious living room, master suite, and balcony views..." class="w-full rounded-xl border-slate-300 py-2 text-xs"></textarea>
                        </div>

                        <div class="pt-3 border-t">
                            <button type="submit" class="w-full py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shadow-sm transition">
                                Create Tour Workspace &rarr;
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <!-- Step 2: Manage Tour Scenes & Hotspots -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                    <!-- Left: Scenes List (8 cols) -->
                    <div class="lg:col-span-8 space-y-6">
                        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                                <div>
                                    <h2 class="text-base font-bold text-slate-900">Room Scenes ({{ $tour->scenes->count() }})</h2>
                                    <p class="text-xs text-slate-500">Add equirectangular 360° panorama images for each room.</p>
                                </div>
                                <button
                                    type="button"
                                    @click="showAddSceneModal = true"
                                    class="px-3.5 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition"
                                >
                                    + Upload Room Scene
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @forelse($tour->scenes as $scene)
                                    <div class="rounded-xl border border-slate-200 overflow-hidden bg-slate-50 flex flex-col justify-between">
                                        <div class="relative h-36 bg-slate-800">
                                            <img src="{{ asset('storage/' . $scene->panorama_image_path) }}" alt="{{ $scene->room_name }}" class="w-full h-full object-cover">
                                            <div class="absolute top-2 left-2 px-2 py-0.5 rounded bg-slate-900/80 text-white text-[10px] font-mono">
                                                {{ $scene->room_name }}
                                            </div>
                                            @if($scene->is_default_entry)
                                                <div class="absolute top-2 right-2 px-2 py-0.5 rounded bg-emerald-600 text-white text-[10px] font-bold">
                                                    Entry Scene
                                                </div>
                                            @endif
                                        </div>

                                        <div class="p-3 space-y-2 text-xs">
                                            <div class="flex items-center justify-between text-[11px] text-slate-500">
                                                <span>Hotspots linked: <strong>{{ $scene->hotspots->count() }}</strong></span>
                                                <span>Order: {{ $scene->sort_order }}</span>
                                            </div>

                                            <div class="pt-2 border-t border-slate-200 flex items-center justify-between">
                                                <button
                                                    type="button"
                                                    @click="selectedSourceSceneId = {{ $scene->id }}; showAddHotspotModal = true"
                                                    class="text-xs text-teal-700 font-bold hover:underline"
                                                >
                                                    + Link Hotspot
                                                </button>

                                                <form method="POST" action="{{ route('virtual-tours.scenes.destroy', $scene) }}" onsubmit="return confirm('Delete this room scene?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-rose-600 hover:underline text-xs">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-span-2 p-8 text-center bg-slate-50 rounded-xl border border-dashed border-slate-300 text-slate-500 text-xs">
                                        No room scenes uploaded yet. Click "+ Upload Room Scene" to add your first 360° panorama image.
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <!-- Hotspots Network Overview -->
                        @if($tour->scenes->count() > 1)
                            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-3">
                                <h3 class="text-sm font-bold text-slate-900">Room-to-Room Navigation Hotspots</h3>
                                <div class="divide-y divide-slate-100 text-xs">
                                    @foreach($tour->scenes as $scene)
                                        @foreach($scene->hotspots as $hotspot)
                                            <div class="py-2 flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-semibold text-slate-800">{{ $scene->room_name }}</span>
                                                    <span class="text-slate-400">&rarr;</span>
                                                    <span class="font-semibold text-teal-700">{{ $hotspot->targetScene?->room_name ?? 'Target Room' }}</span>
                                                    <span class="text-slate-400 text-[11px]">({{ $hotspot->title }})</span>
                                                </div>
                                                <span class="font-mono text-slate-500 text-[10px]">
                                                    Pitch: {{ $hotspot->pitch }}°, Yaw: {{ $hotspot->yaw }}°
                                                </span>
                                            </div>
                                        @endforeach
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Right: Tour Details & Public Preview (4 cols) -->
                    <div class="lg:col-span-4 space-y-4">
                        <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-3 shadow-xs">
                            <h3 class="text-sm font-bold text-slate-900">Public Access Status</h3>
                            <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 space-y-1">
                                <div class="font-bold">Public Viewing Granted</div>
                                <div>Anyone visiting the platform can view this tour without requiring authentication or a subscription.</div>
                            </div>

                            <div class="text-xs space-y-1 pt-2 border-t text-slate-600">
                                <div>Tour UUID: <span class="font-mono text-[11px] text-slate-800">{{ $tour->uuid }}</span></div>
                                <div>Created: {{ $tour->created_at->format('d M Y') }}</div>
                            </div>

                            <a
                                href="{{ route('virtual-tours.show', $tour->uuid) }}"
                                target="_blank"
                                class="w-full block text-center py-2.5 rounded-xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition"
                            >
                                Open Interactive Viewer &rarr;
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Upload Room Scene Modal -->
                <div
                    x-show="showAddSceneModal"
                    x-cloak
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
                >
                    <div class="bg-white rounded-2xl border border-slate-200 max-w-md w-full p-6 space-y-4 shadow-xl" @click.outside="showAddSceneModal = false">
                        <div class="flex items-center justify-between border-b pb-3">
                            <h3 class="text-base font-bold text-slate-900">Upload Room Panorama</h3>
                            <button type="button" @click="showAddSceneModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                        </div>

                        <form method="POST" action="{{ route('virtual-tours.scenes.store', $tour) }}" enctype="multipart/form-data" class="space-y-3 text-xs">
                            @csrf
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Room Name:</label>
                                <input type="text" name="room_name" required placeholder="e.g. Master Bedroom, Balcony, Living Room" class="w-full rounded-xl border-slate-300 py-1.5 text-xs">
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Equirectangular 360° Photo (JPG, PNG, WebP):</label>
                                <input type="file" name="panorama_file" accept="image/*" required class="w-full text-xs text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
                                <div class="text-[10px] text-slate-400 mt-1">Recommended 2:1 aspect ratio (e.g. 4096x2048, 6000x3000). Max 25MB.</div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Initial Pitch (-90° to 90°):</label>
                                    <input type="number" step="0.1" name="initial_pitch" value="0.0" class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-mono">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Initial Yaw (-180° to 180°):</label>
                                    <input type="number" step="0.1" name="initial_yaw" value="0.0" class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-mono">
                                </div>
                            </div>

                            <div class="pt-3 border-t flex justify-end gap-2">
                                <button type="button" @click="showAddSceneModal = false" class="px-3 py-1.5 rounded-xl border text-slate-600 hover:bg-slate-50">Cancel</button>
                                <button type="submit" class="px-4 py-1.5 rounded-xl bg-amber-600 text-white font-bold hover:bg-amber-700">Upload Scene</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Add Hotspot Modal -->
                <div
                    x-show="showAddHotspotModal"
                    x-cloak
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
                >
                    <div class="bg-white rounded-2xl border border-slate-200 max-w-md w-full p-6 space-y-4 shadow-xl" @click.outside="showAddHotspotModal = false">
                        <div class="flex items-center justify-between border-b pb-3">
                            <h3 class="text-base font-bold text-slate-900">Link Navigation Hotspot</h3>
                            <button type="button" @click="showAddHotspotModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                        </div>

                        <template x-if="selectedSourceSceneId">
                            <form :action="'/virtual-tour-scenes/' + selectedSourceSceneId + '/hotspots'" method="POST" class="space-y-3 text-xs">
                                @csrf
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Target Room Scene:</label>
                                    <select name="target_scene_id" required class="w-full rounded-xl border-slate-300 py-1.5 text-xs">
                                        <option value="">-- Select Destination Room --</option>
                                        @foreach($tour->scenes as $s)
                                            <option value="{{ $s->id }}">{{ $s->room_name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Hotspot Label (Tooltip):</label>
                                    <input type="text" name="title" placeholder="e.g. Walk to Kitchen" class="w-full rounded-xl border-slate-300 py-1.5 text-xs">
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block font-semibold text-slate-700 mb-1">Pitch (-90° to 90°):</label>
                                        <input type="number" step="0.5" name="pitch" value="-5.0" required class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-mono">
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-700 mb-1">Yaw (-180° to 180°):</label>
                                        <input type="number" step="0.5" name="yaw" value="30.0" required class="w-full rounded-xl border-slate-300 py-1.5 text-xs font-mono">
                                    </div>
                                </div>

                                <div class="pt-3 border-t flex justify-end gap-2">
                                    <button type="button" @click="showAddHotspotModal = false" class="px-3 py-1.5 rounded-xl border text-slate-600 hover:bg-slate-50">Cancel</button>
                                    <button type="submit" class="px-4 py-1.5 rounded-xl bg-teal-700 text-white font-bold hover:bg-teal-800">Add Hotspot</button>
                                </div>
                            </form>
                        </template>
                    </div>
                </div>

            @endif

        </div>
    </div>
</x-app-layout>

