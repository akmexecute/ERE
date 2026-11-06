<x-app-layout>
    <x-slot name="title">Ticket {{ $ticket->ticket_code }} — Support Desk | Ethiopian Real Estate Marketplace</x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <nav class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                    <a href="{{ route('support.tickets.index') }}" class="text-teal-800 hover:underline">Support Desk</a>
                    <span>/</span>
                    <span class="font-mono text-slate-800 font-semibold">{{ $ticket->ticket_code }}</span>
                </nav>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-extrabold text-slate-900">
                        {{ $ticket->subject }}
                    </h1>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Submitted by <strong>{{ $ticket->user?->name ?? 'User' }}</strong> on {{ $ticket->created_at->format('M d, Y • H:i') }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider {{ in_array($ticket->priority, ['high', 'urgent']) ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-700' }}">
                    {{ $ticket->priority }} Priority
                </span>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider {{ $ticket->status === 'resolved' || $ticket->status === 'closed' ? 'bg-emerald-50 text-emerald-800' : ($ticket->status === 'in_progress' ? 'bg-sky-50 text-sky-800' : 'bg-amber-50 text-amber-800') }}">
                    {{ str_replace('_', ' ', $ticket->status) }}
                </span>
            </div>
        </div>

        @if (session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left 8 Cols: Ticket Details -->
            <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">
                        Ticket Description
                    </h3>
                    <div class="p-4 rounded-xl bg-slate-50 text-xs text-slate-800 leading-relaxed whitespace-pre-line border border-slate-200/60">
                        {{ $ticket->description }}
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-slate-400 block text-[11px]">Category</span>
                        <span class="font-bold text-slate-800 uppercase">{{ $ticket->category }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[11px]">Ticket Code</span>
                        <span class="font-mono font-bold text-slate-800">{{ $ticket->ticket_code }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[11px]">Assigned Staff</span>
                        <span class="font-bold text-slate-800">{{ $ticket->assignedStaff?->name ?? 'Unassigned' }}</span>
                    </div>
                </div>
            </div>

            <!-- Right 4 Cols: Staff Controls or Help Info -->
            <div class="lg:col-span-4 space-y-4">
                @if($isStaff)
                    <!-- Staff Workflow Actions -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 space-y-4">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-teal-800">
                            Staff Resolution Controls
                        </h3>

                        <form method="POST" action="{{ route('support.tickets.status.update', $ticket->uuid) }}" class="space-y-4 text-xs">
                            @csrf
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Update Status</label>
                                <select
                                    name="status"
                                    class="w-full rounded-xl border-slate-300 text-xs font-semibold focus:border-teal-800 focus:ring-teal-800 py-2"
                                >
                                    @foreach($validStatuses as $st)
                                        <option value="{{ $st }}" {{ $ticket->status === $st ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $st)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <button
                                type="submit"
                                class="w-full py-2.5 px-4 rounded-xl bg-teal-800 hover:bg-teal-900 text-white font-bold shadow-xs transition"
                            >
                                Update Ticket Status
                            </button>
                        </form>

                        <p class="text-[10px] text-slate-400 leading-tight">
                            Status changes are recorded in the audit trail per platform governance policies.
                        </p>
                    </div>
                @else
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 space-y-2 text-xs">
                        <h3 class="font-bold text-slate-900">Ticket Resolution SLA</h3>
                        <p class="text-slate-500 leading-relaxed">
                            Our support desk handles urgent priority tickets within 4 hours, and standard inquiries within 24 business hours.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
