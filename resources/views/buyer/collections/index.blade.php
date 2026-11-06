<x-app-layout>
    <x-slot name="title">Private Collections — Ethiopian Real Estate Marketplace</x-slot>

    <div
        x-data="{ createModalOpen: false }"
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6"
    >
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <nav class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                    <a href="{{ url('/search') }}" class="text-teal-800 hover:underline">Marketplace</a>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold">Collections</span>
                </nav>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">
                    Private Property Collections
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Curate and organize properties into private or shared project folders with personal evaluation notes.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="createModalOpen = true"
                    class="px-4 py-2 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-xs font-semibold shadow-xs transition flex items-center gap-1.5"
                >
                    <span>➕</span>
                    <span>New Collection</span>
                </button>
            </div>
        </div>

        @if (session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        @if($collections->isEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center max-w-md mx-auto space-y-4 shadow-xs my-8">
                <div class="w-16 h-16 rounded-full bg-teal-50 text-teal-800 flex items-center justify-center text-3xl mx-auto">
                    📁
                </div>
                <h2 class="text-lg font-bold text-slate-900">No Collections Yet</h2>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Create custom collections like "Bole Family Villas", "Commercial Investments", or "Shortlisted Rentals" to organize your research.
                </p>
                <div class="pt-2">
                    <button
                        type="button"
                        @click="createModalOpen = true"
                        class="px-5 py-2.5 rounded-xl bg-teal-800 text-white text-xs font-bold hover:bg-teal-900 transition shadow-xs"
                    >
                        Create Your First Collection
                    </button>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($collections as $col)
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 flex flex-col justify-between hover:shadow-md transition space-y-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="px-2 py-0.5 rounded-full {{ $col->is_private ? 'bg-slate-100 text-slate-700' : 'bg-emerald-50 text-emerald-800' }} font-medium">
                                    {{ $col->is_private ? '🔒 Private' : '🌐 Public' }}
                                </span>
                                <span class="text-slate-400 font-medium">
                                    {{ $col->items_count }} {{ Str::plural('item', $col->items_count) }}
                                </span>
                            </div>

                            <h3 class="text-base font-bold text-slate-900 leading-snug">
                                <a href="{{ route('collections.show', $col->uuid) }}" class="hover:text-teal-800 transition">
                                    {{ $col->name }}
                                </a>
                            </h3>

                            @if($col->description)
                                <p class="text-xs text-slate-500 line-clamp-2">
                                    {{ $col->description }}
                                </p>
                            @endif
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs">
                            <span class="text-slate-400 text-[11px]">
                                {{ $col->created_at->format('M d, Y') }}
                            </span>

                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('collections.destroy', $col->uuid) }}" onsubmit="return confirm('Delete this collection?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-slate-400 hover:text-rose-600 transition" title="Delete Collection">
                                        🗑️
                                    </button>
                                </form>

                                <a
                                    href="{{ route('collections.show', $col->uuid) }}"
                                    class="px-3 py-1.5 rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-800 font-semibold transition"
                                >
                                    Open →
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Create Collection Modal -->
        <div
            x-show="createModalOpen"
            x-cloak
            @keydown.escape.window="createModalOpen = false"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div
                @click.away="createModalOpen = false"
                class="bg-white rounded-2xl max-w-md w-full p-6 text-left shadow-2xl border border-slate-200 space-y-4"
            >
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-1.5">
                        <span>📁</span>
                        <span>Create New Collection</span>
                    </h3>
                    <button type="button" @click="createModalOpen = false" class="text-slate-400 hover:text-slate-700 text-lg">✕</button>
                </div>

                <form method="POST" action="{{ route('collections.store') }}" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Collection Name</label>
                        <input
                            type="text"
                            name="name"
                            required
                            maxlength="120"
                            placeholder="e.g. Bole Medhanialem 4-Bed Shortlist"
                            class="w-full rounded-xl border-slate-300 text-xs py-2 px-3 focus:border-teal-700 focus:ring-teal-700"
                        >
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Description (Optional)</label>
                        <textarea
                            name="description"
                            rows="2"
                            maxlength="255"
                            placeholder="Brief purpose of this collection..."
                            class="w-full rounded-xl border-slate-300 text-xs p-2.5 focus:border-teal-700 focus:ring-teal-700"
                        ></textarea>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            name="is_private"
                            value="1"
                            checked
                            id="is_private"
                            class="rounded border-slate-300 text-teal-700 focus:ring-teal-700"
                        >
                        <label for="is_private" class="text-slate-700 cursor-pointer">
                            Keep collection private (only visible to you)
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" @click="createModalOpen = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-teal-800 hover:bg-teal-900 text-white font-semibold">
                            Create Collection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
