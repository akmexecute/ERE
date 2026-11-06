<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tour->title }} &middot; ERE 360° Virtual Tour</title>

    <!-- Tailwind / App CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Pannellum 360° Panorama Viewer CSS & JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css"/>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>

    <style>
        #panorama-container {
            width: 100vw;
            height: 100vh;
            background-color: #0b0f19;
        }
        .pnlm-load-button {
            background-color: #0d9488 !important;
        }
        .custom-hotspot-pin {
            width: 32px;
            height: 32px;
            background: rgba(13, 148, 136, 0.9);
            border: 2px solid white;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            transition: transform 0.2s;
        }
        .custom-hotspot-pin:hover {
            transform: scale(1.2);
            background: #d97706;
        }
    </style>
</head>
<body class="h-full bg-slate-950 text-white overflow-hidden relative" x-data="{ currentScene: '{{ $payload['default_scene_id'] ?? array_key_first($payload['scenes'] ?? []) }}', scenes: {{ \Illuminate\Support\Js::from($payload['scenes'] ?? []) }} }">

    <!-- Top Overlay Bar -->
    <div class="absolute top-4 left-4 right-4 z-20 flex items-center justify-between pointer-events-none">
        <div class="pointer-events-auto bg-slate-900/90 backdrop-blur border border-slate-700 px-4 py-2 rounded-2xl shadow-lg flex items-center gap-3">
            <a href="/" class="text-amber-500 font-extrabold text-sm tracking-tight hover:text-amber-400">
                EthioProperty
            </a>
            <span class="text-slate-600">|</span>
            <div>
                <h1 class="text-xs font-bold text-white">{{ $tour->title }}</h1>
                <div class="text-[10px] text-slate-400">Public 360° Virtual Walkthrough &middot; All Visitors Welcome</div>
            </div>
        </div>

        <!-- Room Scene Selector Dropdown -->
        @if(!empty($payload['scenes']))
            <div class="pointer-events-auto flex items-center gap-2">
                <select
                    x-model="currentScene"
                    @change="window.pannellumViewer && window.pannellumViewer.loadScene(currentScene)"
                    class="bg-slate-900/90 backdrop-blur border border-slate-700 text-white text-xs rounded-xl px-3 py-2 shadow-lg focus:ring-teal-500 focus:border-teal-500"
                >
                    <template x-for="(sceneData, sceneKey) in scenes" :key="sceneKey">
                        <option :value="sceneKey" x-text="sceneData.title"></option>
                    </template>
                </select>

                <button
                    type="button"
                    onclick="if (document.fullscreenElement) { document.exitFullscreen(); } else { document.documentElement.requestFullscreen(); }"
                    class="bg-slate-900/90 backdrop-blur border border-slate-700 hover:bg-slate-800 text-white text-xs px-3 py-2 rounded-xl shadow-lg transition"
                >
                    ⛶ Fullscreen
                </button>
            </div>
        @endif
    </div>

    <!-- 360° Panorama Stage Container -->
    <div id="panorama-container"></div>

    <!-- Bottom Attribution / Helper -->
    <div class="absolute bottom-4 left-4 right-4 z-20 flex items-center justify-between pointer-events-none">
        <div class="pointer-events-auto bg-slate-900/80 backdrop-blur border border-slate-800 text-slate-300 px-3 py-1.5 rounded-xl text-[11px] shadow">
            💡 Drag or swipe to rotate 360°. Click hotspots or select rooms to navigate.
        </div>
        <div class="pointer-events-auto">
            <a
                href="{{ route('listings.show', $tour->property?->listing?->slug ?? $tour->property_id) }}"
                class="bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold px-3.5 py-2 rounded-xl shadow-lg transition inline-flex items-center gap-1.5"
            >
                View Listing Details &rarr;
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const payload = @js($payload);
            const container = document.getElementById('panorama-container');

            if (!payload.scenes || Object.keys(payload.scenes).length === 0) {
                container.innerHTML = '<div class="flex items-center justify-center h-full text-slate-400 text-sm">No 360° scenes available yet for this tour.</div>';
                return;
            }

            if (typeof pannellum !== 'undefined') {
                try {
                    window.pannellumViewer = pannellum.viewer('panorama-container', {
                        default: {
                            firstScene: payload.default_scene_id || Object.keys(payload.scenes)[0],
                            author: "Ethiopian Real Estate Marketplace",
                            sceneFadeDuration: 1000,
                            autoLoad: true,
                            autoRotate: -2,
                            showZoomCtrl: true,
                            showFullscreenCtrl: false
                        },
                        scenes: payload.scenes
                    });
                } catch (e) {
                    console.error("Pannellum initialization fallback:", e);
                }
            } else {
                container.innerHTML = '<div class="flex items-center justify-center h-full text-slate-400 text-sm">360° Viewer requires WebGL. Please refresh with WebGL enabled.</div>';
            }
        });
    </script>
</body>
</html>

