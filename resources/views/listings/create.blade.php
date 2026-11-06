<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
        <div>
            <a href="{{ url('/dashboard') }}" class="text-xs font-semibold text-teal-700 hover:underline">{{ __('listings.back_to_dashboard') }}</a>
            <h1 class="text-2xl font-bold text-slate-900 mt-2">{{ __('listings.post_title') }}</h1>
            <p class="text-sm text-slate-600">{{ __('listings.post_subtitle') }}</p>
        </div>

        <form
            x-data="{
                priceEtb: 25000000,
                areaSqm: 250,
                lat: 9.0192,
                lng: 38.7525,
                calculatePricePerSqm() {
                    if (this.areaSqm > 0) {
                        return Math.round(this.priceEtb / this.areaSqm).toLocaleString();
                    }
                    return '0';
                }
            }"
            class="space-y-6"
        >
            <!-- Basic Details -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
                <h3 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">{{ __('listings.overview') }}</h3>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('listings.title_input') }}</label>
                    <input type="text" placeholder="{{ __('listings.title_placeholder') }}" class="w-full text-sm rounded-xl border border-slate-300 px-3.5 py-2.5 focus:border-teal-600 focus:ring-teal-600">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('listings.price_input') }}</label>
                        <input type="number" x-model.number="priceEtb" class="w-full text-sm rounded-xl border border-slate-300 px-3.5 py-2.5 focus:border-teal-600 focus:ring-teal-600">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('listings.area_input') }}</label>
                        <input type="number" x-model.number="areaSqm" class="w-full text-sm rounded-xl border border-slate-300 px-3.5 py-2.5 focus:border-teal-600 focus:ring-teal-600">
                    </div>
                </div>

                <!-- Calculated Rate Display -->
                <div class="p-3 bg-teal-50 border border-teal-100 rounded-xl flex items-center justify-between text-xs text-teal-900">
                    <span>{{ __('listings.calculated_rate') }}</span>
                    <strong>ETB <span x-text="calculatePricePerSqm()"></span> / m²</strong>
                </div>
            </div>

            <!-- Cascading Location Picker (Module 02) -->
            <x-location.cascading-picker />

            <!-- Interactive Pin Drop Map -->
            <div
                x-data="{
                    adapter: null,
                    initCreateMap() {
                        if (!window.L || !window.MapAdapter) return;
                        this.adapter = new window.MapAdapter('create-map', {
                            center: [9.0192, 38.7525],
                            zoom: 13,
                            onPinMoved: (pos) => {
                                lat = pos.lat;
                                lng = pos.lng;
                            }
                        });
                        this.adapter.init(window.L);
                        this.adapter.enablePinDrop(window.L, 9.0192, 38.7525);
                    }
                }"
                x-init="
                    $nextTick(() => {
                        const checkL = setInterval(() => {
                            if (window.L && window.MapAdapter) {
                                clearInterval(checkL);
                                initCreateMap();
                            }
                        }, 100);
                    });
                    window.addEventListener('location-selected', (e) => {
                        if (adapter && e.detail.lat && e.detail.lng) {
                            adapter.setCenter(e.detail.lat, e.detail.lng, 15);
                        }
                    });
                "
                class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs"
            >
                <div class="p-4 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">{{ __('listings.pin_drop_title') }}</h3>
                        <p class="text-xs text-slate-500">Drag marker to exact compound entrance or plot boundary</p>
                    </div>
                    <span class="text-xs text-teal-700 font-mono">EPSG:4326</span>
                </div>
                <div id="create-map" class="w-full h-80 z-10"></div>
            </div>

            <button
                type="button"
                @click="window.dispatchEvent(new CustomEvent('notify', { detail: { message: 'Property draft saved successfully!' } }))"
                class="w-full py-3 px-4 rounded-xl bg-teal-700 hover:bg-teal-800 text-white font-semibold text-sm shadow-sm transition"
            >
                {{ __('listings.publish_btn') }}
            </button>
        </form>
    </div>
</x-app-layout>
