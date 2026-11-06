<x-app-layout>
    <div
        x-data="{
            revealedPhone: null,
            revealingPhone: false,
            phoneError: null,
            attachmentName: null,
            async revealSellerPhone(listingId) {
                this.revealingPhone = true;
                this.phoneError = null;
                try {
                    const res = await fetch(`/listings/${listingId}/reveal-phone`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const data = await res.json();
                    if (res.ok && data.phone) {
                        this.revealedPhone = data.phone;
                    } else {
                        this.phoneError = data.message || 'Phone disclosure restricted by seller privacy preferences.';
                    }
                } catch (e) {
                    this.phoneError = 'Unable to verify authorization for phone reveal.';
                } finally {
                    this.revealingPhone = false;
                }
            }
        }"
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6"
    >
        @if (session('success'))
            <div class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center justify-between">
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
                @foreach ($errors->all() as $error)
                    <p>• {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden grid grid-cols-1 lg:grid-cols-12 min-h-[78vh]">

            <!-- Column 1 (3 cols): Inquiry & Conversation List -->
            <div class="lg:col-span-3 border-r border-slate-200 bg-slate-50 flex flex-col">
                <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-white">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Inquiries &amp; Messages</h2>
                        <p class="text-[11px] text-slate-500">Pipeline conversations</p>
                    </div>
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-teal-100 text-teal-800">
                        {{ $inquiries->count() }} Active
                    </span>
                </div>

                <div class="flex-1 overflow-y-auto divide-y divide-slate-100 p-2 space-y-1">
                    @forelse($inquiries as $inq)
                        @php
                            $isActive = $activeInquiry && $activeInquiry->id === $inq->id;
                            $isBuyer = (auth()->id() === $inq->buyer_id);
                            $counterpart = $isBuyer ? $inq->seller : $inq->buyer;
                            $unreadCount = $isBuyer ? $inq->unread_buyer_count : $inq->unread_seller_count;
                        @endphp
                        <a
                            href="{{ route('messages.inbox', $inq->uuid) }}"
                            class="block p-3 rounded-xl transition {{ $isActive ? 'bg-white border-2 border-teal-700 shadow-xs' : 'hover:bg-slate-100/80 border border-transparent' }}"
                        >
                            <div class="flex items-center justify-between text-[11px] text-slate-500 mb-1">
                                <span class="font-mono font-bold text-slate-700">{{ $inq->inquiry_code }}</span>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $inq->status->value === 'closed' ? 'bg-emerald-100 text-emerald-800' : ($inq->status->value === 'lost' ? 'bg-slate-200 text-slate-600' : 'bg-teal-50 text-teal-800') }}">
                                    {{ $inq->status->value }}
                                </span>
                            </div>

                            <p class="text-xs font-bold text-slate-900 truncate">
                                {{ $inq->property?->title_en ?? ($inq->listing?->title ?? 'Property Inquiry') }}
                            </p>

                            <div class="flex items-center justify-between text-[11px] text-slate-500 mt-1">
                                <span class="truncate">{{ $counterpart?->name ?? 'User' }}</span>
                                @if($unreadCount > 0)
                                    <span class="px-1.5 py-0.2 rounded-full bg-rose-600 text-white font-bold text-[10px]">
                                        {{ $unreadCount }}
                                    </span>
                                @else
                                    <span class="text-[10px] text-slate-400">
                                        {{ $inq->last_message_at ? $inq->last_message_at->diffForHumans(null, true) : 'New' }}
                                    </span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="p-6 text-center text-slate-400 space-y-2">
                            <span class="text-3xl">💬</span>
                            <p class="text-xs font-medium text-slate-600">No inquiries yet</p>
                            <p class="text-[11px] text-slate-400">Contact property owners on marketplace listings to start a conversation.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Column 2 (6 cols): Contextual Conversation Stream -->
            @if($activeInquiry)
                @php
                    $isBuyer = (auth()->id() === $activeInquiry->buyer_id);
                    $otherUser = $isBuyer ? $activeInquiry->seller : $activeInquiry->buyer;
                @endphp
                <div class="lg:col-span-6 flex flex-col justify-between bg-white">
                    <!-- Thread Header -->
                    <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-white">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-teal-800 text-white font-bold flex items-center justify-center text-sm">
                                {{ substr($otherUser?->name ?? 'U', 0, 1) }}
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">{{ $otherUser?->name ?? 'Participant' }}</h3>
                                <p class="text-xs text-slate-500">
                                    {{ $isBuyer ? 'Property Owner / Seller' : 'Prospective Buyer' }} •
                                    Inquiry <span class="font-mono font-semibold text-slate-700">{{ $activeInquiry->inquiry_code }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-teal-50 text-teal-800 uppercase tracking-tight">
                                {{ $activeInquiry->status->value }}
                            </span>
                        </div>
                    </div>

                    <!-- Messages Stream -->
                    <div class="flex-1 p-5 space-y-4 overflow-y-auto bg-slate-50/70 max-h-[58vh]">
                        @forelse($activeInquiry->messages as $msg)
                            @if($msg->message_type === 'system')
                                <!-- System Status / Audit Notification -->
                                <div class="text-center my-3">
                                    <span class="inline-block px-3 py-1 rounded-full bg-slate-200 text-slate-700 text-[11px] font-medium">
                                        ⚡ {{ $msg->body }}
                                    </span>
                                </div>
                            @else
                                @php
                                    $isMine = ($msg->sender_id === auth()->id());
                                @endphp
                                <div class="flex flex-col {{ $isMine ? 'items-end' : 'items-start' }}">
                                    <div class="max-w-md rounded-2xl p-4 shadow-xs text-xs {{ $isMine ? 'bg-teal-800 text-white rounded-br-xs' : 'bg-white border border-slate-200 text-slate-800 rounded-bl-xs' }}">
                                        <div class="flex items-center justify-between gap-4 mb-1.5 opacity-80 text-[11px]">
                                            <strong class="font-semibold">{{ $isMine ? 'You' : ($msg->sender?->name ?? 'User') }}</strong>
                                            <span>{{ $msg->created_at->format('M d, H:i') }}</span>
                                        </div>

                                        <p class="leading-relaxed whitespace-pre-line">{{ $msg->body }}</p>

                                        @if($msg->attachments->isNotEmpty())
                                            <div class="mt-2.5 pt-2 border-t {{ $isMine ? 'border-teal-700/60' : 'border-slate-100' }} space-y-1">
                                                @foreach($msg->attachments as $att)
                                                    <a
                                                        href="{{ route('inquiries.attachments.download', ['uuid' => $activeInquiry->uuid, 'attachmentId' => $att->id]) }}"
                                                        class="flex items-center gap-2 p-1.5 rounded-lg text-[11px] font-semibold transition {{ $isMine ? 'bg-teal-900/50 hover:bg-teal-900 text-teal-100' : 'bg-slate-50 hover:bg-slate-100 text-slate-700' }}"
                                                    >
                                                        <span>📎</span>
                                                        <span class="truncate">{{ $att->original_filename }}</span>
                                                        <span class="text-[10px] opacity-75">({{ number_format($att->file_size_bytes / 1024, 0) }} KB)</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    @if($isMine && $msg->read_at)
                                        <span class="text-[10px] text-slate-400 mt-0.5 pr-1">Read {{ $msg->read_at->diffForHumans(null, true) }}</span>
                                    @endif
                                </div>
                            @endif
                        @empty
                            <div class="text-center py-12 text-slate-400 text-xs">
                                No messages yet. Send a message below to start the conversation.
                            </div>
                        @endforelse
                    </div>

                    <!-- Message Reply Form -->
                    <form
                        action="{{ route('inquiries.messages.store', $activeInquiry->uuid) }}"
                        method="POST"
                        enctype="multipart/form-data"
                        class="p-4 bg-white border-t border-slate-200 space-y-2"
                    >
                        @csrf
                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                name="body"
                                placeholder="Type your message here..."
                                class="flex-1 rounded-xl border-slate-300 text-xs py-2.5 px-3.5 focus:border-teal-700 focus:ring-teal-700"
                            >

                            <!-- Attachment Button -->
                            <label class="cursor-pointer p-2.5 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-600 transition" title="Attach Document / Photo">
                                <span class="text-sm">📎</span>
                                <input
                                    type="file"
                                    name="attachment"
                                    class="hidden"
                                    accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx"
                                    @change="attachmentName = $event.target.files[0] ? $event.target.files[0].name : null"
                                >
                            </label>

                            <button
                                type="submit"
                                class="px-5 py-2.5 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-xs font-bold transition shadow-xs"
                            >
                                Send
                            </button>
                        </div>

                        <template x-if="attachmentName">
                            <div class="flex items-center justify-between text-[11px] bg-slate-100 py-1 px-2.5 rounded-lg text-slate-700">
                                <span>Attached: <strong x-text="attachmentName"></strong></span>
                                <button type="button" @click="attachmentName = null" class="text-slate-400 hover:text-slate-700 ml-2">✕</button>
                            </div>
                        </template>
                    </form>
                </div>

                <!-- Column 3 (3 cols): Property Context & SRS Section 73 Lead Status Pipeline -->
                <div class="lg:col-span-3 border-l border-slate-200 p-4 space-y-5 bg-white">
                    <!-- Linked Property Context -->
                    @if($activeInquiry->property)
                        <div>
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Linked Property</span>
                            @php
                                $thumb = $activeInquiry->property->images?->first();
                            @endphp
                            @if($thumb)
                                <div class="mt-2 h-28 rounded-xl overflow-hidden bg-slate-100 border border-slate-200">
                                    <img
                                        src="{{ asset('storage/' . $thumb->original_sanitized_path) }}"
                                        alt="{{ $activeInquiry->property->title_en }}"
                                        class="w-full h-full object-cover"
                                    >
                                </div>
                            @endif
                            <h4 class="text-xs font-bold text-slate-900 mt-2">{{ $activeInquiry->property->title_en }}</h4>
                            <p class="text-xs text-slate-500 font-mono">{{ $activeInquiry->property->property_code }}</p>

                            @if($activeInquiry->listing)
                                <p class="text-base font-extrabold text-teal-900 mt-1">
                                    {{ \App\Support\Money\EtbFormatter::format($activeInquiry->listing->price_etb) }}
                                </p>
                                @if($activeInquiry->property->area_sqm)
                                    <p class="text-[11px] text-slate-500">
                                        {{ number_format((float)$activeInquiry->property->area_sqm, 1) }} m²
                                        @if($activeInquiry->listing->price_per_sqm_etb)
                                            • {{ \App\Support\Money\EtbFormatter::format($activeInquiry->listing->price_per_sqm_etb) }}/m²
                                        @endif
                                    </p>
                                @endif
                                <a
                                    href="{{ url('/listings/' . $activeInquiry->listing->slug) }}"
                                    target="_blank"
                                    class="inline-block text-[11px] text-teal-700 hover:underline font-semibold mt-1"
                                >
                                    View Full Listing →
                                </a>
                            @endif
                        </div>
                    @endif

                    <!-- SRS Section 73: Inquiry Pipeline Status Selector -->
                    <div class="pt-3 border-t border-slate-100">
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                            Pipeline Status (SRS Section 73)
                        </label>
                        <form action="{{ route('inquiries.status.update', $activeInquiry->uuid) }}" method="POST">
                            @csrf
                            <select
                                name="status"
                                onchange="this.form.submit()"
                                class="w-full rounded-xl border-slate-300 text-xs font-semibold text-slate-800 focus:border-teal-700 focus:ring-teal-700 py-2"
                            >
                                @foreach(['new' => '1. New Lead', 'contacted' => '2. Contacted', 'viewing' => '3. Viewing Scheduled', 'negotiating' => '4. Negotiating', 'offer' => '5. Offer Made', 'closed' => '6. Deal Closed', 'lost' => '7. Lost / Archived'] as $val => $label)
                                    <option value="{{ $val }}" {{ $activeInquiry->status->value === $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                        <p class="text-[10px] text-slate-400 mt-1">
                            Status updates create immutable audit records in the conversation history.
                        </p>
                    </div>

                    <!-- SRS Section 75: Phone Privacy Guard -->
                    @if($activeInquiry->listing)
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                            <span class="text-xs font-semibold text-slate-800 block">Seller Phone Privacy</span>

                            <template x-if="!revealedPhone">
                                <div>
                                    <button
                                        type="button"
                                        @click="revealSellerPhone({{ $activeInquiry->listing->id }})"
                                        :disabled="revealingPhone"
                                        class="w-full py-2.5 px-3 rounded-xl bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 transition shadow-xs disabled:opacity-50"
                                    >
                                        <span x-text="revealingPhone ? 'Authorizing...' : 'Reveal Verified Phone'"></span>
                                    </button>
                                    <template x-if="phoneError">
                                        <p class="text-[11px] text-amber-800 mt-1.5 bg-amber-50 p-2 rounded border border-amber-200" x-text="phoneError"></p>
                                    </template>
                                </div>
                            </template>

                            <template x-if="revealedPhone">
                                <a
                                    :href="'tel:' + revealedPhone"
                                    class="w-full py-2.5 px-3 rounded-xl bg-teal-800 text-white text-xs font-bold hover:bg-teal-900 transition shadow-xs flex items-center justify-center gap-1.5"
                                >
                                    <span>📞</span>
                                    <span x-text="revealedPhone"></span>
                                </a>
                            </template>

                            <p class="text-[10px] text-slate-500 leading-normal">
                                Protected by anti-scraper rate limits and audit logs per SRS Section 75.
                            </p>
                        </div>
                    @endif
                </div>
            @else
                <div class="lg:col-span-9 flex flex-col items-center justify-center p-12 text-center text-slate-400 space-y-3">
                    <span class="text-5xl">📫</span>
                    <h3 class="text-base font-bold text-slate-700">Select a Conversation</h3>
                    <p class="text-xs text-slate-500 max-w-sm">
                        Choose an inquiry from the left panel to review messages, exchange attachments, and manage pipeline progression.
                    </p>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
