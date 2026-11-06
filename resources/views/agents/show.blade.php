<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
        <!-- Agent Overview Card -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <div class="flex flex-col md:flex-row gap-6 md:items-start justify-between">
                <div class="flex items-start gap-5">
                    <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl bg-slate-900 text-white flex items-center justify-center text-2xl font-bold font-mono shrink-0 shadow-inner">
                        {{ strtoupper(substr($user->name ?? 'A', 0, 2)) }}
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                                {{ $user->name ?? 'Licensed Agent' }}
                            </h1>

                            @if($agent->is_verified_agent)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    Verified Professional
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                                    Standard Listing Agent
                                </span>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-600">
                            <span class="font-mono font-semibold text-slate-800">{{ $agent->agent_code }}</span>
                            @if($company)
                                <span>•</span>
                                <span>{{ $company->trade_name ?: $company->legal_name }}</span>
                            @endif
                            @if($branch)
                                <span>•</span>
                                <span>{{ $branch->name_en }}</span>
                            @endif
                        </div>

                        @if($agent->license_number)
                            <p class="text-[11px] text-slate-500 font-mono">
                                Professional Reg. No: {{ $agent->license_number }} (Proclamation No. 1357/2024 Metadata)
                            </p>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row md:flex-col gap-2.5 shrink-0">
                    <a
                        href="{{ route('messages.inbox', ['agent' => $agent->agent_code]) }}"
                        class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition shadow-sm"
                    >
                        Send Inquiry
                    </a>

                    @auth
                        @if(auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('verification_officer'))
                            @if(! $agent->is_verified_agent)
                                <form method="POST" action="{{ route('admin.agents.verify', $agent->agent_code) }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="w-full inline-flex items-center justify-center px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition cursor-pointer"
                                    >
                                        Approve Verification
                                    </button>
                                </form>
                            @endif
                        @endif
                    @endauth
                </div>
            </div>

            <!-- Performance Metrics Strip -->
            <div class="mt-8 pt-6 border-t border-slate-100 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-4">
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-[11px] font-medium text-slate-500 block">Client Rating</span>
                    <div class="flex items-center gap-1.5 mt-1">
                        <span class="text-xl font-extrabold text-slate-900">{{ number_format((float) $agent->average_rating, 1) }}</span>
                        <span class="text-xs text-amber-500">★</span>
                        <span class="text-[11px] text-slate-400">({{ $agent->reviews_count }})</span>
                    </div>
                </div>

                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-[11px] font-medium text-slate-500 block">Closed Transactions</span>
                    <span class="text-xl font-extrabold text-slate-900 block mt-1">{{ $agent->closed_deals_count }} deals</span>
                </div>

                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-[11px] font-medium text-slate-500 block">Active Listings</span>
                    <span class="text-xl font-extrabold text-slate-900 block mt-1">{{ $agent->active_listings_count }} properties</span>
                </div>

                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-[11px] font-medium text-slate-500 block">Response Rate</span>
                    <span class="text-xl font-extrabold text-slate-900 block mt-1">{{ $agent->response_rate_pct }}%</span>
                </div>

                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100 col-span-2 sm:col-span-1">
                    <span class="text-[11px] font-medium text-slate-500 block">Avg Response Time</span>
                    <span class="text-xl font-extrabold text-slate-900 block mt-1">{{ $agent->avg_response_minutes }} mins</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left 2 Cols: Biography & Details -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Multi-lingual Bio -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                    <h2 class="text-lg font-bold text-slate-900">About the Agent</h2>

                    @if($agent->bio_en)
                        <div class="prose prose-sm text-slate-700 leading-relaxed">
                            <p>{{ $agent->bio_en }}</p>
                        </div>
                    @endif

                    @if($agent->bio_am)
                        <div class="pt-3 border-t border-slate-100 text-slate-700 text-sm leading-relaxed" lang="am">
                            <span class="text-xs font-semibold text-slate-400 block mb-1">ስለ ወኪሉ በአማርኛ፦</span>
                            <p>{{ $agent->bio_am }}</p>
                        </div>
                    @endif

                    @if($agent->bio_om)
                        <div class="pt-3 border-t border-slate-100 text-slate-700 text-sm leading-relaxed" lang="om">
                            <span class="text-xs font-semibold text-slate-400 block mb-1">Waa'ee Bakka Bu'aa Afaan Oromootiin:</span>
                            <p>{{ $agent->bio_om }}</p>
                        </div>
                    @endif
                </div>

                <!-- Active Listings Portfolio -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-slate-900">Active Listings</h2>
                        <span class="text-xs text-slate-500">{{ $listings->count() }} listed</span>
                    </div>

                    @if($listings->isEmpty())
                        <div class="p-8 text-center bg-slate-50 rounded-xl border border-dashed border-slate-200 text-xs text-slate-500">
                            No active listings currently published by this agent.
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($listings as $item)
                                <a
                                    href="{{ route('listings.show', $item->slug) }}"
                                    class="p-4 rounded-xl border border-slate-200 hover:border-slate-300 transition block space-y-2 bg-slate-50/50 hover:bg-slate-50"
                                >
                                    <div class="text-sm font-bold text-slate-900 line-clamp-1">
                                        {{ $item->property?->title_en ?? $item->slug }}
                                    </div>
                                    <div class="text-xs text-emerald-800 font-extrabold">
                                        ETB {{ number_format((float) $item->price_etb) }}
                                    </div>
                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-200 text-slate-700">
                                        {{ $item->transaction_mode }}
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Right 1 Col: Branch, Affiliations & Languages -->
            <div class="space-y-6">
                <!-- Branch Affiliation -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-3">
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Branch Affiliation</h3>
                    @if($branch)
                        <div class="space-y-1">
                            <strong class="text-sm text-slate-900 block">{{ $branch->name_en }}</strong>
                            <span class="text-xs font-mono text-slate-600 block">Code: {{ $branch->branch_code }}</span>
                            @if($branch->address_line)
                                <p class="text-xs text-slate-500">{{ $branch->address_line }}</p>
                            @endif
                        </div>
                    @else
                        <p class="text-xs text-slate-500">Independent Licensed Associate</p>
                    @endif
                </div>

                <!-- Languages Spoken -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-3">
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Spoken Languages</h3>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($agent->languages_json ?? ['en', 'am'] as $lang)
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold uppercase">
                                {{ $lang }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <!-- Service Locations -->
                @if($locations && $locations->isNotEmpty())
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-3">
                        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Service Coverage</h3>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($locations as $loc)
                                <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 text-xs font-medium">
                                    {{ $loc->name_en }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

