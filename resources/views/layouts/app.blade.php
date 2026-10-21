<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'EthioProperty — National Real Estate Marketplace & Stays' }}</title>

    <!-- Preconnect & subsetted fonts for Latin (en/om) & Ethiopic (am) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', 'Noto Sans Ethiopic', system-ui, sans-serif;
            line-height: 1.6;
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-full bg-slate-50 text-slate-900 flex flex-col" x-data>

    <!-- Top Utility Bar: Language (en / am / om) + Ethiopian Calendar Toggle + Currency -->
    <div class="bg-slate-900 text-slate-200 text-xs border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 text-emerald-400 font-medium">
                    <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                    Ethiopia National Property Core
                </span>
                <span class="hidden sm:inline text-slate-500">|</span>
                <span class="hidden sm:inline text-slate-300">Currency: <strong>ETB (ብር)</strong></span>
            </div>

            <div class="flex items-center gap-4">
                <!-- Ethiopian vs Gregorian Calendar Toggle (SRS Section 125) -->
                <button
                    type="button"
                    @click="$store.preferences.toggleCalendar()"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-100 transition"
                >
                    <span>📅</span>
                    <span x-text="$store.preferences.calendar === 'ethiopian' ? 'ኢትዮጵያዊ ቀን (EC)' : 'Gregorian (GC)'"></span>
                </button>

                <!-- Trilingual Switcher (SRS Section 124) -->
                <div class="inline-flex items-center rounded bg-slate-800 p-0.5">
                    @foreach(['en' => 'English', 'am' => 'አማርኛ', 'om' => 'Afaan Oromo'] as $code => $label)
                        <a
                            href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}"
                            class="px-2 py-0.5 rounded text-xs font-medium transition {{ app()->getLocale() === $code ? 'bg-emerald-600 text-white' : 'text-slate-300 hover:text-white' }}"
                        >
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Primary Marketplace Navigation (SRS Section 163) -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-8">
                <a href="{{ url('/') }}" class="flex items-center gap-2 font-bold text-xl tracking-tight text-teal-800">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-teal-700 text-white font-bold">EP</span>
                    <span>EthioProperty</span>
                </a>

                <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-slate-700">
                    <a href="{{ url('/search?mode=BUY') }}" class="hover:text-teal-700 transition">Buy</a>
                    <a href="{{ url('/search?mode=RENT') }}" class="hover:text-teal-700 transition">Rent</a>
                    <a href="{{ url('/search?mode=LEASE') }}" class="hover:text-teal-700 transition">Lease</a>
                    <a href="{{ url('/search?category=land') }}" class="hover:text-teal-700 transition">Land</a>
                    <span class="inline-flex items-center gap-1.5 text-slate-400 cursor-not-allowed" title="Launching in Phase 2">
                        Stay
                        <span class="text-[10px] uppercase px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-semibold">Phase 2</span>
                    </span>
                </nav>
            </div>

            <div class="flex items-center gap-3">
                <a
                    href="{{ url('/listings/create') }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-teal-700 hover:bg-teal-800 text-white text-sm font-semibold shadow-sm transition"
                >
                    <span>+ Post Property</span>
                </a>

                @auth
                    <div class="flex items-center gap-2">
                        <a href="{{ url('/dashboard') }}" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-100">
                            {{ auth()->user()->name }}
                        </a>
                        <form method="POST" action="{{ url('/logout') }}">
                            @csrf
                            <button type="submit" class="px-2.5 py-1.5 text-xs text-slate-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition">
                                Logout
                            </button>
                        </form>
                    </div>
                @else
                    <a href="{{ url('/login') }}" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-100">
                        Login / Register
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1">
        {{ $slot }}
    </main>

    <!-- Global Toast Notification Component -->
    <div
        x-data="{ notifications: [] }"
        @notify.window="notifications.push({ id: Date.now(), message: $event.detail.message, type: $event.detail.type || 'success' }); setTimeout(() => notifications.shift(), 4500)"
        class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none"
    >
        @if (session('status'))
            <div
                x-data="{ show: true }"
                x-show="show"
                x-init="setTimeout(() => show = false, 4500)"
                class="pointer-events-auto rounded-xl px-4 py-3 shadow-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm font-medium flex items-center justify-between"
            >
                <span>{{ session('status') }}</span>
                <button type="button" @click="show = false" class="text-emerald-700 font-bold ml-2">&times;</button>
            </div>
        @endif

        <template x-for="item in notifications" :key="item.id">
            <div
                class="pointer-events-auto rounded-xl px-4 py-3 shadow-lg border text-sm font-medium flex items-center justify-between"
                :class="item.type === 'error' ? 'bg-red-50 border-red-200 text-red-900' : 'bg-emerald-50 border-emerald-200 text-emerald-900'"
            >
                <span x-text="item.message"></span>
            </div>
        </template>
    </div>

    <!-- Alpine Global Store Initialization -->
    <script>
        document.addEventListener('alpine:init', () => {
            if (!Alpine.store('preferences')) {
                Alpine.store('preferences', {
                    calendar: localStorage.getItem('ep_calendar') || 'ethiopian',
                    toggleCalendar() {
                        this.calendar = this.calendar === 'ethiopian' ? 'gregorian' : 'ethiopian';
                        localStorage.setItem('ep_calendar', this.calendar);
                    }
                });
            }
        });
    </script>
</body>
</html>
