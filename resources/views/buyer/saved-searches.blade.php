<x-app-layout>
    <x-slot name="title">Saved Search Alerts — Ethiopian Real Estate Marketplace</x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <nav class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                    <a href="{{ url('/search') }}" class="text-teal-800 hover:underline">Marketplace</a>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold">Search Alerts</span>
                </nav>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">
                    Saved Search Alerts
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Receive instant, daily, or weekly notifications whenever new listings match your saved filters.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a
                    href="{{ url('/search') }}"
                    class="px-4 py-2 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-xs font-semibold shadow-xs transition flex items-center gap-1.5"
                >
                    <span>🔍</span>
                    <span>Search Marketplace</span>
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        @if($searches->isEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center max-w-md mx-auto space-y-4 shadow-xs my-8">
                <div class="w-16 h-16 rounded-full bg-sky-50 text-sky-700 flex items-center justify-center text-3xl mx-auto">
                    🔔
                </div>
                <h2 class="text-lg font-bold text-slate-900">No Saved Search Alerts</h2>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Set up search alerts from the marketplace filter screen to get notified whenever a matching property is published.
                </p>
                <div class="pt-2">
                    <a
                        href="{{ url('/search') }}"
                        class="inline-block px-5 py-2.5 rounded-xl bg-teal-800 text-white text-xs font-bold hover:bg-teal-900 transition shadow-xs"
                    >
                        Browse &amp; Save a Search
                    </a>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($searches as $search)
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 flex flex-col justify-between hover:shadow-md transition space-y-4">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-xs">
                                <span class="px-2.5 py-0.5 rounded-full font-bold uppercase tracking-wider text-[10px] {{ $search->frequency === 'instant' ? 'bg-rose-50 text-rose-700' : ($search->frequency === 'daily' ? 'bg-teal-50 text-teal-800' : 'bg-slate-100 text-slate-700') }}">
                                    ⏱️ {{ $search->frequency }} Alert
                                </span>
                                <span class="flex items-center gap-1 text-[11px] {{ $search->is_active ? 'text-emerald-700 font-semibold' : 'text-slate-400' }}">
                                    <span class="w-2 h-2 rounded-full {{ $search->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                    <span>{{ $search->is_active ? 'Active' : 'Paused' }}</span>
                                </span>
                            </div>

                            <h3 class="text-base font-bold text-slate-900 leading-snug">
                                {{ $search->name }}
                            </h3>

                            <!-- Filters summary -->
                            <div class="p-3 bg-slate-50 rounded-xl space-y-1 text-xs">
                                @php
                                    $filters = $search->filters_json ?? [];
                                @endphp
                                @if(!empty($filters['city']))
                                    <div class="flex justify-between text-slate-600">
                                        <span class="text-slate-400">Location:</span>
                                        <span class="font-semibold text-slate-800">{{ $filters['city'] }}</span>
                                    </div>
                                @endif
                                @if(!empty($filters['transaction_mode']))
                                    <div class="flex justify-between text-slate-600">
                                        <span class="text-slate-400">Mode:</span>
                                        <span class="font-semibold text-slate-800 uppercase">{{ $filters['transaction_mode'] }}</span>
                                    </div>
                                @endif
                                @if(!empty($filters['max_price']))
                                    <div class="flex justify-between text-slate-600">
                                        <span class="text-slate-400">Max Price:</span>
                                        <span class="font-semibold text-slate-800">{{ \App\Support\Money\EtbFormatter::format((float)$filters['max_price']) }}</span>
                                    </div>
                                @endif
                                @if(!empty($filters['bedrooms']))
                                    <div class="flex justify-between text-slate-600">
                                        <span class="text-slate-400">Bedrooms:</span>
                                        <span class="font-semibold text-slate-800">{{ $filters['bedrooms'] }}+ Beds</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                            <span class="text-slate-400 text-[11px]">
                                {{ $search->last_notified_at ? 'Alerted ' . $search->last_notified_at->diffForHumans(null, true) : 'No alerts yet' }}
                            </span>

                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('saved-searches.destroy', $search->uuid) }}" onsubmit="return confirm('Delete this search alert?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-slate-400 hover:text-rose-600 transition" title="Delete Alert">
                                        🗑️
                                    </button>
                                </form>

                                <a
                                    href="{{ url('/search?' . http_build_query($search->filters_json ?? [])) }}"
                                    class="px-3 py-1.5 rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-800 font-semibold transition"
                                >
                                    Run Search →
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
