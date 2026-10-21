/**
 * Provider-Agnostic Map Adapter for Ethiopian Real Estate Platform (SRS Section 149).
 * Supports OpenStreetMap (Leaflet) by default with pluggable Mapbox GL / Google Maps drivers.
 */
export class MapAdapter {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.provider = options.provider || 'osm';
        this.center = options.center || [9.0192, 38.7525]; // Addis Ababa default
        this.zoom = options.zoom || 13;
        this.onPinMoved = options.onPinMoved || (() => {});
        this.onBoundsChanged = options.onBoundsChanged || (() => {});
        this.map = null;
        this.activePin = null;
        this.markers = new Map();
    }

    init(L) {
        if (!L) {
            console.error('Leaflet library is required to initialize MapAdapter');
            return this;
        }

        const container = document.getElementById(this.containerId);
        if (!container) {
            return this;
        }

        this.map = L.map(this.containerId).setView(this.center, this.zoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(this.map);

        let debounceTimer = null;
        this.map.on('moveend', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const bounds = this.map.getBounds();
                this.onBoundsChanged({
                    ne_lat: bounds.getNorthEast().lat,
                    ne_lng: bounds.getNorthEast().lng,
                    sw_lat: bounds.getSouthWest().lat,
                    sw_lng: bounds.getSouthWest().lng,
                });
            }, 350);
        });

        return this;
    }

    enablePinDrop(L, initialLat = 9.0192, initialLng = 38.7525) {
        if (!this.map || !L) return;

        this.activePin = L.marker([initialLat, initialLng], { draggable: true }).addTo(this.map);

        this.activePin.on('dragend', (event) => {
            const pos = event.target.getLatLng();
            this.onPinMoved({ lat: pos.lat, lng: pos.lng });
        });

        this.map.on('click', (event) => {
            this.activePin.setLatLng(event.latlng);
            this.onPinMoved({ lat: event.latlng.lat, lng: event.latlng.lng });
        });
    }

    setCenter(lat, lng, zoom = null) {
        if (this.map) {
            this.map.setView([lat, lng], zoom || this.map.getZoom());
            if (this.activePin) {
                this.activePin.setLatLng([lat, lng]);
            }
        }
    }

    renderPriceMarkers(L, listings = []) {
        if (!this.map || !L) return;

        this.markers.forEach((marker) => this.map.removeLayer(marker));
        this.markers.clear();

        listings.forEach((item) => {
            const icon = L.divIcon({
                className: 'ep-price-pin-wrapper',
                html: `<div class="px-2.5 py-1 rounded-full bg-teal-800 text-white text-xs font-bold shadow-md border border-white whitespace-nowrap hover:scale-110 transition cursor-pointer">${item.compact_price}</div>`
            });
            const marker = L.marker([item.lat, item.lng], { icon }).addTo(this.map);
            if (item.title) {
                marker.bindPopup(`<strong>${item.title}</strong><br>${item.full_price}`);
            }
            this.markers.set(item.id, marker);
        });
    }
}
