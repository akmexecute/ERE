<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
        <!-- Header & Breadcrumbs -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-6">
            <div>
                <nav class="text-xs text-slate-500 mb-1 flex items-center gap-2">
                    <a href="{{ url('/dashboard') }}" class="hover:text-teal-700">Dashboard</a>
                    <span>/</span>
                    <span class="text-slate-800 font-medium">Maintenance & Work Orders</span>
                </nav>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Maintenance Requests & Work Orders</h1>
                <p class="text-sm text-slate-600 mt-1">Track work orders, dispatch technicians, log repairs, and record verified ETB costs.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('maintenance.create') }}" class="px-4 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-sm font-semibold transition shadow-xs flex items-center gap-2">
                    <span>+</span>
                    <span>Report Maintenance Issue</span>
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
            <form method="GET" action="{{ route('maintenance.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                    <select name="status" class="w-full text-xs rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Category</label>
                    <select name="category" class="w-full text-xs rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->value }}" {{ request('category') === $category->value ? 'selected' : '' }}>
                                {{ $category->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Priority</label>
                    <select name="priority" class="w-full text-xs rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500">
                        <option value="">All Priorities</option>
                        @foreach($priorities as $priority)
                            <option value="{{ $priority->value }}" {{ request('priority') === $priority->value ? 'selected' : '' }}>
                                {{ $priority->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="w-full py-2 px-3 rounded-xl bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 transition">
                        Apply Filters
                    </button>
                    @if(request()->hasAny(['status', 'category', 'priority']))
                        <a href="{{ route('maintenance.index') }}" class="py-2 px-3 rounded-xl border border-slate-300 text-slate-600 text-xs hover:bg-slate-50 transition">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Tickets Table -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase font-semibold">
                        <tr>
                            <th class="px-5 py-3.5">Ticket Code</th>
                            <th class="px-5 py-3.5">Property & Location</th>
                            <th class="px-5 py-3.5">Category & Title</th>
                            <th class="px-5 py-3.5">Priority</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5">Assigned To</th>
                            <th class="px-5 py-3.5">Cost (ETB)</th>
                            <th class="px-5 py-3.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($tickets as $ticket)
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="px-5 py-4 font-mono font-semibold text-teal-800">
                                    {{ $ticket->ticket_code }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-slate-900">{{ $ticket->property?->title_en ?? 'Property #'.$ticket->property_id }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $ticket->property?->property_code }}</div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-medium text-slate-900">{{ $ticket->title }}</div>
                                    <span class="inline-block mt-0.5 px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700">
                                        {{ $ticket->category->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    @php
                                        $priorityClass = match($ticket->priority->value) {
                                            'emergency' => 'bg-red-100 text-red-800 border-red-200',
                                            'high' => 'bg-amber-100 text-amber-800 border-amber-200',
                                            'normal' => 'bg-blue-100 text-blue-800 border-blue-200',
                                            default => 'bg-slate-100 text-slate-700 border-slate-200',
                                        };
                                    @endphp
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold border {{ $priorityClass }}">
                                        {{ $ticket->priority->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
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
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold border {{ $statusClass }}">
                                        {{ $ticket->status->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-slate-600">
                                    {{ $ticket->assignedTechnician?->name ?? ($ticket->contractor_name ? 'Contractor: '.$ticket->contractor_name : 'Unassigned') }}
                                </td>
                                <td class="px-5 py-4 font-mono font-medium">
                                    {{ number_format((float) ($ticket->actual_cost_etb > 0 ? $ticket->actual_cost_etb : $ticket->estimated_cost_etb), 2) }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('maintenance.show', $ticket->uuid) }}" class="px-3 py-1.5 rounded-lg bg-teal-50 text-teal-700 hover:bg-teal-100 font-semibold transition">
                                        View &rarr;
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-12 text-center text-slate-400">
                                    <p class="text-sm">No maintenance requests found.</p>
                                    <a href="{{ route('maintenance.create') }}" class="inline-block mt-3 text-teal-700 font-semibold hover:underline">
                                        + Submit a maintenance issue
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($tickets->hasPages())
                <div class="p-4 border-t border-slate-200">
                    {{ $tickets->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
