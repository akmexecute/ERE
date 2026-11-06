<x-app-layout>
    <x-slot name="title">My Saved Listings — Ethiopian Real Estate Marketplace</x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <nav class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                    <a href="{{ url('/search') }}" class="text-teal-800 hover:underline">Marketplace</a>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold">Favorites</span>
                </nav>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">
                    Saved Listings &amp; Favorites
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Manage your bookmarked properties, monitor price updates, and build side-by-side comparisons.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a
                    href="{{ route('collections.index') }}"
                    class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition"
                >
                    📁 Private Collections
                </a>
                <a
                    href="{{ route('saved-searches.index') }}"
                    class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition"
                >
                    🔔 Saved Search Alerts
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        @if($favorites->isEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center max-w-md mx-auto space-y-4 shadow-xs my-8">
                <div class="w-16 h-16 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center text-3xl mx-auto">
                    ❤️
                </div>
                <h2 class="text-lg font-bold text-slate-900">No Favorites Yet</h2>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Click the favorite button on any marketplace listing to save properties for future review and tracking.
                </p>
                <div class="pt-2">
                    <a
                        href="{{ url('/search') }}"
                        class="inline-block px-5 py-2.5 rounded-xl bg-teal-800 text-white text-xs font-bold hover:bg-teal-900 transition shadow-xs"
                    >
                        Browse Verified Listings
                    </a>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($favorites as $fav)
                    @php
                        $listing = $fav->listing;
                        $property = $listing?->property;
                        $coverImage = $property?->images?->first();
                    @endphp
                    @if($listing && $property)
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden flex flex-col justify-between hover:shadow-md transition">
                            <div>
                                <!-- Image Thumbnail -->
                                <div class="h-48 relative bg-slate-100 overflow-hidden group">
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

                                    <!-- Remove Favorite Form -->
                                    <form method="POST" action="{{ route('favorites.destroy', $listing->id) }}" class="absolute top-3 right-3">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="w-8 h-8 rounded-full bg-white/90 hover:bg-white text-rose-600 flex items-center justify-center shadow-xs transition"
                                            title="Remove from favorites"
                                        >
                                            ❤️
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

            <div class="pt-4">
                {{ $favorites->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
