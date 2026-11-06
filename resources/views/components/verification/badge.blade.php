@props(['level' => 0, 'badgeCode' => null])

@php
    $enumLevel = \App\Domains\Verification\Enums\VerificationLevel::tryFrom((int) $level)
        ?? \App\Domains\Verification\Enums\VerificationLevel::Unverified;

    $palette = match ($enumLevel->value) {
        5, 4 => 'bg-emerald-50 text-emerald-900 border-emerald-300',
        3 => 'bg-teal-50 text-teal-900 border-teal-300',
        2, 1 => 'bg-sky-50 text-sky-900 border-sky-300',
        default => 'bg-slate-100 text-slate-600 border-slate-200',
    };
@endphp

<div x-data="{ open: false }" @click.away="open = false" @keydown.escape.window="open = false" class="relative inline-block text-left">
    <button
        type="button"
        @click="open = !open"
        @mouseenter="open = true"
        :aria-expanded="open"
        aria-haspopup="dialog"
        aria-label="{{ $enumLevel->label(app()->getLocale()) }} details"
        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border transition cursor-pointer shadow-xs focus:outline-none focus:ring-2 focus:ring-teal-500 {{ $palette }}"
    >
        @if($enumLevel->value >= 1)
            <span class="text-emerald-600 font-bold">✓</span>
        @else
            <span class="text-slate-400 font-bold">○</span>
        @endif
        <span>{{ $enumLevel->label(app()->getLocale()) }}</span>
        <span class="text-[10px] opacity-70">ⓘ</span>
    </button>

    <!-- SRS Sections 22, 23 & 177: Mandatory Visible Explanation & Non-Government Registry Disclaimer -->
    <div
        x-show="open"
        x-cloak
        role="dialog"
        aria-label="Verification Level Explanation"
        class="absolute left-0 mt-2 w-80 z-50 rounded-2xl bg-slate-900 text-slate-100 p-4 shadow-xl border border-slate-700 text-xs leading-relaxed"
    >
        <div class="font-bold text-emerald-400 mb-1 flex items-center justify-between">
            <span>{{ $enumLevel->label(app()->getLocale()) }}</span>
            <button type="button" @click="open = false" class="text-slate-400 hover:text-white font-bold text-sm px-1" aria-label="Close">✕</button>
        </div>
        <p class="text-slate-200">
            {{ $enumLevel->visibleExplanation(app()->getLocale()) }}
        </p>
    </div>
</div>
