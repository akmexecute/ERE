<x-app-layout>
    <x-slot name="title">{{ $collection->name }} — Collection | Ethiopian Real Estate Marketplace</x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <nav class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                    <a href="{{ route('collections.index') }}" class="text-teal-800 hover:underline">Collections</a>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold">{{ $collection->name }}</span>
                </nav>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">
                        {{ $collection->name }}
                    </h1>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $collection->is_private ? 'bg-slate-100 text-slate-700' : 'bg-emerald-50 text-emerald-800' }}">
                        {{ $collection->is_private ? '🔒 Private' : '🌐 Public' }}
                    </span>
                </div>
                @if($collection->description)
                    <p class="text-xs text-slate-600 mt-1 max-w-2xl">
                        {{ $collection->description }}
                    </p>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if($collection->items->isNotEmpty())
                    @php
                        $itemPropertyIds = $collection->items->pluck('listing.property_id')->filter()->unique()->take(4)->all();
                    @endphp
                    <a
                        href="{{ route('marketplace.compare', ['ids' => $itemPropertyIds]) }}"
                        class="px-4 py-2 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-xs font-semibold shadow-xs transition flex items-center gap-1.5"
                    >
                        <span>⚖️</span>
                        <span>Compare All in Matrix</span>
                    </a>
                @endif
                <a
                    href="{{ url('/search') }}"
                    class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition"
                >
                    🔍 Find More Properties
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        @if($collection->items->isEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center max-w-md mx-auto space-y-4 shadow-xs my-8">
                <div class="w-16 h-16 rounded-full bg-teal-50 text-teal-800 flex items-center justify-center text-3xl mx-auto">
                    📂
                </div>
                <h2 class="text-lg font-bold text-slate-900">No Properties in Collection</h2>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Browse verified marketplace listings to add properties and personal evaluation notes to this collection.
                </p>
                <div class="pt-2">
                    <a
                        href="{{ url('/search') }}"
                        class="inline-block px-5 py-2.5 rounded-xl bg-teal-800 text-white text-xs font-bold hover:bg-teal-900 transition shadow-xs"
                    >
                        Explore Listings
                    </a>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($collection->items as $item)
                    @php
                        $listing = $item->listing;
                        $property = $listing?->property;
                        $coverImage = $property?->images?->first();
                    @endphp
                    @if($listing && $property)
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden flex flex-col justify-between hover:shadow-md transition">
                            <div>
                                <!-- Image Thumbnail -->
                                <div class="h-44 relative bg-slate-100 overflow-hidden group">
                                    @if($coverImage)
                                        <img
                                            src="{{ asset('storage/' . $coverImage->original_sanitized_path) }}"
                                            alt="{{ $property->title_en }}"
                                            class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                        >
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-3xl text-slate-300">
                                            🏢
                                        </div>
                                    @endif

                                    <div class="absolute top-3 left-3">
                                        <x-verification.badge :level="$property->verification_level" />
                                    </div>

                                    <!-- Remove Item Form -->
                                    <form method="POST" action="{{ route('collections.items.destroy', ['uuid' => $collection->uuid, 'itemId' => $item->id]) }}" class="absolute top-3 right-3">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="w-8 h-8 rounded-full bg-white/90 hover:bg-white text-slate-500 hover:text-rose-600 flex items-center justify-center shadow-xs transition"
                                            title="Remove from collection"
                                        >
                                            ✕
                                        </button>
                                    </form>
                                </div>

                                <!-- Card Body -->
                                <div class="p-5 space-y-2">
                                    <div class="flex items-center justify-between text-xs text-slate-400 font-mono">
                                        <span>{{ $property->property_code }}</span>
                                        <span class="uppercase font-sans font-semibold text-teal-800 bg-teal-50 px-2 py-0.5 rounded text-[10px]">
                                            {{ $listing->transaction_mode }}
                                        </span>
                                    </div>

                                    <h3 class="text-sm font-bold text-slate-900 leading-snug line-clamp-1">
                                        {{ $property->title_en }}
                                    </h3>

                                    <p class="text-xs text-slate-500">
                                        📍 {{ $property->subcity?->name_en ?? 'Bole' }}, {{ $property->city?->name_en ?? 'Addis Ababa' }}
                                    </p>

                                    <div class="pt-2">
                                        <div class="text-base font-extrabold text-teal-900">
                                            {{ \App\Support\Money\EtbFormatter::format($listing->price_etb) }}
                                        </div>
                                        @if($listing->price_per_sqm_etb)
                                            <div class="text-[11px] text-teal-700 font-semibold">
                                                {{ \App\Support\Money\EtbFormatter::format($listing->price_per_sqm_etb) }} / m²
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Personal Note up to 300 characters -->
                                    @if($item->personal_note)
                                        <div class="mt-3 p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-[11px] text-amber-900 leading-relaxed">
                                            <strong>Note:</strong> {{ $item->personal_note }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Card Footer Actions -->
                            <div class="p-4 border-t border-slate-100 grid grid-cols-2 gap-2 bg-slate-50/50">
                                <a
                                    href="{{ route('marketplace.compare', ['ids' => [$property->id]]) }}"
                                    class="py-2 px-3 rounded-xl bg-white border border-slate-200 text-slate-700 text-xs font-semibold text-center hover:bg-slate-100 transition"
                                >
                                    ⚖️ Compare
                                </a>
                                <a
                                    href="{{ url('/listings/' . $listing->slug) }}"
                                    class="py-2 px-3 rounded-xl bg-teal-800 text-white text-xs font-bold text-center hover:bg-teal-900 transition shadow-xs"
                                >
                                    View Details
                                </a>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
