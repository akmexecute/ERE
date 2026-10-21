<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
        <!-- User Profile Card -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 shadow-xs">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-teal-800 text-white font-bold text-2xl flex items-center justify-center">
                    {{ strtoupper(substr($user->name, 0, 2)) }}
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-bold text-slate-900">{{ $user->name }}</h1>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                            Level {{ $user->verification_level }} Verified
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 mt-1">
                        <span>📱 {{ $user->phone }}</span>
                        <span>•</span>
                        <span>Privacy: <code class="bg-slate-100 px-1 py-0.5 rounded">{{ $user->phone_privacy->value }}</code></span>
                        <span>•</span>
                        <span>Locale: <strong>{{ strtoupper($user->preferred_locale) }}</strong></span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a
                    href="{{ url('/listings/create') }}"
                    class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-sm font-semibold transition"
                >
                    + Post New Listing
                </a>
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 text-sm font-semibold transition"
                    >
                        Sign Out
                    </button>
                </form>
            </div>
        </div>

        <!-- Audit Log History -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 space-y-4 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Immutable Audit Trail (SRS Section 142 &amp; 189)</h2>
                    <p class="text-xs text-slate-500">Append-only audit events logged by the Auditable trait</p>
                </div>
                <span class="text-xs px-2.5 py-1 rounded bg-slate-100 text-slate-700 font-mono">Immutable</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase font-semibold">
                        <tr>
                            <th class="px-4 py-3">Event Action</th>
                            <th class="px-4 py-3">Entity Type</th>
                            <th class="px-4 py-3">IP Address</th>
                            <th class="px-4 py-3">Timestamp (EC / GC)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($auditLogs as $log)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-teal-800">{{ $log->action }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</td>
                                <td class="px-4 py-3 font-mono text-slate-500">{{ $log->ip_address ?? '127.0.0.1' }}</td>
                                <td class="px-4 py-3">@ethDate($log->created_at)</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-slate-400">
                                    No audit logs recorded yet. Changes made to auditable entities will appear here.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
