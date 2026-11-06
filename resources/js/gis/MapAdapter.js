/**
 * Provider-Agnostic Map Adapter for Ethiopian Real Estate Platform (SRS Section 149 & Milestone 1).
 * Supports OpenStreetMap (Leaflet) by default with Road/Satellite layer toggle and rich popup discovery cards.
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

        // Base tile layers
        this.baseLayers = {};
        this.currentLayerType = 'road'; // 'road' or 'satellite'
        const globalConfig = typeof window !== 'undefined' && window.MAP_CONFIG ? window.MAP_CONFIG : {};
        this.satelliteUrl = options.satelliteUrl || globalConfig.satelliteUrl || 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
        this.satelliteAttribution = options.satelliteAttribution || globalConfig.satelliteAttribution || '&copy; Esri, Maxar, Earthstar Geographics';
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

        // Initialize Leaflet Map
        this.map = L.map(this.containerId, {
            center: this.center,
            zoom: this.zoom,
            zoomControl: false, // Customized control positioning
        });

        // Add standard zoom control at top-right
        L.control.zoom({ position: 'topright' }).addTo(this.map);

        // 1. Road Layer (OpenStreetMap - Free, No Credentials Required)
        this.baseLayers.road = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        });

        // 2. Satellite Layer (Permitted Imagery Provider)
        this.baseLayers.satellite = L.tileLayer(this.satelliteUrl, {
            maxZoom: 19,
            attribution: this.satelliteAttribution
        });

        // Add default road layer to map
        this.baseLayers.road.addTo(this.map);

        // Add Road / Satellite Layer Toggle Control (Urbano Minimalist Architectural Pill)
        this.addLayerToggleControl(L);

        // Debounced viewport boundary change listener for map-based property search
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

    addLayerToggleControl(L) {
        const self = this;
        const toggleControl = L.control({ position: 'topleft' });

        toggleControl.onAdd = function () {
            const div = L.DomUtil.create('div', 'leaflet-bar ep-map-layer-control');
            div.style.background = 'white';
            div.style.borderRadius = '12px';
            div.style.boxShadow = '0 4px 12px rgba(0,0,0,0.12)';
            div.style.padding = '3px';
            div.style.display = 'flex';
            div.style.gap = '2px';
            div.style.border = '1px solid rgba(226,232,240,0.9)';

            div.innerHTML = `
                <button type="button" id="ep-btn-road" style="padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 8px; border: none; cursor: pointer; background: #0f172a; color: #ffffff; transition: all 0.15s ease;">
                    Road Map
                </button>
                <button type="button" id="ep-btn-satellite" style="padding: 4px 10px; font-size: 11px; font-weight: 600; border-radius: 8px; border: none; cursor: pointer; background: transparent; color: #475569; transition: all 0.15s ease;">
                    Satellite
                </button>
            `;

            L.DomEvent.disableClickPropagation(div);

            const roadBtn = div.querySelector('#ep-btn-road');
            const satBtn = div.querySelector('#ep-btn-satellite');

            roadBtn.addEventListener('click', () => {
                self.switchLayer('road');
                roadBtn.style.background = '#0f172a';
                roadBtn.style.color = '#ffffff';
                roadBtn.style.fontWeight = '700';
                satBtn.style.background = 'transparent';
                satBtn.style.color = '#475569';
                satBtn.style.fontWeight = '600';
            });

            satBtn.addEventListener('click', () => {
                self.switchLayer('satellite');
                satBtn.style.background = '#0f172a';
                satBtn.style.color = '#ffffff';
                satBtn.style.fontWeight = '700';
                roadBtn.style.background = 'transparent';
                roadBtn.style.color = '#475569';
                roadBtn.style.fontWeight = '600';
            });

            return div;
        };

        toggleControl.addTo(this.map);
    }

    switchLayer(layerType) {
        if (!this.map || this.currentLayerType === layerType) return;

        const oldLayer = this.baseLayers[this.currentLayerType];
        const newLayer = this.baseLayers[layerType];

        if (oldLayer && this.map.hasLayer(oldLayer)) {
            this.map.removeLayer(oldLayer);
        }

        if (newLayer) {
            newLayer.addTo(this.map);
            newLayer.bringToBack();
            this.currentLayerType = layerType;
        }
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
                html: `<div class="px-2.5 py-1 rounded-full bg-slate-900 text-white text-xs font-bold shadow-md border border-slate-700 whitespace-nowrap hover:bg-teal-700 hover:scale-105 transition cursor-pointer">${item.compact_price}</div>`
            });

            const marker = L.marker([item.lat, item.lng], { icon }).addTo(this.map);

            // Rich Urbano Discovery Card Popup
            const imageHtml = item.image_url
                ? `<div style="height: 110px; width: 100%; border-radius: 12px; overflow: hidden; margin-bottom: 8px;"><img src="${item.image_url}" style="width: 100%; height: 100%; object-fit: cover;" alt="${item.title || 'Property'}"></div>`
                : '';

            const popupContent = `
                <div style="font-family: 'Inter', system-ui, sans-serif; font-size: 12px; line-height: 1.4; max-width: 240px; padding: 2px;">
                    ${imageHtml}
                    <div style="font-size: 10px; text-transform: uppercase; font-weight: 700; color: #0d9488; letter-spacing: 0.5px;">${item.subcity || 'Addis Ababa'}</div>
                    <div style="font-size: 13px; font-weight: 800; color: #0f172a; margin-top: 2px; margin-bottom: 4px;">${item.title || 'Property Listing'}</div>
                    <div style="font-size: 14px; font-weight: 800; color: #0f766e;">${item.price_formatted || item.compact_price}</div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 2px;">${item.bedrooms || 0} Beds • ${item.bathrooms || 0} Baths • ${item.area_sqm || 0} m²</div>
                    <div style="margin-top: 8px; padding-top: 6px; border-top: 1px solid #f1f5f9;">
                        <a href="${item.url || '#'}" style="display: inline-block; width: 100%; text-align: center; background: #0f172a; color: white; padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; text-decoration: none;">View Details &rarr;</a>
                    </div>
                </div>
            `;

            marker.bindPopup(popupContent, { maxWidth: 280, minWidth: 220, className: 'ep-urbano-map-popup' });
            this.markers.set(item.id, marker);
        });
    }
}
