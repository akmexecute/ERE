<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8" x-data="{ transitionModal: false, targetStatus: '', modalTitle: '', requiresContractor: false, requiresCompletion: false, requiresClosure: false }">
        <!-- Header & Breadcrumbs -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-6">
            <div>
                <nav class="text-xs text-slate-500 mb-1 flex items-center gap-2">
                    <a href="{{ route('maintenance.index') }}" class="hover:text-teal-700">Maintenance Requests</a>
                    <span>/</span>
                    <span class="font-mono text-slate-800 font-semibold">{{ $ticket->ticket_code }}</span>
                </nav>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">{{ $ticket->title }}</h1>
                    @php
                        $statusClass = match($ticket->status->value) {
                            'open' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                            'assigned' => 'bg-blue-50 text-blue-800 border-blue-200',
                            'in_progress' => 'bg-purple-50 text-purple-800 border-purple-200',
                            'waiting' => 'bg-amber-50 text-amber-800 border-amber-200',
                            'completed' => 'bg-teal-50 text-teal-800 border-teal-200',
                            'closed' => 'bg-slate-100 text-slate-600 border-slate-200',
                            default => 'bg-slate-50 text-slate-700',
                        };
                    @endphp
                    <span class="px-3 py-1 rounded-full text-xs font-semibold border {{ $statusClass }}">
                        {{ $ticket->status->label() }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Submitted on @ethDate($ticket->created_at) ({{ $ticket->created_at->format('M d, Y H:i') }}) by <strong>{{ $ticket->reporter->name }}</strong>
                </p>
            </div>

            <!-- Quick Action Buttons based on State Machine -->
            <div class="flex flex-wrap items-center gap-2">
                @if($ticket->status->value === 'open')
                    <button
                        type="button"
                        @click="targetStatus = 'assigned'; modalTitle = 'Assign Technician or Contractor'; requiresContractor = true; requiresCompletion = false; requiresClosure = false; transitionModal = true"
                        class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-xs transition"
                    >
                        Assign Work Order
                    </button>
                    <button
                        type="button"
                        @click="targetStatus = 'in_progress'; modalTitle = 'Start Repair Directly'; requiresContractor = false; requiresCompletion = false; requiresClosure = false; transitionModal = true"
                        class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold shadow-xs transition"
                    >
                        Start Work
                    </button>
                    <button
                        type="button"
                        @click="targetStatus = 'closed'; modalTitle = 'Cancel / Reject Ticket'; requiresContractor = false; requiresCompletion = false; requiresClosure = true; transitionModal = true"
                        class="px-4 py-2 rounded-xl border border-red-300 text-red-700 hover:bg-red-50 text-xs font-semibold transition"
                    >
                        Cancel Ticket
                    </button>
                @elseif($ticket->status->value === 'assigned')
                    <button
                        type="button"
                        @click="targetStatus = 'in_progress'; modalTitle = 'Commence Work on Ticket'; requiresContractor = false; requiresCompletion = false; requiresClosure = false; transitionModal = true"
                        class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold shadow-xs transition"
                    >
                        Mark In Progress
                    </button>
                    <button
                        type="button"
                        @click="targetStatus = 'waiting'; modalTitle = 'Hold for Parts or Tenant Access'; requiresContractor = false; requiresCompletion = false; requiresClosure = false; transitionModal = true"
                        class="px-4 py-2 rounded-xl border border-amber-300 text-amber-800 hover:bg-amber-50 text-xs font-semibold transition"
                    >
                        Place on Hold
                    </button>
                    <button
                        type="button"
                        @click="targetStatus = 'completed'; modalTitle = 'Record Resolution & Costs'; requiresContractor = false; requiresCompletion = true; requiresClosure = false; transitionModal = true"
                        class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold shadow-xs transition"
                    >
                        Mark Completed
                    </button>
                @elseif($ticket->status->value === 'in_progress')
                    <button
                        type="button"
                        @click="targetStatus = 'waiting'; modalTitle = 'Pause / Place on Hold'; requiresContractor = false; requiresCompletion = false; requiresClosure = false; transitionModal = true"
                        class="px-4 py-2 rounded-xl border border-amber-300 text-amber-800 hover:bg-amber-50 text-xs font-semibold transition"
                    >
                        Wait on Parts
                    </button>
                    <button
                        type="button"
                        @click="targetStatus = 'completed'; modalTitle = 'Record Resolution & Actual Cost'; requiresContractor = false; requiresCompletion = true; requiresClosure = false; transitionModal = true"
                        class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold shadow-xs transition"
                    >
                        Complete Work Order
                    </button>
                @elseif($ticket->status->value === 'waiting')
                    <button
                        type="button"
                        @click="targetStatus = 'in_progress'; modalTitle = 'Resume Work'; requiresContractor = false; requiresCompletion = false; requiresClosure = false; transitionModal = true"
                        class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold shadow-xs transition"
                    >
                        Resume Work
                    </button>
                    <button
                        type="button"
                        @click="targetStatus = 'completed'; modalTitle = 'Record Resolution & Cost'; requiresContractor = false; requiresCompletion = true; requiresClosure = false; transitionModal = true"
                        class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold shadow-xs transition"
                    >
                        Complete
                    </button>
                @elseif($ticket->status->value === 'completed')
                    <button
                        type="button"
                        @click="targetStatus = 'closed'; modalTitle = 'Sign Off & Close Ticket'; requiresContractor = false; requiresCompletion = false; requiresClosure = true; transitionModal = true"
                        class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold shadow-xs transition"
                    >
                        Sign Off & Close
                    </button>
                    <button
                        type="button"
                        @click="targetStatus = 'in_progress'; modalTitle = 'Reopen Ticket for Incomplete Work'; requiresContractor = false; requiresCompletion = false; requiresClosure = false; transitionModal = true"
                        class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-semibold transition"
                    >
                        Reopen Ticket
                    </button>
                @elseif($ticket->status->value === 'closed')
                    <span class="text-xs px-3 py-1.5 rounded-xl bg-slate-100 text-slate-500 font-semibold">
                        🔒 Ticket Permanently Closed
                    </span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left 2 Cols: Details, Property, Costs, Resolution -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Main Request Card -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Category:</span>
                            <span class="text-xs font-semibold text-slate-900 bg-slate-100 px-2.5 py-1 rounded-lg">
                                {{ $ticket->category->label() }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Priority:</span>
                            <span class="text-xs font-semibold text-slate-900">
                                {{ $ticket->priority->label() }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Description</h3>
                        <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">{{ $ticket->description }}</p>
                    </div>

                    @if($ticket->resolution_notes)
                        <div class="p-4 rounded-xl bg-teal-50/60 border border-teal-200/80 space-y-1">
                            <h4 class="text-xs font-bold text-teal-900 uppercase">Work Resolution Notes</h4>
                            <p class="text-xs text-teal-800 leading-relaxed">{{ $ticket->resolution_notes }}</p>
                        </div>
                    @endif
                </div>

                <!-- Financial Breakdown & Costs -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Maintenance Financials</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-[11px] text-slate-500 block mb-1">Estimated Cost</span>
                            <span class="text-lg font-mono font-bold text-slate-900">ETB {{ number_format((float) $ticket->estimated_cost_etb, 2) }}</span>
                        </div>
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-[11px] text-slate-500 block mb-1">Actual Cost</span>
                            <span class="text-lg font-mono font-bold text-teal-700">ETB {{ number_format((float) $ticket->actual_cost_etb, 2) }}</span>
                        </div>
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-[11px] text-slate-500 block mb-1">Billable Responsibility</span>
                            <span class="text-sm font-semibold text-slate-800 capitalize">{{ $ticket->billable_party }}</span>
                        </div>
                    </div>
                </div>

                <!-- Operational History Timeline -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Status History & Audit Trail</h3>
                        <span class="text-xs font-mono text-slate-400">{{ $ticket->histories->count() }} events</span>
                    </div>

                    <div class="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                        @foreach($ticket->histories as $history)
                            <div class="relative">
                                <div class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full bg-teal-600 ring-4 ring-white"></div>
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-xs">
                                    <div class="font-semibold text-slate-900">
                                        Status changed: <span class="capitalize text-slate-500">{{ str_replace('_', ' ', $history->from_status) }}</span> &rarr;
                                        <span class="capitalize text-teal-800 font-bold">{{ str_replace('_', ' ', $history->to_status) }}</span>
                                    </div>
                                    <span class="text-[11px] text-slate-400 font-mono">@ethDate($history->created_at) {{ $history->created_at->format('H:i') }}</span>
                                </div>
                                <p class="text-xs text-slate-600 mt-1">{{ $history->notes }}</p>
                                <span class="text-[11px] text-slate-400 block mt-0.5">By: {{ $history->actor?->name ?? 'System' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Right Column: Property & Assignment Card -->
            <div class="space-y-6">
                <!-- Property Card -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Property Details</h3>
                    <div>
                        <div class="text-base font-bold text-slate-900">{{ $ticket->property->title_en }}</div>
                        <div class="text-xs font-mono text-slate-400 mt-0.5">{{ $ticket->property->property_code }}</div>
                    </div>
                    <div class="text-xs text-slate-600 space-y-1.5 pt-2 border-t border-slate-100">
                        <div><strong>City:</strong> {{ $ticket->property->city?->name_en ?? 'Addis Ababa' }}</div>
                        <div><strong>Owner:</strong> {{ $ticket->property->owner->name }} ({{ $ticket->property->owner->phone }})</div>
                    </div>
                </div>

                <!-- Assignment Card -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Assigned Dispatch</h3>
                    @if($ticket->assignedTechnician)
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-800 font-bold flex items-center justify-center text-sm">
                                {{ strtoupper(substr($ticket->assignedTechnician->name, 0, 2)) }}
                            </div>
                            <div>
                                <div class="text-sm font-bold text-slate-900">{{ $ticket->assignedTechnician->name }}</div>
                                <div class="text-xs text-slate-500">Staff Technician • {{ $ticket->assignedTechnician->phone }}</div>
                            </div>
                        </div>
                    @elseif($ticket->contractor_name)
                        <div class="space-y-1 text-xs">
                            <div class="font-bold text-slate-900 text-sm">{{ $ticket->contractor_name }}</div>
                            <div class="text-slate-500">External Contractor</div>
                            @if($ticket->contractor_phone)
                                <div class="font-mono text-slate-600">📞 {{ $ticket->contractor_phone }}</div>
                            @endif
                        </div>
                    @else
                        <div class="text-xs text-slate-400 italic">No technician assigned yet.</div>
                    @endif
                </div>
            </div>
        </div>

        <!-- State Transition Modal -->
        <div
            x-show="transitionModal"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div
                @click.outside="transitionModal = false"
                class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-lg w-full p-6 space-y-5"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900" x-text="modalTitle"></h3>
                    <button type="button" @click="transitionModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
                </div>

                <form method="POST" action="{{ route('maintenance.transition', $ticket->uuid) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="target_status" :value="targetStatus">

                    <!-- Contractor Assignment fields -->
                    <template x-if="requiresContractor">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Staff Technician (Optional)</label>
                                <select name="assigned_to_user_id" class="w-full text-xs rounded-xl border-slate-300">
                                    <option value="">-- Choose internal technician --</option>
                                    @foreach($technicians as $tech)
                                        <option value="{{ $tech->id }}">{{ $tech->name }} ({{ $tech->phone }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">OR External Contractor Name</label>
                                <input type="text" name="contractor_name" placeholder="e.g. Addis Electrical Solutions" class="w-full text-xs rounded-xl border-slate-300">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Contractor Phone</label>
                                <input type="text" name="contractor_phone" placeholder="+251 9..." class="w-full text-xs rounded-xl border-slate-300">
                            </div>
                        </div>
                    </template>

                    <!-- Completion fields -->
                    <template x-if="requiresCompletion">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Actual Incurred Cost (ETB) <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" name="actual_cost_etb" required value="{{ $ticket->estimated_cost_etb > 0 ? $ticket->estimated_cost_etb : '0.00' }}" class="w-full text-xs rounded-xl border-slate-300 font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Resolution Summary <span class="text-red-500">*</span></label>
                                <textarea name="resolution_notes" required rows="3" placeholder="Describe the parts replaced and verified repair..." class="w-full text-xs rounded-xl border-slate-300"></textarea>
                            </div>
                        </div>
                    </template>

                    <!-- Notes -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Action Notes / Reason</label>
                        <textarea name="notes" rows="2" placeholder="Provide operational notes for this transition..." class="w-full text-xs rounded-xl border-slate-300"></textarea>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" @click="transitionModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-semibold">
                            Confirm Transition
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
