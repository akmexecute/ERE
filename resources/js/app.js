import Alpine from 'alpinejs';
import L from 'leaflet';
import { MapAdapter } from './gis/MapAdapter.js';

window.Alpine = Alpine;
window.L = L;
window.MapAdapter = MapAdapter;

Alpine.start();
