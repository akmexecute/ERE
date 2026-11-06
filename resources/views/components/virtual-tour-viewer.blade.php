@props(['tour'])

@if($tour && $tour->is_published)
    <div class="rounded-2xl border border-slate-200 overflow-hidden bg-slate-900 shadow-md">
        <div class="px-5 py-3 bg-slate-800 text-white flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-amber-400 text-base">🌐</span>
                <span class="font-bold text-xs">360° Interactive Virtual Tour</span>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-teal-800 text-teal-100 font-mono">Published</span>
            </div>

            <a
                href="{{ route('virtual-tours.show', $tour->uuid) }}"
                target="_blank"
                class="px-3 py-1 rounded-lg bg-teal-700 hover:bg-teal-600 text-white text-xs font-semibold transition flex items-center gap-1"
            >
                <span>Full Screen &rarr;</span>
            </a>
        </div>

        <div class="relative h-80 sm:h-96 w-full bg-slate-950">
            <iframe
                src="{{ route('virtual-tours.show', $tour->uuid) }}"
                class="w-full h-full border-0"
                allow="fullscreen; accelerometer; gyroscope; magnetometer"
                loading="lazy"
            ></iframe>
        </div>
    </div>
@endif

