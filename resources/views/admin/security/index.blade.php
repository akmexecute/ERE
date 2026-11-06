<x-app-layout>
    <div
        x-data="{
            showStatusModal: false,
            selectedUserId: null,
            selectedUserName: '',
            selectedCurrentStatus: '',
            targetStatus: 'active',
            statusReason: '',
            openStatusModal(id, name, status) {
                this.selectedUserId = id;
                this.selectedUserName = name;
                this.selectedCurrentStatus = status;
                this.targetStatus = status === 'active' ? 'suspended' : 'active';
                this.statusReason = '';
                this.showStatusModal = true;
            }
        }"
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6"
    >
        <!-- Urbano Header: Architectural Slate-900 Bar with Subtle Borders -->
        <div class="rounded-3xl bg-slate-900 text-white p-6 sm:p-8 border border-slate-800 shadow-xl relative overflow-hidden">
            <div class="relative z-10 flex flex-wrap items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-teal-400">
                        <a href="{{ url('/admin/dashboard') }}" class="hover:underline text-teal-300">Admin Operations</a>
                        <span class="text-slate-600">/</span>
                        <span>Module 01: Identity, Auth &amp; RBAC</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight mt-1 text-white">
                        Account Security &amp; Access Intelligence
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-2xl">
                        Real-time audit of OTP verifications, sign-in activity, failed authentication patterns, and account suspension enforcement.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <span class="px-3.5 py-1.5 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 text-xs font-semibold flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Audit Watchdog Active</span>
                    </span>
                    <a
                        href="{{ url('/admin/dashboard') }}"
                        class="px-4 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-600 text-white text-xs font-semibold shadow-md transition"
                    >
                        National Admin Center
                    </a>
                </div>
            </div>
        </div>

        @if(session('status'))
            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-950 text-xs font-semibold flex items-center gap-2 shadow-xs">
                <span class="text-emerald-700 text-sm">✓</span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- Security Intelligence KPI Metric Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Sign-in Attempts (30d)</span>
                <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ number_format($totalAttempts) }}</div>
                <div class="flex items-center gap-1 mt-1 text-[11px] text-teal-700 font-medium">
                    <span>Verified via Phone OTP</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Successful Logins</span>
                <div class="text-2xl font-extrabold text-emerald-700 mt-1">{{ number_format($successfulAttempts) }}</div>
                <div class="flex items-center gap-1 mt-1 text-[11px] text-slate-500">
                    <span>Authenticated sessions</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Failed / Rejected Codes</span>
                <div class="text-2xl font-extrabold text-amber-700 mt-1">{{ number_format($failedAttempts) }}</div>
                <div class="flex items-center gap-1 mt-1 text-[11px] text-amber-800 font-semibold">
                    <span>{{ $failureRate }}% Failure Rate</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-xs">
                <span class="text-xs font-medium text-slate-500">Suspicious Signals</span>
                <div class="text-2xl font-extrabold text-rose-700 mt-1">{{ number_format($suspiciousAttempts) }}</div>
                <div class="flex items-center gap-1 mt-1 text-[11px] text-slate-500">
                    <span>Throttled / Flagged IPs</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Sign-in History & OTP Events Table (2 Cols) -->
            <div class="lg:col-span-2 space-y-4">
                <!-- Filter Bar -->
                <div class="bg-white rounded-2xl border border-slate-200/90 p-4 shadow-xs space-y-3">
                    <form method="GET" action="{{ route('admin.security') }}" class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                        <!-- Search Input -->
                        <div class="relative w-full sm:w-72">
                            <input
                                type="text"
                                name="search"
                                value="{{ $currentSearch }}"
                                placeholder="Search phone, name or IP..."
                                class="w-full pl-8 pr-4 py-2 text-xs rounded-xl border border-slate-300 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20"
                            >
                            <span class="absolute left-2.5 top-2.5 text-slate-400">🔍</span>
                        </div>

                        <!-- Status & Time Controls -->
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <select name="status" class="px-2.5 py-1.5 rounded-xl border border-slate-300 text-xs">
                                <option value="all" {{ $currentStatus === 'all' ? 'selected' : '' }}>All Statuses</option>
                                <option value="success" {{ $currentStatus === 'success' ? 'selected' : '' }}>Success Only</option>
                                <option value="failed" {{ $currentStatus === 'failed' ? 'selected' : '' }}>Failed Only</option>
                                <option value="suspicious" {{ $currentStatus === 'suspicious' ? 'selected' : '' }}>Suspicious Only</option>
                            </select>

                            <select name="time_range" class="px-2.5 py-1.5 rounded-xl border border-slate-300 text-xs">
                                <option value="24h" {{ $currentTimeRange === '24h' ? 'selected' : '' }}>Past 24 Hours</option>
                                <option value="7d" {{ $currentTimeRange === '7d' ? 'selected' : '' }}>Past 7 Days</option>
                                <option value="30d" {{ $currentTimeRange === '30d' ? 'selected' : '' }}>Past 30 Days</option>
                            </select>

                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-900 text-white font-semibold hover:bg-slate-800 transition">
                                Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- History Table -->
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-slate-900">Authentication &amp; Sign-In History</h2>
                        <span class="text-xs text-slate-500 font-mono">{{ $histories->total() }} total recorded attempts</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50/70 border-b border-slate-200 text-slate-600 uppercase font-semibold text-[11px]">
                                    <th class="py-3 px-4">Identifier / User</th>
                                    <th class="py-3 px-3">Method</th>
                                    <th class="py-3 px-3">Status</th>
                                    <th class="py-3 px-3">IP &amp; Device</th>
                                    <th class="py-3 px-4 text-right">Timestamp</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($histories as $h)
                                    @php
                                        // Privacy masking for phone numbers in administrative view: show +251 9... and last 4 digits
                                        $phoneStr = (string) $h->phone_or_email;
                                        $maskedPhone = strlen($phoneStr) >= 10
                                            ? substr($phoneStr, 0, 5) . '•••' . substr($phoneStr, -4)
                                            : $phoneStr;
                                    @endphp
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="py-3 px-4">
                                            <div class="font-mono font-bold text-slate-900">{{ $maskedPhone }}</div>
                                            @if($h->user_name)
                                                <div class="text-[11px] text-slate-500">{{ $h->user_name }}</div>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3">
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase bg-slate-100 text-slate-700">
                                                {{ $h->auth_method }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-3">
                                            @if($h->is_suspicious)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                                    Suspicious
                                                </span>
                                            @elseif($h->succeeded)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                    Success
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                                    Failed Code
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-slate-600 font-mono text-[11px]">
                                            <div>{{ $h->ip_address ?? '127.0.0.1' }}</div>
                                            <div class="text-[10px] text-slate-400 font-sans truncate max-w-[150px]">
                                                {{ $h->user_agent ?? 'Browser' }}
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-right text-slate-500 whitespace-nowrap">
                                            <div>{{ \Carbon\Carbon::parse($h->created_at)->format('M d, H:i:s') }}</div>
                                            <div class="text-[10px] text-slate-400">@ethDate(\Carbon\Carbon::parse($h->created_at))</div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-slate-400">
                                            No login events matching the criteria found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($histories->hasPages())
                        <div class="px-6 py-3 border-t border-slate-100 bg-slate-50/50">
                            {{ $histories->links() }}
                        </div>
                    @endif
                </div>
            </div>

            <!-- Right: Suspended Accounts & Security Audit Trails (1 Col) -->
            <div class="space-y-6">
                <!-- Suspended Accounts Card -->
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-5 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h2 class="text-sm font-bold text-slate-900">Restricted &amp; Suspended Accounts</h2>
                        <span class="text-xs px-2 py-0.5 rounded-full font-bold bg-rose-50 text-rose-800">
                            {{ count($suspendedUsers) }} accounts
                        </span>
                    </div>

                    <div class="space-y-3">
                        @forelse($suspendedUsers as $susUser)
                            <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between text-xs">
                                <div>
                                    <div class="font-bold text-slate-900">{{ $susUser->name }}</div>
                                    <div class="font-mono text-slate-500 text-[11px]">{{ $susUser->phone }}</div>
                                    <span class="inline-block mt-1 text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-rose-100 text-rose-900">
                                        {{ $susUser->status }}
                                    </span>
                                </div>

                                <button
                                    type="button"
                                    @click="openStatusModal({{ $susUser->id }}, '{{ addslashes($susUser->name) }}', '{{ $susUser->status }}')"
                                    class="px-2.5 py-1 rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 font-semibold text-[11px] transition shadow-2xs"
                                >
                                    Modify
                                </button>
                            </div>
                        @empty
                            <div class="text-center py-6 text-xs text-slate-400">
                                No suspended or banned accounts on file.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Recent Administrative Security Actions (Audit Trail) -->
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-5 space-y-3">
                    <div class="border-b border-slate-100 pb-3">
                        <h2 class="text-sm font-bold text-slate-900">Immutable Security Audit Trail</h2>
                        <p class="text-[11px] text-slate-500">Append-only administrative event log (SRS Section 142)</p>
                    </div>

                    <div class="space-y-2.5">
                        @forelse($securityLogs as $log)
                            <div class="text-xs p-2.5 rounded-xl bg-slate-50/70 border border-slate-100 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono font-semibold text-slate-800 text-[11px]">{{ $log->action }}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">{{ $log->created_at->diffForHumans() }}</span>
                                </div>
                                @if($log->reason)
                                    <div class="text-slate-600 text-[11px]">Reason: {{ $log->reason }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-4 text-xs text-slate-400">
                                No security actions logged yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Modify Account Status Modal -->
        <div
            x-show="showStatusModal"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div
                @click.away="showStatusModal = false"
                class="bg-white rounded-3xl border border-slate-200 max-w-md w-full p-6 space-y-4 shadow-2xl"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Modify Account Access</h3>
                    <button type="button" @click="showStatusModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form
                    method="POST"
                    :action="'/admin/security/users/' + selectedUserId + '/status'"
                    class="space-y-3.5 text-xs"
                >
                    @csrf

                    <div>
                        <span class="text-slate-500">Target User:</span>
                        <div class="text-sm font-bold text-slate-900" x-text="selectedUserName"></div>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">New Account Status</label>
                        <select name="status" x-model="targetStatus" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs">
                            <option value="active">Active (Permit Authentication &amp; System Access)</option>
                            <option value="suspended">Suspended (Temporarily Revoke All Permissions)</option>
                            <option value="banned">Banned (Permanently Block Phone &amp; Identity)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Mandatory Administrative Audit Reason</label>
                        <textarea
                            name="reason"
                            x-model="statusReason"
                            required
                            rows="3"
                            placeholder="State administrative justification for compliance and audit trail..."
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showStatusModal = false"
                            class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 text-xs font-semibold"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="!statusReason"
                            class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 disabled:opacity-50 text-white text-xs font-semibold shadow-sm"
                        >
                            Save Status Change
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
