<x-app-layout>
    <x-slot name="title">Seller Operations Dashboard — Ethiopian Real Estate Marketplace</x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-teal-800">
                    Seller Operations &amp; Lead Management
                </span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">
                    Seller Dashboard
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Welcome back, {{ auth()->user()->name }}. Track your active listings, buyer inquiry pipeline, and viewing appointments.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a
                    href="{{ route('messages.inbox') }}"
                    class="px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-xs font-semibold hover:bg-slate-50 shadow-xs transition flex items-center gap-2"
                >
                    <span>💬</span>
                    <span>Inbox</span>
                    @if($unreadMessagesCount > 0)
                        <span class="px-1.5 py-0.5 rounded-full bg-rose-600 text-white font-bold text-[10px]">
                            {{ $unreadMessagesCount }}
                        </span>
                    @endif
                </a>

                <a
                    href="{{ route('listings.create') }}"
                    class="px-4 py-2.5 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-xs font-bold shadow-xs transition flex items-center gap-1.5"
                >
                    <span>➕</span>
                    <span>Publish New Listing</span>
                </a>
            </div>
        </div>

        <!-- Action Required Notice (SRS Section 134) -->
        @if($actionRequiredListings->isNotEmpty())
            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-xs text-amber-900 flex items-start justify-between gap-4">
                <div class="space-y-1">
                    <strong class="font-bold flex items-center gap-1.5 text-amber-950">
                        <span>⚠️</span>
                        <span>Action Required on {{ $actionRequiredListings->count() }} {{ Str::plural('Listing', $actionRequiredListings->count()) }}</span>
                    </strong>
                    <p class="text-amber-800">
                        Some listings are awaiting compliance review or have moderator notes requiring your attention before going live.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @foreach($actionRequiredListings->take(2) as $reqListing)
                        <span class="px-2.5 py-1 rounded-lg bg-amber-200/80 font-mono text-[11px] font-semibold">
                            {{ $reqListing->property?->property_code ?? $reqListing->slug }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Real Database-Backed Seller KPI Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Active Listings</span>
                <div class="text-2xl font-black text-slate-900 mt-1">
                    {{ number_format($activeListingsCount) }}
                </div>
                <span class="text-[11px] text-teal-800 font-medium">Published Live</span>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Draft / Inactive</span>
                <div class="text-2xl font-black text-slate-900 mt-1">
                    {{ number_format($inactiveListingsCount) }}
                </div>
                <span class="text-[11px] text-slate-400 font-medium">Pending Review</span>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Total Inquiries</span>
                <div class="text-2xl font-black text-teal-900 mt-1">
                    {{ number_format($totalInquiries) }}
                </div>
                <span class="text-[11px] text-teal-700 font-medium">Buyer Leads</span>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Unread Messages</span>
                <div class="text-2xl font-black {{ $unreadMessagesCount > 0 ? 'text-rose-600' : 'text-slate-900' }} mt-1">
                    {{ number_format($unreadMessagesCount) }}
                </div>
                <span class="text-[11px] {{ $unreadMessagesCount > 0 ? 'text-rose-600 font-bold' : 'text-slate-400' }} font-medium">
                    {{ $unreadMessagesCount > 0 ? 'Requires Reply' : 'Up to date' }}
                </span>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Listing Views</span>
                <div class="text-2xl font-black text-slate-900 mt-1">
                    {{ number_format($totalViews) }}
                </div>
                <span class="text-[11px] text-teal-800 font-medium">Total Impressions</span>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Saved Favorites</span>
                <div class="text-2xl font-black text-slate-900 mt-1">
                    {{ number_format($totalFavorites) }}
                </div>
                <span class="text-[11px] text-teal-800 font-medium">Buyer Bookmarks</span>
            </div>
        </div>

        <!-- SRS Section 73: Inquiry Pipeline Distribution -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Inquiry Pipeline Funnel (SRS Section 73)</h2>
                    <p class="text-xs text-slate-500">Live lead distribution across negotiation and deal stages</p>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-teal-50 text-teal-800">
                    {{ $totalInquiries }} Total Leads
                </span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 pt-2">
                @php
                    $stages = [
                        'new' => ['label' => '1. New', 'bg' => 'bg-sky-50', 'text' => 'text-sky-800'],
                        'contacted' => ['label' => '2. Contacted', 'bg' => 'bg-indigo-50', 'text' => 'text-indigo-800'],
                        'viewing' => ['label' => '3. Viewing', 'bg' => 'bg-purple-50', 'text' => 'text-purple-800'],
                        'negotiating' => ['label' => '4. Negotiating', 'bg' => 'bg-amber-50', 'text' => 'text-amber-800'],
                        'offer' => ['label' => '5. Offer', 'bg' => 'bg-teal-50', 'text' => 'text-teal-800'],
                        'closed' => ['label' => '6. Closed Deal', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-800'],
                        'lost' => ['label' => '7. Lost', 'bg' => 'bg-slate-100', 'text' => 'text-slate-600'],
                    ];
                @endphp
                @foreach($stages as $key => $meta)
                    <div class="p-3.5 rounded-xl border border-slate-200 {{ $meta['bg'] }} space-y-1">
                        <span class="text-[11px] font-semibold uppercase tracking-wider {{ $meta['text'] }} block">
                            {{ $meta['label'] }}
                        </span>
                        <div class="text-xl font-black text-slate-900">
                            {{ $pipelineDistribution[$key] ?? 0 }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Left 7 Cols: Recent Inquiries -->
            <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Recent Buyer Inquiries</h2>
                        <p class="text-xs text-slate-500">Latest inquiries for your properties</p>
                    </div>
                    <a href="{{ route('messages.inbox') }}" class="text-xs font-semibold text-teal-800 hover:underline">
                        View All in Inbox →
                    </a>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($recentInquiries as $inq)
                        <div class="p-4 flex items-center justify-between gap-4 hover:bg-slate-50/70 transition">
                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="font-mono font-bold text-slate-700">{{ $inq->inquiry_code }}</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $inq->status->value === 'closed' ? 'bg-emerald-100 text-emerald-800' : 'bg-teal-50 text-teal-800' }}">
                                        {{ $inq->status->value }}
                                    </span>
                                    @if($inq->unread_seller_count > 0)
                                        <span class="px-1.5 py-0.2 rounded-full bg-rose-600 text-white font-bold text-[10px]">
                                            {{ $inq->unread_seller_count }} New
                                        </span>
                                    @endif
                                </div>
                                <h4 class="text-xs font-bold text-slate-900 truncate">
                                    {{ $inq->property?->title_en ?? 'Property Inquiry' }}
                                </h4>
                                <p class="text-[11px] text-slate-500">
                                    From: <strong>{{ $inq->buyer?->name ?? 'Buyer' }}</strong> •
                                    {{ $inq->last_message_at ? $inq->last_message_at->diffForHumans(null, true) : 'New' }}
                                </p>
                            </div>

                            <a
                                href="{{ route('messages.inbox', $inq->uuid) }}"
                                class="px-3 py-1.5 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-xs font-semibold shrink-0 transition shadow-xs"
                            >
                                Reply
                            </a>
                        </div>
                    @empty
                        <div class="p-8 text-center text-slate-400 text-xs">
                            No inquiries received yet. Inquiries will appear here when prospective buyers reach out.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Right 5 Cols: Your Listings -->
            <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Your Listings</h2>
                        <p class="text-xs text-slate-500">{{ $activeListingsCount }} Active published</p>
                    </div>
                    <a href="{{ route('listings.create') }}" class="text-xs font-semibold text-teal-800 hover:underline">
                        + New
                    </a>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($recentListings as $listing)
                        @php
                            $prop = $listing->property;
                            $thumb = $prop?->images?->first();
                        @endphp
                        <div class="p-4 flex items-center justify-between gap-3 hover:bg-slate-50/70 transition">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-12 h-12 rounded-xl overflow-hidden bg-slate-100 border border-slate-200 shrink-0">
                                    @if($thumb)
                                        <img src="{{ asset('storage/' . $thumb->original_sanitized_path) }}" alt="" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-slate-300">🏢</div>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <h4 class="text-xs font-bold text-slate-900 truncate">
                                        {{ $prop?->title_en ?? $listing->slug }}
                                    </h4>
                                    <p class="text-[11px] text-teal-800 font-semibold">
                                        {{ \App\Support\Money\EtbFormatter::format($listing->price_etb) }}
                                    </p>
                                    <span class="text-[10px] text-slate-400 font-mono">
                                        {{ $prop?->property_code }}
                                    </span>
                                </div>
                            </div>

                            <a
                                href="{{ url('/listings/' . $listing->slug) }}"
                                class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold shrink-0 transition"
                            >
                                View
                            </a>
                        </div>
                    @empty
                        <div class="p-8 text-center text-slate-400 text-xs">
                            No listings published yet. Click "Publish New Listing" to create your first property offer.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
