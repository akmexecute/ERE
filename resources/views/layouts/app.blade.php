<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'EthioProperty — National Real Estate Marketplace & Stays' }}</title>
    {{ $meta ?? '' }}

    <!-- Preconnect & subsetted fonts for Latin (en/om) & Ethiopic (am) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', 'Noto Sans Ethiopic', system-ui, sans-serif;
            line-height: 1.6;
        }
        [x-cloak] { display: none !important; }
    </style>

    <script>
        window.MAP_CONFIG = {
            satelliteUrl: @js(config('services.maps.satellite_url')),
            satelliteAttribution: @js(config('services.maps.satellite_attribution'))
        };
    </script>
</head>
<body class="min-h-full bg-slate-50 text-slate-900 flex flex-col" x-data="{ mobileMenuOpen: false }">

    <!-- Top Utility Bar: Language (en / am / om) + Ethiopian Calendar Toggle + Currency -->
    <div class="bg-slate-900 text-slate-200 text-xs border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 text-emerald-400 font-medium">
                    <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                    {{ __('app.tagline') }}
                </span>
                <span class="hidden sm:inline text-slate-500">|</span>
                <span class="hidden sm:inline text-slate-300">{{ __('app.currency') }}</span>
            </div>

            <div class="flex items-center gap-4">
                <!-- Ethiopian vs Gregorian Calendar Toggle (SRS Section 125) -->
                <button
                    type="button"
                    @click="$store.preferences.toggleCalendar()"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-100 transition"
                >
                    <span>📅</span>
                    <span x-text="($store.preferences?.calendar ?? 'ethiopian') === 'ethiopian' ? 'ኢትዮጵያዊ ቀን (EC)' : 'Gregorian (GC)'">ኢትዮጵያዊ ቀን (EC)</span>
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

    <!-- Primary Marketplace Navigation (Urbano Minimalist Clean Header) -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-8">
                <a href="{{ url('/') }}" class="flex items-center gap-2 font-bold text-xl tracking-tight text-teal-800">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-slate-900 text-white font-bold border border-slate-800">EP</span>
                    <span class="tracking-tight text-slate-900 font-extrabold">EthioProperty</span>
                </a>

                <nav class="hidden lg:flex items-center gap-6 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                    <a href="{{ url('/search?mode=BUY') }}" class="hover:text-teal-700 transition">{{ __('app.nav.buy') }}</a>
                    <a href="{{ url('/search?mode=RENT') }}" class="hover:text-teal-700 transition">{{ __('app.nav.rent') }}</a>
                    <a href="{{ url('/search?mode=LEASE') }}" class="hover:text-teal-700 transition">{{ __('app.nav.lease') }}</a>
                    <a href="{{ url('/search?category=land') }}" class="hover:text-teal-700 transition">{{ __('app.nav.land') }}</a>
                    <a href="{{ route('stays.index') }}" class="hover:text-teal-700 transition inline-flex items-center gap-1">
                        {{ __('app.nav.stay') }}
                        <span class="text-[9px] uppercase px-1.5 py-0.5 rounded-full bg-teal-50 text-teal-700 border border-teal-200 font-bold">Stays</span>
                    </a>
                    <a href="{{ url('/projects/bole-tower-residences') }}" class="hover:text-teal-700 transition inline-flex items-center gap-1">
                        {{ __('app.nav.developments') }}
                        <span class="text-[9px] uppercase px-1.5 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 font-bold">Projects</span>
                    </a>
                    <a href="{{ route('subscriptions.index') }}" class="hover:text-teal-700 transition inline-flex items-center gap-1">
                        {{ __('Plans') }}
                        <span class="text-[9px] uppercase px-1.5 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200 font-bold">Pro</span>
                    </a>
                </nav>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <a
                    href="{{ url('/listings/create') }}"
                    class="hidden sm:inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-semibold shadow-xs transition"
                >
                    <span>+</span>
                    <span>{{ __('app.nav.post_property') }}</span>
                </a>

                @auth
                    <!-- User & Tenant Navigation Dropdown -->
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button
                            type="button"
                            @click="open = !open"
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-medium text-slate-700 hover:bg-slate-100 transition border border-slate-200"
                        >
                            <span class="w-6 h-6 rounded-lg bg-slate-900 text-white font-bold text-[10px] flex items-center justify-center">
                                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                            </span>
                            <span class="max-w-[100px] truncate hidden sm:inline">{{ auth()->user()->name }}</span>
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <div
                            x-show="open"
                            x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute right-0 mt-2 w-64 rounded-2xl bg-white shadow-xl border border-slate-200 py-2 z-50 text-xs"
                        >
                            <div class="px-4 py-2.5 border-b border-slate-100">
                                <div class="flex items-center justify-between">
                                    <p class="font-bold text-slate-900 truncate">{{ auth()->user()->name }}</p>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        Lvl {{ auth()->user()->verification_level }}
                                    </span>
                                </div>
                                <p class="text-slate-500 font-mono text-[11px] mt-0.5">{{ auth()->user()->phone }}</p>
                            </div>

                            <div class="py-1">
                                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-4 py-2 text-slate-800 hover:bg-slate-50 hover:text-teal-700 font-semibold transition">
                                    <span>📊</span> My Dashboard
                                </a>
                                <a href="{{ route('listings.create') }}" class="flex items-center gap-2.5 px-4 py-2 text-slate-700 hover:bg-slate-50 hover:text-teal-700 font-medium transition">
                                    <span>➕</span> Create Listing / Post Property
                                </a>
                                <a href="{{ route('messages.inbox') }}" class="flex items-center gap-2.5 px-4 py-2 text-slate-700 hover:bg-slate-50 hover:text-teal-700 font-medium transition">
                                    <span>💬</span> Messages &amp; Inquiries
                                </a>
                                <a href="{{ route('favorites.index') }}" class="flex items-center gap-2.5 px-4 py-2 text-slate-700 hover:bg-slate-50 hover:text-teal-700 font-medium transition">
                                    <span>⭐</span> Saved Favorites
                                </a>
                                <a href="{{ url('/dashboard?tab=stays') }}" class="flex items-center gap-2.5 px-4 py-2 text-slate-700 hover:bg-slate-50 hover:text-teal-700 font-medium transition">
                                    <span>🏨</span> My Stays &amp; Host Operations
                                </a>
                                <a href="{{ url('/dashboard?tab=purchases') }}" class="flex items-center gap-2.5 px-4 py-2 text-slate-700 hover:bg-slate-50 hover:text-teal-700 font-medium transition">
                                    <span>🛍️</span> My Purchases &amp; Bookings
                                </a>
                                <a href="{{ route('subscriptions.index') }}" class="flex items-center gap-2.5 px-4 py-2 text-slate-700 hover:bg-slate-50 hover:text-teal-700 font-medium transition">
                                    <span>👑</span> Membership &amp; Upgrades
                                </a>
                            </div>

                            @if(auth()->user()->isAgent() || auth()->user()->companies()->exists() || auth()->user()->hasRole('developer'))
                                <div class="border-t border-slate-100 my-1"></div>
                                <div class="px-4 py-1 text-[10px] uppercase font-bold text-slate-400 tracking-wider">Enterprise &amp; Agency</div>

                                <a href="{{ route('crm.dashboard') }}" class="flex items-center gap-2.5 px-4 py-1.5 text-slate-700 hover:bg-slate-50 hover:text-teal-700 font-medium transition">
                                    <span>💼</span> Agent CRM &amp; Pipeline
                                </a>
                                <a href="{{ route('portfolio.dashboard') }}" class="flex items-center gap-2.5 px-4 py-1.5 text-slate-700 hover:bg-slate-50 hover:text-teal-700 font-medium transition">
                                    <span>🏢</span> Portfolio Dashboard
                                </a>
                                <a href="{{ route('portfolio.matrix') }}" class="flex items-center gap-2.5 px-4 py-1.5 text-slate-700 hover:bg-slate-50 hover:text-teal-700 font-medium transition">
                                    <span>🔲</span> Unit Inventory Matrix
                                </a>
                                <a href="{{ route('invoices.index') }}" class="flex items-center gap-2.5 px-4 py-1.5 text-slate-700 hover:bg-slate-50 hover:text-teal-700 font-medium transition">
                                    <span>🧾</span> Billing &amp; Invoices
                                </a>
                            @endif

                            @if(auth()->user()->isStaff())
                                <div class="border-t border-slate-100 my-1"></div>
                                <div class="px-4 py-1 text-[10px] uppercase font-bold text-teal-800 tracking-wider">Administration</div>

                                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 px-4 py-1.5 text-slate-700 hover:bg-slate-50 hover:text-teal-700 font-medium transition">
                                    <span>🏛️</span> Admin Center
                                </a>
                                <a href="{{ route('admin.moderation.index') }}" class="flex items-center gap-2.5 px-4 py-1.5 text-slate-700 hover:bg-slate-50 hover:text-teal-700 font-medium transition">
                                    <span>⚖️</span> Moderation Queue
                                </a>
                                <a href="{{ route('admin.security') }}" class="flex items-center gap-2.5 px-4 py-1.5 text-slate-700 hover:bg-slate-50 hover:text-teal-700 font-medium transition">
                                    <span>🛡️</span> Security &amp; Access
                                </a>
                            @endif

                            <div class="border-t border-slate-100 my-1"></div>

                            <form method="POST" action="{{ url('/logout') }}">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-2.5 px-4 py-2 text-left text-red-600 hover:bg-red-50 font-medium transition">
                                    <span>🚪</span> {{ __('app.nav.logout') }}
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ url('/login') }}" class="px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-100 border border-slate-200 transition">
                        {{ __('app.nav.login') }}
                    </a>
                @endauth

                <!-- Urbano Signature Hamburger Menu Button -->
                <button
                    type="button"
                    @click="mobileMenuOpen = true"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white border border-slate-800 transition text-xs font-semibold shadow-xs"
                    aria-label="Open Platform Navigation Menu"
                >
                    <span class="flex flex-col gap-1 w-3.5">
                        <span class="block h-0.5 w-3.5 bg-white rounded-full"></span>
                        <span class="block h-0.5 w-2.5 bg-teal-400 rounded-full"></span>
                        <span class="block h-0.5 w-3.5 bg-white rounded-full"></span>
                    </span>
                    <span class="font-bold tracking-wider text-[11px] uppercase">Menu</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Urbano Architectural Slide-Over Drawer Navigation (SRS Sections 124, 163 & 214) -->
    <div
        x-show="mobileMenuOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-hidden"
        aria-modal="true"
    >
        <!-- Backdrop Blur -->
        <div
            x-show="mobileMenuOpen"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="mobileMenuOpen = false"
            class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity"
        ></div>

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <div
                x-show="mobileMenuOpen"
                x-transition:enter="transform transition ease-in-out duration-300"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transform transition ease-in-out duration-300"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
                class="w-screen max-w-md bg-slate-900 text-white border-l border-slate-800 shadow-2xl flex flex-col justify-between"
            >
                <!-- Drawer Header -->
                <div class="p-6 border-b border-slate-800 flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-teal-400">
                            <span>ERE Marketplace</span>
                            <span class="text-slate-600">/</span>
                            <span>Platform Explorer</span>
                        </div>
                        <h2 class="text-xl font-extrabold tracking-tight text-white mt-1">EthioProperty</h2>
                    </div>

                    <button
                        type="button"
                        @click="mobileMenuOpen = false"
                        class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition"
                        aria-label="Close Menu"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Drawer Content Scrollable Area -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6 text-sm divide-y divide-slate-800/80">
                    <!-- Marketplace & Listings Section -->
                    <div class="space-y-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-teal-400">Marketplace &amp; Discovery</div>
                        <div class="grid grid-cols-2 gap-2 text-xs font-medium">
                            <a href="{{ url('/search?mode=BUY') }}" class="p-3 rounded-xl bg-slate-800/70 hover:bg-slate-800 border border-slate-800 text-slate-200 hover:text-teal-300 transition flex items-center justify-between">
                                <span>{{ __('app.nav.buy') }}</span>
                                <span class="text-slate-500">→</span>
                            </a>
                            <a href="{{ url('/search?mode=RENT') }}" class="p-3 rounded-xl bg-slate-800/70 hover:bg-slate-800 border border-slate-800 text-slate-200 hover:text-teal-300 transition flex items-center justify-between">
                                <span>{{ __('app.nav.rent') }}</span>
                                <span class="text-slate-500">→</span>
                            </a>
                            <a href="{{ url('/search?mode=LEASE') }}" class="p-3 rounded-xl bg-slate-800/70 hover:bg-slate-800 border border-slate-800 text-slate-200 hover:text-teal-300 transition flex items-center justify-between">
                                <span>{{ __('app.nav.lease') }}</span>
                                <span class="text-slate-500">→</span>
                            </a>
                            <a href="{{ url('/search?category=land') }}" class="p-3 rounded-xl bg-slate-800/70 hover:bg-slate-800 border border-slate-800 text-slate-200 hover:text-teal-300 transition flex items-center justify-between">
                                <span>{{ __('app.nav.land') }}</span>
                                <span class="text-slate-500">→</span>
                            </a>
                            <a href="{{ route('stays.index') }}" class="p-3 rounded-xl bg-slate-800/70 hover:bg-slate-800 border border-slate-800 text-slate-200 hover:text-teal-300 transition flex items-center justify-between col-span-2">
                                <div class="flex items-center gap-2">
                                    <span>{{ __('app.nav.stay') }}</span>
                                    <span class="text-[9px] uppercase px-1.5 py-0.5 rounded-full bg-teal-900/80 text-teal-300 border border-teal-700">Verified Stays</span>
                                </div>
                                <span class="text-slate-500">→</span>
                            </a>
                            <a href="{{ url('/projects/bole-tower-residences') }}" class="p-3 rounded-xl bg-slate-800/70 hover:bg-slate-800 border border-slate-800 text-slate-200 hover:text-teal-300 transition flex items-center justify-between col-span-2">
                                <div class="flex items-center gap-2">
                                    <span>{{ __('app.nav.developments') }}</span>
                                    <span class="text-[9px] uppercase px-1.5 py-0.5 rounded-full bg-amber-900/80 text-amber-300 border border-amber-700">New Projects</span>
                                </div>
                                <span class="text-slate-500">→</span>
                            </a>
                        </div>
                    </div>

                    <!-- Enterprise & Portfolio Section -->
                    <div class="pt-6 space-y-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-teal-400">Enterprise &amp; Operations</div>
                        <div class="space-y-1.5 text-xs">
                            <a href="{{ route('crm.dashboard') }}" class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-800/60 text-slate-200 hover:text-white transition">
                                <span class="flex items-center gap-2.5">
                                    <span class="text-teal-400">💼</span>
                                    <span>Agent CRM &amp; Pipeline</span>
                                </span>
                                <span class="text-[10px] text-slate-500">M20</span>
                            </a>
                            <a href="{{ route('portfolio.dashboard') }}" class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-800/60 text-slate-200 hover:text-white transition">
                                <span class="flex items-center gap-2.5">
                                    <span class="text-teal-400">🏢</span>
                                    <span>Enterprise Portfolio Dashboard</span>
                                </span>
                                <span class="text-[10px] text-slate-500">M29</span>
                            </a>
                            <a href="{{ route('portfolio.matrix') }}" class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-800/60 text-slate-200 hover:text-white transition">
                                <span class="flex items-center gap-2.5">
                                    <span class="text-teal-400">🔲</span>
                                    <span>Unit Inventory Matrix</span>
                                </span>
                                <span class="text-[10px] text-slate-500">Live</span>
                            </a>
                            <a href="{{ route('maintenance.index') }}" class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-800/60 text-slate-200 hover:text-white transition">
                                <span class="flex items-center gap-2.5">
                                    <span class="text-teal-400">🔧</span>
                                    <span>Maintenance &amp; Work Orders</span>
                                </span>
                                <span class="text-[10px] text-slate-500">M28</span>
                            </a>
                            <a href="{{ route('invoices.index') }}" class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-800/60 text-slate-200 hover:text-white transition">
                                <span class="flex items-center gap-2.5">
                                    <span class="text-teal-400">🧾</span>
                                    <span>Invoicing, Tax &amp; ERCA Compliance</span>
                                </span>
                                <span class="text-[10px] text-slate-500">M30</span>
                            </a>
                            <a href="{{ route('leases.index') }}" class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-800/60 text-slate-200 hover:text-white transition">
                                <span class="flex items-center gap-2.5">
                                    <span class="text-teal-400">📜</span>
                                    <span>Lease Contracts &amp; Schedules</span>
                                </span>
                                <span class="text-[10px] text-slate-500">P4</span>
                            </a>
                        </div>
                    </div>

                    <!-- Administrative Intelligence & GIS Section (Milestone 1 Core) -->
                    <div class="pt-6 space-y-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-teal-400">National Administration (Modules 00–02)</div>
                        <div class="space-y-1.5 text-xs">
                            <a href="{{ route('admin.dashboard') }}" class="flex items-center justify-between p-2.5 rounded-xl bg-slate-800/40 hover:bg-slate-800 border border-slate-800 text-slate-200 hover:text-teal-300 transition">
                                <span class="flex items-center gap-2.5">
                                    <span>🏛️</span>
                                    <span class="font-semibold">National Admin Intelligence Center</span>
                                </span>
                                <span class="text-slate-500">→</span>
                            </a>
                            <a href="{{ route('admin.languages') }}" class="flex items-center justify-between p-2.5 rounded-xl bg-slate-800/40 hover:bg-slate-800 border border-slate-800 text-slate-200 hover:text-teal-300 transition">
                                <span class="flex items-center gap-2.5">
                                    <span>🌐</span>
                                    <span class="font-semibold">Database-Driven Language Catalog</span>
                                </span>
                                <span class="text-[10px] text-teal-400 font-mono">Module 00</span>
                            </a>
                            <a href="{{ route('admin.locations') }}" class="flex items-center justify-between p-2.5 rounded-xl bg-slate-800/40 hover:bg-slate-800 border border-slate-800 text-slate-200 hover:text-teal-300 transition">
                                <span class="flex items-center gap-2.5">
                                    <span>📍</span>
                                    <span class="font-semibold">Administrative Locations &amp; GIS</span>
                                </span>
                                <span class="text-[10px] text-teal-400 font-mono">Module 02</span>
                            </a>
                            <a href="{{ route('admin.security') }}" class="flex items-center justify-between p-2.5 rounded-xl bg-slate-800/40 hover:bg-slate-800 border border-slate-800 text-slate-200 hover:text-teal-300 transition">
                                <span class="flex items-center gap-2.5">
                                    <span>🛡️</span>
                                    <span class="font-semibold">Account Security &amp; Access Watchdog</span>
                                </span>
                                <span class="text-[10px] text-teal-400 font-mono">Module 01</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Drawer Footer: Quick Concierge & Localization -->
                <div class="p-6 border-t border-slate-800 bg-slate-950/60 space-y-4">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-400">Active Localization:</span>
                        <div class="flex items-center gap-1">
                            @foreach(['en' => 'EN', 'am' => 'አማ', 'om' => 'OM'] as $code => $lbl)
                                <a
                                    href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}"
                                    class="px-2 py-1 rounded-lg text-[11px] font-bold font-mono transition {{ app()->getLocale() === $code ? 'bg-teal-600 text-white' : 'bg-slate-800 text-slate-300 hover:text-white' }}"
                                >
                                    {{ $lbl }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <a
                        href="{{ url('/listings/create') }}"
                        class="w-full py-3 rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-semibold text-xs text-center block shadow-lg transition"
                    >
                        + Post Property or Listing
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-1">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <!-- Urbano Architectural Footer -->
    <footer class="bg-slate-900 text-white border-t border-slate-800 mt-16 text-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 pb-10 border-b border-slate-800">
                <!-- Col 1: Brand & National Context -->
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-teal-600 text-white font-bold">EP</span>
                        <span class="text-base font-extrabold tracking-tight">EthioProperty</span>
                    </div>
                    <p class="text-slate-400 leading-relaxed text-xs">
                        The National Ethiopian Real Estate Marketplace and Enterprise Property Management Platform. Built for certified brokerages, institutional owners, diaspora investors, and residents.
                    </p>
                    <div class="flex items-center gap-2 text-[11px] text-teal-400 font-mono">
                        <span class="w-2 h-2 rounded-full bg-teal-400"></span>
                        <span>PostGIS &amp; Ethiopian Geocoding Engine</span>
                    </div>
                </div>

                <!-- Col 2: Marketplace Discovery -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-200">Marketplace Discovery</h3>
                    <ul class="space-y-2 text-slate-400">
                        <li><a href="{{ url('/search?mode=BUY') }}" class="hover:text-teal-400 transition">Residential &amp; Commercial Sale</a></li>
                        <li><a href="{{ url('/search?mode=RENT') }}" class="hover:text-teal-400 transition">Long-Term Rentals</a></li>
                        <li><a href="{{ url('/search?mode=LEASE') }}" class="hover:text-teal-400 transition">Commercial &amp; Industrial Leases</a></li>
                        <li><a href="{{ url('/search?category=land') }}" class="hover:text-teal-400 transition">Agricultural &amp; Urban Land</a></li>
                        <li><a href="{{ route('stays.index') }}" class="hover:text-teal-400 transition">Luxury Short-Term Stays</a></li>
                    </ul>
                </div>

                <!-- Col 3: Operations & Portfolios -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-200">Enterprise Operations</h3>
                    <ul class="space-y-2 text-slate-400">
                        <li><a href="{{ route('portfolio.dashboard') }}" class="hover:text-teal-400 transition">Enterprise Portfolio Dashboard</a></li>
                        <li><a href="{{ route('portfolio.matrix') }}" class="hover:text-teal-400 transition">Unit Inventory Matrix</a></li>
                        <li><a href="{{ route('maintenance.index') }}" class="hover:text-teal-400 transition">Work Orders &amp; Maintenance</a></li>
                        <li><a href="{{ route('invoices.index') }}" class="hover:text-teal-400 transition">Tax Invoicing &amp; VAT/TOT Compliance</a></li>
                        <li><a href="{{ route('leases.index') }}" class="hover:text-teal-400 transition">Lease Contracts &amp; Rent Schedules</a></li>
                    </ul>
                </div>

                <!-- Col 4: National Admin & GIS -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-200">Administration &amp; Trust</h3>
                    <ul class="space-y-2 text-slate-400">
                        <li><a href="{{ route('admin.dashboard') }}" class="hover:text-teal-400 transition">National Admin Center</a></li>
                        <li><a href="{{ route('admin.languages') }}" class="hover:text-teal-400 transition">Language Management (Module 00)</a></li>
                        <li><a href="{{ route('admin.security') }}" class="hover:text-teal-400 transition">Security &amp; OTP Watchdog (Module 01)</a></li>
                        <li><a href="{{ route('admin.locations') }}" class="hover:text-teal-400 transition">8-Level Location Hierarchy (Module 02)</a></li>
                    </ul>
                </div>
            </div>

            <div class="pt-6 flex flex-wrap items-center justify-between gap-4 text-slate-400 text-[11px]">
                <div class="flex items-center gap-3">
                    <span>&copy; {{ date('Y') }} EthioProperty (ERE). All rights reserved.</span>
                    <span>•</span>
                    <span>Addis Ababa, Bole Sub-City, Ethiopia</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-1 rounded-full bg-slate-800 text-teal-400 border border-slate-700 font-mono">
                        📅 @ethDate(now())
                    </span>
                    <span class="px-2.5 py-1 rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                        ETB Currency Verified
                    </span>
                </div>
            </div>
        </div>
    </footer>

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
        (function() {
            function initPrefStore() {
                if (window.Alpine && !window.Alpine.store('preferences')) {
                    window.Alpine.store('preferences', {
                        calendar: localStorage.getItem('ep_calendar') || 'ethiopian',
                        toggleCalendar() {
                            this.calendar = this.calendar === 'ethiopian' ? 'gregorian' : 'ethiopian';
                            localStorage.setItem('ep_calendar', this.calendar);
                        }
                    });
                }
            }
            if (window.Alpine) {
                initPrefStore();
            } else {
                document.addEventListener('alpine:init', initPrefStore);
            }
        })();
    </script>
</body>
</html>
