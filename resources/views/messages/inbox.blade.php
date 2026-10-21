<x-app-layout>
    <div
        x-data="{
            activeInquiryId: 'INQ-2026-0000482',
            pipelineStage: 'viewing',
            newMessage: '',
            phoneRevealed: null,
            messages: [
                { id: 1, sender: 'buyer', name: 'Dawit Tadesse', time: '2019 መስከረም 11 • 10:15 AM', text: 'Selam! Is the 4-Bedroom Villa in Bole Medhanialem (ETH-AA-BOLE-RES-00001245) still available for viewing this week?' },
                { id: 2, sender: 'seller', name: 'Abebe Kebede (Verified Owner)', time: '2019 መስከረም 11 • 10:42 AM', text: 'Selam Dawit! Yes, it is available. We can arrange a viewing on Thursday afternoon at 3:00 PM.' }
            ],
            sendMessage() {
                if (!this.newMessage.trim()) return;
                this.messages.push({
                    id: Date.now(),
                    sender: 'buyer',
                    name: 'You',
                    time: 'Just now',
                    text: this.newMessage
                });
                this.newMessage = '';
            }
        }"
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6"
    >
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden grid grid-cols-1 lg:grid-cols-12 min-h-[75vh]">

            <!-- Column 1 (3 cols): Inquiry Thread List -->
            <div class="lg:col-span-3 border-r border-slate-200 bg-slate-50 p-4 space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                    <h2 class="text-sm font-bold text-slate-900">Inquiries & Leads</h2>
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-teal-100 text-teal-800">Pipeline</span>
                </div>
                <div class="p-3.5 rounded-xl bg-white border-2 border-teal-600 shadow-sm cursor-pointer transition hover:border-teal-700">
                    <div class="flex items-center justify-between text-[11px] text-slate-500">
                        <span class="font-mono font-semibold text-teal-800">INQ-2026-0000482</span>
                        <span class="px-2 py-0.5 rounded-full bg-teal-50 text-teal-800 font-semibold uppercase tracking-tight" x-text="pipelineStage"></span>
                    </div>
                    <p class="mt-1.5 text-xs font-bold text-slate-900 truncate">Modern 4-Bed Villa — Bole</p>
                    <p class="text-xs text-slate-600 truncate mt-0.5">Dawit Tadesse • ETB 25,000,000</p>
                </div>
            </div>

            <!-- Column 2 (6 cols): Contextual Chat Stream -->
            <div class="lg:col-span-6 flex flex-col justify-between">
                <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-white">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Conversation with Abebe Kebede</h3>
                        <p class="text-xs text-slate-500">Property ID: <span class="font-mono font-semibold text-slate-700">ETH-AA-BOLE-RES-00001245</span></p>
                    </div>
                    <x-verification.badge :level="3" />
                </div>

                <div class="flex-1 p-5 space-y-4 overflow-y-auto bg-slate-50/60">
                    <template x-for="msg in messages" :key="msg.id">
                        <div :class="msg.sender === 'buyer' ? 'ml-auto bg-teal-700 text-white' : 'mr-auto bg-white border border-slate-200 text-slate-800'" class="max-w-md rounded-2xl p-3.5 shadow-sm text-xs">
                            <div class="flex items-center justify-between gap-4 mb-1 opacity-80 text-[11px]">
                                <strong x-text="msg.name"></strong>
                                <span x-text="msg.time"></span>
                            </div>
                            <p class="leading-relaxed whitespace-pre-line" x-text="msg.text"></p>
                        </div>
                    </template>
                </div>

                <form @submit.prevent="sendMessage()" class="p-4 bg-white border-t border-slate-200 flex items-center gap-2">
                    <input
                        type="text"
                        x-model="newMessage"
                        placeholder="Write a message or ask for viewing dates..."
                        class="flex-1 rounded-xl border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600 px-3.5 py-2.5"
                    >
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold transition shadow-xs">
                        Send
                    </button>
                </form>
            </div>

            <!-- Column 3 (3 cols): Property Context & SRS Section 73 Lead Status Pipeline -->
            <div class="lg:col-span-3 border-l border-slate-200 p-4 space-y-5 bg-white">
                <div>
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Linked Property</span>
                    <h4 class="text-sm font-bold text-slate-900 mt-1">Modern Villa — Bole</h4>
                    <p class="text-base font-extrabold text-teal-800 mt-0.5">ETB 25,000,000</p>
                    <p class="text-xs text-slate-500">ETB 66,666.67 / m² • 375 m²</p>
                </div>

                <!-- SRS Section 73: Inquiry Pipeline Stage Selector -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        Inquiry Pipeline Status (SRS Section 73)
                    </label>
                    <select x-model="pipelineStage" class="w-full rounded-xl border-slate-300 text-xs font-semibold focus:border-teal-600 focus:ring-teal-600">
                        <option value="new">1. New Inquiry</option>
                        <option value="contacted">2. Contacted</option>
                        <option value="viewing">3. Viewing Scheduled</option>
                        <option value="negotiating">4. Negotiating</option>
                        <option value="offer">5. Offer Received</option>
                        <option value="closed">6. Closed Deal</option>
                        <option value="lost">7. Lost</option>
                    </select>
                </div>

                <!-- SRS Section 75: Phone Privacy Guard -->
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                    <span class="text-xs font-semibold text-slate-800 block">Seller Contact Privacy</span>
                    <button
                        type="button"
                        @click="phoneRevealed = '+251 911 234 567'"
                        class="w-full py-2.5 px-3 rounded-xl bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 transition shadow-xs"
                    >
                        <span x-text="phoneRevealed ? ('📞 ' + phoneRevealed) : 'Reveal Verified Phone'"></span>
                    </button>
                    <p class="text-[10px] text-slate-500 leading-normal">
                        Protected by anti-scraper rate limiting and audited access log per SRS Section 75.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
