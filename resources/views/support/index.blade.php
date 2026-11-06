<x-app-layout>
    <x-slot name="title">Customer Support Tickets — Ethiopian Real Estate Marketplace</x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <nav class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                    <a href="{{ route('dashboard') }}" class="text-teal-800 hover:underline">Account</a>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold">Support Desk</span>
                </nav>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">
                    {{ $isStaff ? 'National Support & Inquiry Helpdesk' : 'Customer Support Tickets' }}
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    {{ $isStaff ? 'Manage, assign, and resolve user inquiries, verification questions, and platform reports.' : 'Submit and track support inquiries for account, booking, verification, or technical assistance.' }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a
                    href="{{ route('support.tickets.create') }}"
                    class="px-4 py-2.5 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-xs font-bold shadow-xs transition flex items-center gap-1.5"
                >
                    <span>➕</span>
                    <span>Submit New Ticket</span>
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        @if($tickets->isEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center max-w-md mx-auto space-y-4 shadow-xs my-8">
                <div class="w-16 h-16 rounded-full bg-teal-50 text-teal-800 flex items-center justify-center text-3xl mx-auto">
                    🎫
                </div>
                <h2 class="text-lg font-bold text-slate-900">No Support Tickets</h2>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Have a question regarding listing verification, payments, or account settings? Our support officers are here to help.
                </p>
                <div class="pt-2">
                    <a
                        href="{{ route('support.tickets.create') }}"
                        class="inline-block px-5 py-2.5 rounded-xl bg-teal-800 text-white text-xs font-bold hover:bg-teal-900 transition shadow-xs"
                    >
                        Create Support Ticket
                    </a>
                </div>
            </div>
        @else
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-600 border-b border-slate-200 uppercase tracking-wider text-[11px]">
                                <th class="py-3.5 px-6 font-bold">Ticket Code</th>
                                <th class="py-3.5 px-4 font-bold">Subject &amp; Category</th>
                                <th class="py-3.5 px-4 font-bold">Priority</th>
                                <th class="py-3.5 px-4 font-bold">Status</th>
                                @if($isStaff)
                                    <th class="py-3.5 px-4 font-bold">User</th>
                                    <th class="py-3.5 px-4 font-bold">Assigned Staff</th>
                                @endif
                                <th class="py-3.5 px-4 font-bold">Created</th>
                                <th class="py-3.5 px-6 font-bold text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-800">
                            @foreach($tickets as $ticket)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3.5 px-6 font-mono font-bold text-slate-900">
                                        {{ $ticket->ticket_code }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-900 max-w-xs truncate">
                                            {{ $ticket->subject }}
                                        </div>
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 uppercase mt-0.5">
                                            {{ $ticket->category }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ in_array($ticket->priority, ['high', 'urgent']) ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-700' }}">
                                            {{ $ticket->priority }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $ticket->status === 'resolved' || $ticket->status === 'closed' ? 'bg-emerald-50 text-emerald-800' : ($ticket->status === 'in_progress' ? 'bg-sky-50 text-sky-800' : 'bg-amber-50 text-amber-800') }}">
                                            {{ str_replace('_', ' ', $ticket->status) }}
                                        </span>
                                    </td>
                                    @if($isStaff)
                                        <td class="py-3.5 px-4 text-slate-600">
                                            {{ $ticket->user?->name ?? 'User #' . $ticket->user_id }}
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-600">
                                            {{ $ticket->assignedStaff?->name ?? 'Unassigned' }}
                                        </td>
                                    @endif
                                    <td class="py-3.5 px-4 text-slate-500 text-[11px]">
                                        {{ $ticket->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="py-3.5 px-6 text-right">
                                        <a
                                            href="{{ route('support.tickets.show', $ticket->uuid) }}"
                                            class="px-3 py-1.5 rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-800 font-semibold transition"
                                        >
                                            View →
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-slate-100">
                    {{ $tickets->links() }}
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
