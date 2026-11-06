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
                            {{ __('app.dashboard.level_verified', ['level' => $user->verification_level]) }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 mt-1">
                        <span>📱 {{ $user->phone }}</span>
                        <span>•</span>
                        <span>{{ __('app.dashboard.privacy') }} <code class="bg-slate-100 px-1 py-0.5 rounded">{{ $user->phone_privacy?->value ?? 'public' }}</code></span>
                        <span>•</span>
                        <span>{{ __('app.dashboard.locale') }} <strong>{{ strtoupper($user->preferred_locale ?? 'en') }}</strong></span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a
                    href="{{ url('/listings/create') }}"
                    class="px-4 py-2 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-sm font-semibold transition"
                >
                    {{ __('app.dashboard.post_listing') }}
                </a>
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 text-sm font-semibold transition"
                    >
                        {{ __('app.dashboard.sign_out') }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Quick Role & Feature Portals (Context-Aware Direction) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <a href="{{ url('/search') }}" class="p-5 rounded-2xl bg-white border border-slate-200 hover:border-teal-500 hover:shadow-md transition group space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-2xl">🏠</span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-teal-700 bg-teal-50 px-2 py-0.5 rounded-full">{{ __('app.roles.buyer') }}</span>
                </div>
                <h3 class="text-base font-bold text-slate-900 group-hover:text-teal-700 transition">{{ __('app.dashboard.portal_buyer_title') }}</h3>
                <p class="text-xs text-slate-500 leading-relaxed">{{ __('app.dashboard.portal_buyer_desc') }}</p>
            </a>

            <a href="{{ url('/listings/create') }}" class="p-5 rounded-2xl bg-white border border-slate-200 hover:border-teal-500 hover:shadow-md transition group space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-2xl">🔑</span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full">{{ __('app.roles.property_owner') }}</span>
                </div>
                <h3 class="text-base font-bold text-slate-900 group-hover:text-teal-700 transition">{{ __('app.dashboard.portal_owner_title') }}</h3>
                <p class="text-xs text-slate-500 leading-relaxed">{{ __('app.dashboard.portal_owner_desc') }}</p>
            </a>

            <a href="{{ url('/crm/dashboard') }}" class="p-5 rounded-2xl bg-white border border-slate-200 hover:border-teal-500 hover:shadow-md transition group space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-2xl">💼</span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">{{ __('app.roles.agent') }}</span>
                </div>
                <h3 class="text-base font-bold text-slate-900 group-hover:text-teal-700 transition">{{ __('app.dashboard.portal_agent_title') }}</h3>
                <p class="text-xs text-slate-500 leading-relaxed">{{ __('app.dashboard.portal_agent_desc') }}</p>
            </a>

            <a href="{{ url('/host/dashboard') }}" class="p-5 rounded-2xl bg-white border border-slate-200 hover:border-teal-500 hover:shadow-md transition group space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-2xl">🏨</span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full">{{ __('app.roles.host') }}</span>
                </div>
                <h3 class="text-base font-bold text-slate-900 group-hover:text-teal-700 transition">{{ __('app.dashboard.portal_host_title') }}</h3>
                <p class="text-xs text-slate-500 leading-relaxed">{{ __('app.dashboard.portal_host_desc') }}</p>
            </a>

            <div class="p-5 rounded-2xl bg-white border border-slate-200 hover:border-purple-400 hover:shadow-md transition space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-2xl">📜</span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-purple-700 bg-purple-50 px-2 py-0.5 rounded-full">Phase 4</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">Rentals & Leases</h3>
                    <p class="text-xs text-slate-500 leading-relaxed">Tenant profiles, applications, contracts & receipts.</p>
                </div>
                <div class="pt-2 border-t border-slate-100 flex flex-col gap-1 text-xs">
                    <a href="{{ route('tenant.profile') }}" class="flex items-center justify-between px-2 py-1.5 rounded-lg text-slate-700 hover:text-purple-700 hover:bg-purple-50 font-medium transition">
                        <span>👤 Tenant Profile</span>
                        <span class="text-slate-400">&rarr;</span>
                    </a>
                    <a href="{{ route('rental-applications.index') }}" class="flex items-center justify-between px-2 py-1.5 rounded-lg text-slate-700 hover:text-purple-700 hover:bg-purple-50 font-medium transition">
                        <span>📋 Applications</span>
                        <span class="text-slate-400">&rarr;</span>
                    </a>
                    <a href="{{ route('leases.index') }}" class="flex items-center justify-between px-2 py-1.5 rounded-lg text-slate-700 hover:text-purple-700 hover:bg-purple-50 font-medium transition">
                        <span>📜 Leases & Rent</span>
                        <span class="text-slate-400">&rarr;</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Audit Log History -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 space-y-4 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">{{ __('app.dashboard.audit_trail_title') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('app.dashboard.audit_trail_desc') }}</p>
                </div>
                <span class="text-xs px-2.5 py-1 rounded bg-slate-100 text-slate-700 font-mono">{{ __('app.dashboard.immutable_badge') }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase font-semibold">
                        <tr>
                            <th class="px-4 py-3">{{ __('app.dashboard.event_action') }}</th>
                            <th class="px-4 py-3">{{ __('app.dashboard.entity_type') }}</th>
                            <th class="px-4 py-3">{{ __('app.dashboard.ip_address') }}</th>
                            <th class="px-4 py-3">{{ __('app.dashboard.timestamp') }}</th>
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
                                    {{ __('app.dashboard.no_audit_logs') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
