<x-app-layout>
    <x-slot name="title">Notification Preferences — Ethiopian Real Estate Marketplace</x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <!-- Header -->
        <div>
            <nav class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                <a href="{{ route('dashboard') }}" class="text-teal-800 hover:underline">Account</a>
                <span>/</span>
                <span class="text-slate-800 font-semibold">Notifications</span>
            </nav>
            <h1 class="text-2xl font-extrabold text-slate-900">
                Notification Preferences
            </h1>
            <p class="text-xs text-slate-500 mt-1">
                Customize how and where you receive inquiry leads, price alerts, and status changes across in-app, SMS, email, and Telegram.
            </p>
        </div>

        @if (session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('notifications.preferences.update') }}" class="bg-white rounded-2xl border border-slate-200 shadow-xs divide-y divide-slate-100">
            @csrf

            <!-- Essential Service Notifications Notice -->
            <div class="p-6 bg-slate-50/70 rounded-t-2xl space-y-1">
                <span class="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                    <span>🛡️</span>
                    <span>Essential Service Notifications</span>
                </span>
                <p class="text-xs text-slate-500">
                    Mandatory account security alerts, OTP sign-in codes, moderation notices, and legal disclosures are always delivered regardless of marketing settings.
                </p>
            </div>

            <!-- Delivery Channels -->
            <div class="p-6 space-y-5">
                <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider text-teal-800">
                    Delivery Channels (SRS Section 81)
                </h2>

                <div class="space-y-4">
                    <!-- In-App -->
                    <label class="flex items-start justify-between gap-4 cursor-pointer">
                        <div class="space-y-0.5">
                            <span class="text-xs font-bold text-slate-900 block">In-App Notifications</span>
                            <span class="text-xs text-slate-500 block">Receive instant badge updates and notifications within the platform dashboard.</span>
                        </div>
                        <input
                            type="checkbox"
                            name="channel_in_app"
                            value="1"
                            {{ $preferences->channel_in_app ? 'checked' : '' }}
                            class="rounded border-slate-300 text-teal-800 focus:ring-teal-800 mt-1"
                        >
                    </label>

                    <!-- Email -->
                    <label class="flex items-start justify-between gap-4 cursor-pointer">
                        <div class="space-y-0.5">
                            <span class="text-xs font-bold text-slate-900 block">Email Alerts</span>
                            <span class="text-xs text-slate-500 block">Receive inquiry updates, saved-search matches, and daily market digests to your registered email.</span>
                        </div>
                        <input
                            type="checkbox"
                            name="channel_email"
                            value="1"
                            {{ $preferences->channel_email ? 'checked' : '' }}
                            class="rounded border-slate-300 text-teal-800 focus:ring-teal-800 mt-1"
                        >
                    </label>

                    <!-- SMS -->
                    <label class="flex items-start justify-between gap-4 cursor-pointer">
                        <div class="space-y-0.5">
                            <span class="text-xs font-bold text-slate-900 block">SMS (Short Message Service)</span>
                            <span class="text-xs text-slate-500 block">Receive urgent buyer inquiry alerts and critical price drops directly to your Ethiopian mobile number.</span>
                        </div>
                        <input
                            type="checkbox"
                            name="channel_sms"
                            value="1"
                            {{ $preferences->channel_sms ? 'checked' : '' }}
                            class="rounded border-slate-300 text-teal-800 focus:ring-teal-800 mt-1"
                        >
                    </label>

                    <!-- Push -->
                    <label class="flex items-start justify-between gap-4 cursor-pointer">
                        <div class="space-y-0.5">
                            <span class="text-xs font-bold text-slate-900 block">Web / Mobile Push Notifications</span>
                            <span class="text-xs text-slate-500 block">Receive real-time push alerts on your desktop or mobile browser.</span>
                        </div>
                        <input
                            type="checkbox"
                            name="channel_push"
                            value="1"
                            {{ $preferences->channel_push ? 'checked' : '' }}
                            class="rounded border-slate-300 text-teal-800 focus:ring-teal-800 mt-1"
                        >
                    </label>

                    <!-- Telegram -->
                    <label class="flex items-start justify-between gap-4 cursor-pointer">
                        <div class="space-y-0.5">
                            <span class="text-xs font-bold text-slate-900 block">Telegram Messenger</span>
                            <span class="text-xs text-slate-500 block">Receive instant listing alerts and chat messages through the official ERE Telegram Bot.</span>
                        </div>
                        <input
                            type="checkbox"
                            name="channel_telegram"
                            value="1"
                            {{ $preferences->channel_telegram ? 'checked' : '' }}
                            class="rounded border-slate-300 text-teal-800 focus:ring-teal-800 mt-1"
                        >
                    </label>
                </div>
            </div>

            <!-- Marketing & Market Intelligence Opt-In -->
            <div class="p-6 space-y-4">
                <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider text-teal-800">
                    Market Intelligence &amp; Promotional Updates
                </h2>

                <label class="flex items-start justify-between gap-4 cursor-pointer">
                    <div class="space-y-0.5">
                        <span class="text-xs font-bold text-slate-900 block">Platform Asking-Price Indicator Digests</span>
                        <span class="text-xs text-slate-500 block">Receive weekly national market trends, city liquidity analyses, and platform news.</span>
                    </div>
                    <input
                        type="checkbox"
                        name="marketing_opt_in"
                        value="1"
                        {{ $preferences->marketing_opt_in ? 'checked' : '' }}
                        class="rounded border-slate-300 text-teal-800 focus:ring-teal-800 mt-1"
                    >
                </label>
            </div>

            <div class="p-6 bg-slate-50/50 rounded-b-2xl flex items-center justify-end">
                <button
                    type="submit"
                    class="px-6 py-2.5 rounded-xl bg-teal-800 hover:bg-teal-900 text-white text-xs font-bold shadow-xs transition"
                >
                    Save Preferences
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
