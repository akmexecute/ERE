import Alpine from 'alpinejs';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { MapAdapter } from './gis/MapAdapter.js';

window.Alpine = Alpine;
window.L = L;
window.MapAdapter = MapAdapter;

Alpine.store('preferences', {
    calendar: localStorage.getItem('ep_calendar') || 'ethiopian',
    toggleCalendar() {
        this.calendar = this.calendar === 'ethiopian' ? 'gregorian' : 'ethiopian';
        localStorage.setItem('ep_calendar', this.calendar);
    }
});

Alpine.start();
