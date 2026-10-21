# EthioProperty — Ethiopian Real Estate Marketplace Platform

## Milestone 1 Implementation: Modules 00, 01 & 02

### Architecture Overview
Domain-Driven Modular Monolith structured under `app/Domains/` and `app/Support/`:
- **Module 00: Foundation, Localization (en/am/om), Ethiopian Calendar & Immutable Audit Core**
  - Trilingual localization engine supporting English, Amharic (አማርኛ), and Afaan Oromo with session persistence (`SetLocaleMiddleware`).
  - Ethiopian Calendar Converter (`EthiopianCalendarConverter`, `EthiopianDate`) based on exact Julian Day Number (JDN) arithmetic for UTC Gregorian synchronization, Pagume leap year cycles, and Meskerem New Year.
  - Blade directives `@ethDate(...)` and `@etb(...)` for interactive dual-calendar rendering and ETB currency formatting.
  - SRS Section 36 Price per m² calculation and compact map pin badge formatter (`EtbFormatter`).
  - Immutable Audit Logging (`AuditLog`, `Auditable`) backed by a PostgreSQL database trigger strictly forbidding UPDATE or DELETE operations (SRS Section 142 & 189).
- **Module 01: Identity, +251 OTP Authentication, Granular RBAC & Multi-Role Profiles**
  - E.164 phone normalizer (`EthiopianPhoneNormalizer`) for Ethio Telecom (`09...`) and Safaricom Ethiopia (`07...`).
  - OTP Generation & Verification (`SendOtpAction`, `VerifyOtpAndAuthenticateAction`) with rate limiting and SHA-256 hash security.
  - Multi-Role Profiles & 13 Granular RBAC roles (`RbacRolePermissionSeeder`): 6 customer roles (Buyer, Tenant, Owner, Host, Agent, Developer) and 7 staff roles (Super Admin, Platform Admin, Property Admin, Verification Officer, Moderator, Finance, Support).
  - Phone privacy controls (Show Phone, Hide Phone, Messaging Only) and 6-tier Verification Hierarchy (Levels 0 to 5).
- **Module 02: 8-Level Ethiopian Location Hierarchy & PostGIS Engine**
  - 8-level hierarchy (`Location` model): Country &rarr; Region &rarr; Zone &rarr; City &rarr; Sub-city &rarr; Woreda &rarr; Kebele &rarr; Neighborhood.
  - Full seed data (`EthiopianLocationsSeeder`) covering Ethiopia root, Addis Ababa Region & City, all 11 Sub-cities, key neighborhoods, and major national cities (Harar, Jijiga, Dire Dawa, Hawassa, Bahir Dar, Adama, Mekelle).
  - Reverse geocoding engine (`ReverseGeocodePointAction`) utilizing PostGIS `ST_Covers(boundary, ...)` and `ST_DWithin(center_point, ..., 15000)` with fallback approximation.
  - Cascading Location Picker (`<x-location.cascading-picker />`) with GPS auto-detection.
  - Provider-agnostic mapping abstraction (`MapAdapter.js`) rendering Leaflet/OSM map with interactive draggable pin drops and compact ETB price markers.

### Running Migrations & Seeders
```bash
# Run migrations
php artisan migrate

# Seed RBAC roles & permissions
php artisan db:seed --class=Database\Seeders\RbacRolePermissionSeeder

# Seed Ethiopian 8-level administrative locations
php artisan db:seed --class=Database\Seeders\EthiopianLocationsSeeder

# Or fresh migration with all seeders
php artisan migrate:fresh --seed
```

### Running Automated Test Suite
```bash
php artisan test
```
All 22 unit & feature tests pass cleanly with 86 assertions covering calendar calculations, ETB formatters, phone normalizers, audit log immutability, OTP authentication, RBAC permissions, and location hierarchy API endpoints.

### Building Frontend Assets
```bash
npm run build
# Or start dev server
npm run dev
```
