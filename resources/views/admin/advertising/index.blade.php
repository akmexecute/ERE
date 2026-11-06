<x-app-layout>
    <div
        x-data="{
            rejectCampaignId: null,
            rejectionReason: '',
            openReject(id) {
                this.rejectCampaignId = id;
                this.rejectionReason = '';
            }
        }"
        class="bg-slate-50 min-h-screen py-8 px-4 sm:px-6 lg:px-8 text-slate-800"
    >
        <div class="max-w-7xl mx-auto space-y-8">

            <!-- Breadcrumbs & Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                        <a href="{{ route('admin.dashboard') }}" class="hover:underline">Admin</a>
                        <span>/</span>
                        <span>Monetization</span>
                        <span>/</span>
                        <span class="text-slate-800 font-semibold">Advertising Management</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900">Multi-Placement Display Advertising (Module 33.6)</h1>
                    <p class="text-xs text-slate-500">Manage 7 advertising placements across ERE and moderate customer marketing campaigns.</p>
                </div>
            </div>

            @if(session('success'))
                <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 font-semibold">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('warning'))
                <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 font-semibold">
                    {{ session('warning') }}
                </div>
            @endif

            <!-- 7 Standard Placements Overview -->
            <div class="space-y-3">
                <h2 class="text-base font-bold text-slate-900">Configured Placements (7 Surfaces)</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach($placements as $placement)
                        <div class="bg-white rounded-2xl border border-slate-200 p-4 space-y-3 shadow-xs">
                            <div class="flex items-start justify-between">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-800 font-mono">
                                    {{ $placement->placement->value }}
                                </span>
                                <span class="text-[11px] font-semibold text-slate-500">
                                    {{ $placement->active_campaigns_count }} active
                                </span>
                            </div>

                            <div>
                                <h3 class="font-bold text-xs text-slate-900">{{ $placement->name_en }}</h3>
                                <div class="text-[11px] text-slate-500 mt-0.5">{{ $placement->duration_days }} days &middot; {{ number_format($placement->impression_limit ?? 0) }} max views</div>
                            </div>

                            <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                                <span class="text-sm font-black text-slate-900">{{ number_format((float) $placement->price_etb, 0) }} ETB</span>
                                <span class="text-[11px] font-semibold {{ $placement->is_active ? 'text-emerald-700' : 'text-slate-400' }}">
                                    {{ $placement->is_active ? 'Serving' : 'Paused' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Campaigns Moderation Queue -->
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
                <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold">Campaigns Moderation &amp; Delivery Queue</h2>
                        <p class="text-xs text-slate-300">Review pending campaigns submitted with subscriptions or direct bookings.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-slate-700">
                                <th class="p-3.5 pl-6 font-semibold">Campaign Details</th>
                                <th class="p-3.5 font-semibold">Advertiser</th>
                                <th class="p-3.5 font-semibold">Placement</th>
                                <th class="p-3.5 font-semibold">Delivery Metrics</th>
                                <th class="p-3.5 font-semibold">Status</th>
                                <th class="p-3.5 pr-6 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse($campaigns as $campaign)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="p-3.5 pl-6">
                                        <div class="font-bold text-slate-900">{{ $campaign->title ?? 'Untitled Campaign' }}</div>
                                        @if($campaign->target_url)
                                            <a href="{{ $campaign->target_url }}" target="_blank" class="text-[11px] text-teal-700 hover:underline flex items-center gap-1">
                                                <span>🔗</span> {{ Str::limit($campaign->target_url, 40) }}
                                            </a>
                                        @endif
                                        <div class="text-[10px] text-slate-400 font-mono mt-0.5">UUID: {{ $campaign->uuid }}</div>
                                    </td>
                                    <td class="p-3.5">
                                        <div class="font-semibold text-slate-800">{{ $campaign->user?->name ?? 'User #' . $campaign->user_id }}</div>
                                        <div class="text-[11px] text-slate-500">{{ $campaign->user?->phone ?? $campaign->user?->email }}</div>
                                    </td>
                                    <td class="p-3.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-800">
                                            {{ $campaign->placement ?? $campaign->placement?->placement?->value }}
                                        </span>
                                    </td>
                                    <td class="p-3.5 font-mono text-[11px]">
                                        <div>👁 {{ number_format($campaign->impressions_count) }} views</div>
                                        <div>👆 {{ number_format($campaign->clicks_count) }} clicks</div>
                                    </td>
                                    <td class="p-3.5">
                                        @php
                                            $badgeClass = match($campaign->status->value) {
                                                'active' => 'bg-emerald-100 text-emerald-800',
                                                'pending_approval' => 'bg-amber-100 text-amber-800',
                                                'rejected' => 'bg-rose-100 text-rose-800',
                                                'completed' => 'bg-slate-100 text-slate-800',
                                                default => 'bg-slate-100 text-slate-700'
                                            };
                                        @endphp
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $badgeClass }}">
                                            {{ $campaign->status->value }}
                                        </span>
                                    </td>
                                    <td class="p-3.5 pr-6 text-right space-x-2">
                                        @if($campaign->status->value === 'pending_approval' || $campaign->status->value === 'draft')
                                            <form method="POST" action="{{ route('admin.advertising.campaigns.approve', $campaign) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1 rounded bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-[11px]">
                                                    Approve
                                                </button>
                                            </form>
                                            <button
                                                type="button"
                                                @click="openReject({{ $campaign->id }})"
                                                class="px-2.5 py-1 rounded bg-rose-100 hover:bg-rose-200 text-rose-800 font-bold text-[11px]"
                                            >
                                                Reject
                                            </button>
                                        @else
                                            <span class="text-slate-400 text-[11px]">Reviewed</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-6 text-center text-slate-500">
                                        No ad campaigns submitted yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($campaigns->hasPages())
                    <div class="p-4 border-t border-slate-200">
                        {{ $campaigns->links() }}
                    </div>
                @endif
            </div>

            <!-- Reject Campaign Modal -->
            <div
                x-show="rejectCampaignId !== null"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            >
                <div class="bg-white rounded-2xl border border-slate-200 max-w-md w-full p-6 space-y-4 shadow-xl" @click.outside="rejectCampaignId = null">
                    <div class="flex items-center justify-between border-b pb-3">
                        <h3 class="text-base font-bold text-slate-900">Reject Advertising Campaign</h3>
                        <button type="button" @click="rejectCampaignId = null" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                    </div>

                    <form :action="'/admin/advertising/campaigns/' + rejectCampaignId + '/reject'" method="POST" class="space-y-3 text-xs">
                        @csrf
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Reason for Rejection:</label>
                            <textarea name="rejection_reason" x-model="rejectionReason" required rows="3" placeholder="Explain compliance issues (e.g. misleading copy, broken target URL)..." class="w-full rounded-xl border-slate-300 py-1.5 text-xs"></textarea>
                        </div>

                        <div class="pt-3 border-t flex justify-end gap-2">
                            <button type="button" @click="rejectCampaignId = null" class="px-3 py-1.5 rounded-xl border text-slate-600 hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-4 py-1.5 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700">Confirm Rejection</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

