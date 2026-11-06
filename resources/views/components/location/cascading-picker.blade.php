@props(['initialRegionId' => null, 'initialCityId' => null, 'initialSubcityId' => null, 'initialNeighborhoodId' => null, 'includeCoordinates' => true])

<div
    x-data="{
        regions: [],
        cities: [],
        subcities: [],
        neighborhoods: [],
        regionId: @js($initialRegionId),
        cityId: @js($initialCityId),
        subcityId: @js($initialSubcityId),
        neighborhoodId: @js($initialNeighborhoodId),
        latitude: 9.0192,
        longitude: 38.7525,
        detectingGps: false,
        gpsMessage: '',

        async init() {
            this.regions = await this.fetchChildren('root');
            if (this.regionId) await this.onRegionChange(false);
        },
        notifyHierarchyChange() {
            window.dispatchEvent(new CustomEvent('location-hierarchy-changed', {
                detail: {
                    regionId: this.regionId,
                    cityId: this.cityId,
                    subcityId: this.subcityId,
                    neighborhoodId: this.neighborhoodId,
                }
            }));
        },
        async fetchChildren(parentId) {
            if (!parentId) return [];
            try {
                const queryParam = parentId === 'root' ? 'root=1' : `parent_id=${parentId}`;
                const res = await fetch(`/api/v1/locations/children?${queryParam}`);
                const json = await res.json();
                return json.data || [];
            } catch (e) {
                console.error('Failed to fetch location children:', e);
                return [];
            }
        },
        async onRegionChange(resetChildren = true) {
            if (resetChildren) { this.cityId = ''; this.subcityId = ''; this.neighborhoodId = ''; this.subcities = []; this.neighborhoods = []; }
            this.cities = await this.fetchChildren(this.regionId);
            this.updateCoordinatesFromSelection(this.regions, this.regionId);
            this.notifyHierarchyChange();
        },
        async onCityChange(resetChildren = true) {
            if (resetChildren) { this.subcityId = ''; this.neighborhoodId = ''; this.neighborhoods = []; }
            this.subcities = await this.fetchChildren(this.cityId);
            this.updateCoordinatesFromSelection(this.cities, this.cityId);
            this.notifyHierarchyChange();
        },
        async onSubcityChange(resetChildren = true) {
            if (resetChildren) { this.neighborhoodId = ''; }
            this.neighborhoods = await this.fetchChildren(this.subcityId);
            this.updateCoordinatesFromSelection(this.subcities, this.subcityId);
            this.notifyHierarchyChange();
        },
        onNeighborhoodChange() {
            this.updateCoordinatesFromSelection(this.neighborhoods, this.neighborhoodId);
            this.notifyHierarchyChange();
        },
        updateCoordinatesFromSelection(list, id) {
            const item = list.find(x => String(x.id) === String(id));
            if (item && item.latitude && item.longitude) {
                this.latitude = item.latitude;
                this.longitude = item.longitude;
                window.dispatchEvent(new CustomEvent('location-selected', { detail: { lat: this.latitude, lng: this.longitude, name: item.name } }));
            }
        },
        detectMyGps() {
            if (!navigator.geolocation) {
                this.gpsMessage = 'Geolocation is not supported by your browser.';
                return;
            }
            this.detectingGps = true;
            this.gpsMessage = '';
            navigator.geolocation.getCurrentPosition(async (pos) => {
                this.latitude = pos.coords.latitude;
                this.longitude = pos.coords.longitude;
                try {
                    const res = await fetch(`/api/v1/gis/reverse-geocode?lat=${this.latitude}&lng=${this.longitude}`);
                    const json = await res.json();
                    const chain = json.data || {};
                    if (chain.region) {
                        this.regionId = chain.region.id;
                        await this.onRegionChange(false);
                    }
                    if (chain.city) {
                        this.cityId = chain.city.id;
                        await this.onCityChange(false);
                    }
                    if (chain.subcity) {
                        this.subcityId = chain.subcity.id;
                        await this.onSubcityChange(false);
                    }
                    if (chain.neighborhood) {
                        this.neighborhoodId = chain.neighborhood.id;
                    }
                    this.gpsMessage = 'Location detected via PostGIS!';
                    window.dispatchEvent(new CustomEvent('location-selected', { detail: { lat: this.latitude, lng: this.longitude } }));
                } catch (e) {
                    this.gpsMessage = 'Reverse geocoding failed.';
                } finally {
                    this.detectingGps = false;
                }
            }, (err) => {
                this.detectingGps = false;
                this.gpsMessage = 'Could not access GPS coordinates.';
            });
        }
    }"
    class="space-y-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-xs"
>
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">Property Administrative Location</h3>
            <p class="text-xs text-slate-500">8-Level Ethiopian Administrative Hierarchy (SRS Section 11, 12, 176)</p>
        </div>
        <button
            type="button"
            @click="detectMyGps()"
            :disabled="detectingGps"
            class="inline-flex items-center gap-1.5 text-xs font-semibold text-teal-700 hover:text-teal-800 bg-teal-50 hover:bg-teal-100 px-3 py-1.5 rounded-lg transition"
        >
            <span>📍</span>
            <span x-text="detectingGps ? 'Detecting GPS...' : 'Auto-Detect via GPS'"></span>
        </button>
    </div>

    <div x-show="gpsMessage" class="text-xs text-teal-800 bg-teal-50/60 px-3 py-1.5 rounded-lg border border-teal-100" x-text="gpsMessage"></div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- Region -->
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Region / Chartered City</label>
            <select name="region_id" x-model="regionId" @change="onRegionChange()" class="w-full rounded-xl border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600">
                <option value="">Select Region...</option>
                <template x-for="r in regions" :key="r.id">
                    <option :value="r.id" x-text="r.name"></option>
                </template>
            </select>
        </div>

        <!-- City -->
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">City / Town</label>
            <select name="city_id" x-model="cityId" @change="onCityChange()" :disabled="!cities.length" class="w-full rounded-xl border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600 disabled:bg-slate-100 disabled:text-slate-400">
                <option value="">Select City...</option>
                <template x-for="c in cities" :key="c.id">
                    <option :value="c.id" x-text="c.name"></option>
                </template>
            </select>
        </div>

        <!-- Sub-city / Woreda -->
        <div x-show="subcities.length > 0">
            <label class="block text-xs font-medium text-slate-600 mb-1">Sub-City / Woreda</label>
            <select name="subcity_id" x-model="subcityId" @change="onSubcityChange()" class="w-full rounded-xl border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600">
                <option value="">Select Sub-City...</option>
                <template x-for="sc in subcities" :key="sc.id">
                    <option :value="sc.id" x-text="sc.name"></option>
                </template>
            </select>
        </div>

        <!-- Neighborhood / Kebele -->
        <div x-show="neighborhoods.length > 0">
            <label class="block text-xs font-medium text-slate-600 mb-1">Neighborhood / Area</label>
            <select name="neighborhood_id" x-model="neighborhoodId" @change="onNeighborhoodChange()" class="w-full rounded-xl border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600">
                <option value="">Select Neighborhood...</option>
                <template x-for="nb in neighborhoods" :key="nb.id">
                    <option :value="nb.id" x-text="nb.name"></option>
                </template>
            </select>
        </div>
    </div>

    @if($includeCoordinates)
    <input type="hidden" name="latitude" :value="latitude">
    <input type="hidden" name="longitude" :value="longitude">
    @endif
</div>
