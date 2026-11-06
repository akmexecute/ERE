# Comprehensive Role-Based Access Control (RBAC) System Audit
**Project:** Ethiopian Real Estate Marketplace (ERE)  
**Date of Audit:** November 6, 2026  
**Auditor Mode:** Strict Read-Only Inspection & Security Assessment  
**Environment:** PHP 8.4.25 | Laravel 13.33.0 | SQLite Local Engine (Production PostgreSQL Target)

---

## 1. Executive Context & Repository Inventory

This audit provides a repository-wide inspection of the authentication, authorization, role-based access control (RBAC), multi-tenant company isolation, resource ownership, and security auditing mechanisms in the Ethiopian Real Estate Marketplace (ERE).

### 1.1 Repository State & Environment
- **Current Git Branch:** `main` (ahead of `origin/main` by 3 commits)
- **Framework & Runtime:** Laravel Framework `13.33.0`, PHP `8.4.25`
- **Application Architectural Pattern:** Domain-Driven Design (DDD) layered under `app/Domains/` (`User`, `Listing`, `Property`, `Company`, `Agency`, `Developer`, `Stay`, `Portfolio`, `Payment`, `Financial`, `Invoice`, `Maintenance`, `Identity`, `Verification`, `Moderation`, `Subscription`, `Advertising`, `GIS`, `Localization`, `Administration`).
- **Authorization Infrastructure:** Custom database-backed RBAC (`roles`, `permissions`, `role_permissions`, `user_roles`, `user_permissions`). No third-party packages (such as `spatie/laravel-permission`) are installed.
- **Laravel Policy Classes (`app/Policies`):** **Zero policy classes exist.** The `app/Policies` directory is absent.
- **Laravel Gates:** **Zero Gates defined.** No `Gate::define` or `Gate::authorize` calls exist.
- **Route Authorization Middleware:** No custom `role` or `permission` route middleware exists in `app/Http/Middleware` or `bootstrap/app.php`. Most routes rely solely on `middleware('auth')`.

---

## 2. Inventory of Existing Roles

The ERE application defines **13 distinct roles** in the `roles` table (`database/migrations/2026_01_01_000002_create_identity_and_rbac_tables.php` and `database/seeders/RbacRolePermissionSeeder.php`).

### 2.1 Complete Role Inventory Table

| Role Slug | Display Name (EN / AM / OM) | Category | Is Staff | Database Existence | Users Count | Mapped Perms | Status |
|---|---|---|---|---|---|---|---|
| `buyer` | Buyer / ገዢ / Bitaa | Marketplace | No (`false`) | Verified | 6 | 4 | Active |
| `tenant` | Tenant / ተከራይ / Kireeffataa | Marketplace | No (`false`) | Verified | 2 | 7 | Active |
| `property_owner` | Property Owner / የንብረት ባለቤት / Abbaa Qabeenyaa | Marketplace | No (`false`) | Verified | 2 | 11 | Active |
| `host` | Short-Stay Host / አስተናጋጅ / Keessummeessaa | Marketplace | No (`false`) | Verified | 2 | 9 | Active |
| `agent` | Real Estate Agent / ወኪል / ደላላ / Bakka Buʼaa | Marketplace | No (`false`) | Verified | 1 | 9 | Active |
| `developer` | Real Estate Developer / አልሚ / Misoomsaa | Marketplace | No (`false`) | Verified | 0 | 6 | Active |
| `super_admin` | Super Admin / ዋና አስተዳዳሪ / Bulchaa Ol-aanaa | Platform Staff | Yes (`true`) | Verified | 1 | 0* | Active |
| `platform_admin` | Platform Admin / የፕላትፎርም አስተዳዳሪ / Bulchaa Waltajjii | Platform Staff | Yes (`true`) | Verified | 0 | 50 | Active |
| `property_admin` | Property Admin / የንብረት አስተዳዳሪ / Bulchaa Qabeenyaa | Operations Staff | Yes (`true`) | Verified | 0 | 20 | Active |
| `verification_officer` | Verification Officer / የማረጋገጫ ባለሙያ / Ogeessa Mirkaneeffannaa | Operations Staff | Yes (`true`) | Verified | 0 | 4 | Active |
| `moderator` | Listing Moderator / ተቆጣጣሪ / Toʼataa | Operations Staff | Yes (`true`) | Verified | 0 | 2 | Active |
| `finance_officer` | Finance Officer / የፋይናንስ ባለሙያ / Ogeessa Faayinaansii | Operations Staff | Yes (`true`) | Verified | 0 | 10 | Active |
| `customer_support` | Customer Support / የደንበኞች ድጋፍ / Deeggarsa Maamilaa | Operations Staff | Yes (`true`) | Verified | 0 | 6 | Active |

*\*Note on `super_admin`:* `super_admin` has 0 rows in `role_permissions` because `User::hasPermission()` hardcodes an absolute bypass:
```php
// app/Domains/User/Models/User.php:112-115
if ($this->hasRole('super_admin')) {
    return true;
}
```

### 2.2 Ghost / Phantom Roles Found in Application Code
During the audit, string literals representing roles were found in authorization checks that **do not exist** in the `roles` table:
- `'admin'` — checked in `AdminDashboardController.php:29`, `ModerateListingAction.php:231`, `BuyerContractController.php:115`, `ReviewVerificationRequestAction.php:77`, `GenerateDocumentSignedUrlAction.php:85`.
- `'system_admin'` — checked in `ModerateListingAction.php:231`, `ReviewVerificationRequestAction.php:77`, `GenerateDocumentSignedUrlAction.php:85`.

*Security Implication:* Code referencing `'admin'` or `'system_admin'` will never match database records, creating dead code branches or confusion regarding which role is intended.

---

## 3. Inventory of Existing Permissions

There are **51 distinct permissions** in the `permissions` table across 13 modules.

### 3.1 Complete Permission Catalogue

| ID | Module | Permission Slug | Roles Assigned | Direct Users | Purpose & Scope |
|---|---|---|---|---|---|
| 1 | `property` | `property.view` | 11 | 0 | View property details and marketplace listings |
| 2 | `property` | `property.create` | 6 | 0 | Draft or publish physical property assets and listings |
| 3 | `property` | `property.update` | 6 | 0 | Modify property data, photos, and descriptions |
| 4 | `property` | `property.delete` | 2 | 0 | Delete property listings (*No controller implementation*) |
| 5 | `property` | `property.approve` | 2 | 0 | Approve listings in moderation workbench |
| 6 | `property` | `property.verify` | 3 | 0 | Mark listing or property physical verification badge |
| 7 | `verification` | `verification.review` | 2 | 0 | Inspect submitted identity, Fayda, or company documents |
| 8 | `verification` | `verification.approve` | 2 | 0 | Approve identity verification level (Level 1..5) |
| 9 | `verification` | `verification.reject` | 2 | 0 | Reject identity verification request with reason |
| 10 | `booking` | `booking.view` | 8 | 0 | View stay reservations and calendar bookings |
| 11 | `booking` | `booking.create` | 4 | 0 | Create booking reservations for stays |
| 12 | `booking` | `booking.cancel` | 5 | 0 | Cancel stay bookings |
| 13 | `booking` | `booking.refund` | 2 | 0 | Calculate and authorize stay cancellation refund |
| 14 | `payment` | `payment.view` | 3 | 0 | Inspect payment records and receipts |
| 15 | `payment` | `payment.create` | 2 | 0 | Initiate checkout and payment intents |
| 16 | `payment` | `payment.refund` | 2 | 0 | Authorize payment refund on unapplied ledger accounts |
| 17 | `payment` | `payment.reconcile` | 2 | 0 | Access financial reconciliation workbench |
| 18 | `maintenance` | `maintenance.create` | 6 | 0 | Submit property maintenance service request |
| 19 | `maintenance` | `maintenance.view` | 7 | 0 | View maintenance requests and status updates |
| 20 | `maintenance` | `maintenance.assign` | 2 | 0 | Assign maintenance contractor or vendor |
| 21 | `maintenance` | `maintenance.update_status` | 3 | 0 | Update maintenance progress stage |
| 22 | `maintenance` | `maintenance.manage_costs` | 2 | 0 | Record maintenance contractor invoices and material costs |
| 23 | `maintenance` | `maintenance.close` | 2 | 0 | Close completed work orders |
| 24 | `portfolio` | `portfolio.view` | 3 | 0 | View enterprise portfolio multi-property dashboard |
| 25 | `mandate` | `mandate.create` | 2 | 0 | Create property management representation mandate |
| 26 | `mandate` | `mandate.view` | 4 | 0 | View active and archived management mandates |
| 27 | `mandate` | `mandate.manage` | 2 | 0 | Update, terminate, or enforce management mandates |
| 28 | `tax` | `tax.manage` | 2 | 0 | Configure VAT, TOT, and withholding tax rules |
| 29 | `invoice` | `invoice.create` | 3 | 0 | Issue sequential ETB tax invoices |
| 30 | `invoice` | `invoice.view` | 5 | 0 | View and print generated invoices |
| 31 | `invoice` | `invoice.void` | 2 | 0 | Void invoices with mandatory audit justification |
| 32 | `localization` | `language.view` | 1 | 0 | View translation catalog entries |
| 33 | `localization` | `language.manage` | 1 | 0 | Edit and update translations in database/files |
| 34 | `localization` | `language.export` | 1 | 0 | Export translation dictionaries to Excel |
| 35 | `localization` | `language.import` | 1 | 0 | Import translations from Excel spreadsheet |
| 36 | `security` | `security.view` | 1 | 0 | View authentication watchdog and login histories |
| 37 | `security` | `security.manage` | 1 | 0 | Suspend, ban, or activate user accounts |
| 38 | `location` | `location.view` | 2 | 0 | View administrative regions, cities, and subcities |
| 39 | `location` | `location.manage` | 2 | 0 | Create and edit administrative hierarchy locations |
| 40 | `location` | `location.deactivate` | 1 | 0 | Deactivate administrative locations |
| 41 | `location` | `location.import` | 1 | 0 | Import Ethiopian geospatial hierarchy data |
| 42 | `subscription` | `subscription.view` | 1 | 0 | View platform subscription plans and features |
| 43 | `subscription` | `subscription.manage` | 1 | 0 | Configure subscription tiers, quotas, and pricing |
| 44 | `promotion` | `promotion.manage` | 1 | 0 | Manage promoted listing packages and durations |
| 45 | `advertising` | `advertising.manage` | 1 | 0 | Configure ad placements, pricing, and banners |
| 46 | `advertising` | `advertising.approve` | 1 | 0 | Approve or reject submitted ad campaigns |
| 47 | `virtual_tour` | `virtual_tour.create` | 5 | 0 | Upload 360° panoramas and build scenes |
| 48 | `virtual_tour` | `virtual_tour.manage` | 5 | 0 | Configure tour hotspots, scenes, and publish |
| 49 | `gis` | `gis.view_advanced` | 1 | 0 | Access PostGIS valuation and rental heatmaps |
| 50 | `moderation` | `listings.moderate.override_subscription_quota` | 1 | 0 | Administratively bypass seller quota on listing approval |
| 51 | `subscription` | `subscriptions.entitlements.bypass` | 0 | 0 | **Unused/Unassigned:** Seeded in `permissions`, assigned to 0 roles |

---

## 4. The Actual Role-Permission Matrix

The following matrix documents the exact permissions assigned to each role based on database inspection of `role_permissions` join `roles` and `permissions`.

| Permission Slug | Super Admin | Platform Admin | Property Admin | Moderator | Verification Officer | Finance Officer | Customer Support | Property Owner | Short-Stay Host | Agent | Developer | Buyer | Tenant |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `property.view` | Allowed* | Allowed | Allowed | Allowed | Allowed | Denied | Allowed | Allowed | Allowed | Allowed | Allowed | Allowed | Allowed |
| `property.create` | Allowed* | Allowed | Allowed | Denied | Denied | Denied | Denied | Allowed | Allowed | Allowed | Allowed | Denied | Denied |
| `property.update` | Allowed* | Allowed | Allowed | Denied | Denied | Denied | Denied | Allowed | Allowed | Allowed | Allowed | Denied | Denied |
| `property.delete` | Allowed* | Allowed | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `property.approve` | Allowed* | Allowed | Allowed | Denied** | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `property.verify` | Allowed* | Allowed | Allowed | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `verification.review` | Allowed* | Allowed | Denied | Denied | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `verification.approve` | Allowed* | Allowed | Denied | Denied | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `verification.reject` | Allowed* | Allowed | Denied | Denied | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `booking.view` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Allowed | Allowed | Allowed | Allowed | Allowed | Allowed | Allowed |
| `booking.create` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Allowed | Denied | Allowed | Allowed |
| `booking.cancel` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Allowed | Denied | Allowed | Denied | Denied | Allowed | Allowed |
| `booking.refund` | Allowed* | Allowed | Denied | Denied | Denied | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `payment.view` | Allowed* | Allowed | Denied | Denied | Denied | Allowed | Allowed | Denied | Denied | Denied | Denied | Denied | Denied |
| `payment.create` | Allowed* | Allowed | Denied | Denied | Denied | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `payment.refund` | Allowed* | Allowed | Denied | Denied | Denied | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `payment.reconcile` | Allowed* | Allowed | Denied | Denied | Denied | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `maintenance.create` | Allowed* | Allowed | Allowed | Denied | Denied | Denied | Allowed | Allowed | Allowed | Denied | Denied | Denied | Allowed |
| `maintenance.view` | Allowed* | Allowed | Allowed | Denied | Denied | Denied | Allowed | Allowed | Allowed | Allowed | Denied | Denied | Allowed |
| `maintenance.assign` | Allowed* | Allowed | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `maintenance.update_status`| Allowed* | Allowed | Allowed | Denied | Denied | Denied | Denied | Allowed | Denied | Denied | Denied | Denied | Denied |
| `maintenance.manage_costs` | Allowed* | Allowed | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `maintenance.close` | Allowed* | Allowed | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `portfolio.view` | Allowed* | Allowed | Allowed | Denied | Denied | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `mandate.create` | Allowed* | Allowed | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `mandate.view` | Allowed* | Allowed | Allowed | Denied | Denied | Denied | Denied | Allowed | Denied | Allowed | Denied | Denied | Denied |
| `mandate.manage` | Allowed* | Allowed | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `tax.manage` | Allowed* | Allowed | Denied | Denied | Denied | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `invoice.create` | Allowed* | Allowed | Allowed | Denied | Denied | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `invoice.view` | Allowed* | Allowed | Allowed | Denied | Denied | Allowed | Denied | Allowed | Denied | Denied | Denied | Denied | Allowed |
| `invoice.void` | Allowed* | Allowed | Denied | Denied | Denied | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `language.view` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `language.manage` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `language.export` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `language.import` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `security.view` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `security.manage` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `location.view` | Allowed* | Allowed | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `location.manage` | Allowed* | Allowed | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `location.deactivate` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `location.import` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `subscription.view` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `subscription.manage` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `promotion.manage` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `advertising.manage` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `advertising.approve` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `virtual_tour.create` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Allowed | Allowed | Allowed | Allowed | Denied | Denied |
| `virtual_tour.manage` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Allowed | Allowed | Allowed | Allowed | Denied | Denied |
| `gis.view_advanced` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `listings.moderate.override_subscription_quota` | Allowed* | Allowed | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |
| `subscriptions.entitlements.bypass` | Allowed* | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied | Denied |

*\*Allowed\**: `super_admin` has a universal code-level bypass in `User::hasPermission()`.  
*\*\*Denied\*\*: In the database seeder, `moderator` does not have `property.approve`. However, `ModerateListingAction::canModerate()` contains a hardcoded fallback: `if ($user->hasAnyRole(['moderator', ...])) return true;` which allows `moderator` to approve listings despite lacking the `property.approve` database permission.

---

## 5. Route Security & Middleware Audit

### 5.1 Route Security Inventory

| Route | Method | Controller & Action | Authentication Middleware | Role/Permission Check in Controller | Ownership / Isolation Check | Security Assessment |
|---|---|---|---|---|---|---|
| `/admin/dashboard` | `GET` | `AdminDashboardController` | `auth` | `hasAnyRole(['super_admin', 'platform_admin', 'admin', 'moderator', 'verification_officer'])` | N/A | **Partially Verified** (uses ghost role `'admin'`) |
| `/admin/moderation` | `GET` | `ListingModerationController@index` | `auth` | **NONE** | **NONE** | **Security Risk:** Open to any authenticated user |
| `/admin/moderation/{listing}` | `GET` | `ListingModerationController@show` | `auth` | **NONE** | **NONE** | **Security Risk:** Open to any authenticated user |
| `/admin/listings/{listing}/approve` | `POST` | `ListingModerationController@approve` | `auth` | `canModerate()`, `canOverrideSubscriptionQuota()` | Model state check | **Verified** |
| `/admin/security` | `GET` | `SecurityAdminController@index` | `auth` | `hasPermission('security.view')` | N/A | **Verified** |
| `/admin/security/users/{id}/status` | `POST` | `SecurityAdminController@toggleUserStatus` | `auth` | `hasPermission('security.manage')` | **NONE** (No hierarchy guard against super_admin) | **Security Risk:** Platform Admin can suspend Super Admin |
| `/admin/settings` | `GET` | `SettingController@index` | `auth` | **NONE** | **NONE** | **Security Risk:** Open to all logged-in users; leaks private settings |
| `/admin/settings/translations` | `POST` | `SettingController@updateTranslations` | `auth` | **NONE** | **NONE** | **Security Risk:** Any user can modify system translations |
| `/admin/taxonomy` | `GET` | `PropertyTaxonomyAdminController@index` | `auth` | `hasPermission('property.update')` | **NONE** | **Security Risk:** Accessible to all property owners, agents, hosts |
| `/admin/gis/heatmaps` | `GET` | `GisHeatmapAdminController@index` | `auth` | `hasPermission('gis.view_advanced')` | N/A | **Verified** |
| `/admin/subscriptions/plans` | `GET` | `SubscriptionPlanAdminController@index` | `auth` | `hasPermission('subscription.manage')` | N/A | **Verified** |
| `/admin/advertising` | `GET` | `AdvertisingAdminController@index` | `auth` | `hasPermission('advertising.manage')` | N/A | **Verified** |
| `/crm/dashboard` | `GET` | `CrmDashboardController@index` | **NONE (Public)** | `if ($user) ...` | **Fails when user is null** | **CRITICAL Risk:** Leaks leads & phones to unauthenticated guests |
| `/host/dashboard` | `GET` | `HostDashboardController@index` | **NONE (Public)** | `if (! $host) $host = Host::first()` | **Fails when user is null** | **CRITICAL Risk:** Leaks first host revenues & KPIs to guests |
| `/projects` | `GET` | `ProjectController@index` | **NONE (Public)** | Public showcase | N/A | **Verified** |
| `/projects/create` | `GET` | `ProjectController@create` | **NONE (Public)** | Checked in controller: `abort_unless($dev, 403)` | N/A | **Partially Verified** |
| `/projects` | `POST` | `ProjectController@store` | **NONE (Public)** | Checked in controller | **Falls back to random company ID** | **CRITICAL Risk:** BOLA/IDOR allows creating project on another's company |
| `/payments/checkout` | `GET` | `PaymentController@checkout` | `auth` | Implicit | Validates payable type | **Verified** |
| `/payments/{uuid}` | `GET` | `PaymentController@show` | `auth` | Direct check | `$payment->customer_user_id === $user->id` | **Verified** |
| `/payouts` | `GET` | `HostPayoutController@index` | `auth` | `if ($host) ...` | **Fails when user is not a host** | **CRITICAL Risk:** Shows all platform payouts to non-host users |
| `/payouts/{uuid}/hold` | `POST` | `HostPayoutController@hold` | `auth` | **NONE** | **NONE** | **CRITICAL Risk:** Any user can put any host payout on hold |
| `/payouts/{uuid}/process` | `POST` | `HostPayoutController@process` | `auth` | **NONE** | **NONE** | **CRITICAL Risk:** Any user can trigger settlement of host payouts |
| `/invoices` | `GET` | `InvoiceController@index` | `auth` | `MultiTenantOrganizationGuard` | Scoped to company / tenant | **Verified** |
| `/invoices/{uuid}` | `GET` | `InvoiceController@show` | `auth` | `MultiTenantOrganizationGuard` | Scoped to customer or company | **Verified** |
| `/portfolio/dashboard` | `GET` | `PortfolioController@dashboard` | `auth` | `MultiTenantOrganizationGuard` | Company & branch isolation | **Verified** |
| `/documents/download/{uuid}/v/{v}` | `GET` | Closure in `routes/web.php` | `auth`, `signed` | `GenerateDocumentSignedUrlAction` | Uploader, staff, or owner check | **Verified** |

---

## 6. Resource Ownership & IDOR Vulnerabilities

### 6.1 CRITICAL Risk 1: Unauthenticated CRM Lead & Phone Disclosure
- **File:** `routes/web.php:309` and `app/Http/Controllers/CrmDashboardController.php:89-92`
- **Mechanism:** In `routes/web.php`, the CRM dashboard route is defined outside `Route::middleware('auth')`. In `CrmDashboardController::index()`, the scoping logic is:
  ```php
  $leadQuery = CrmLead::query()->latest('created_at')->limit(20);
  if ($user) {
      $leadQuery = $this->leadGuard->scopeQueryForUser($user, $leadQuery);
  }
  ```
- **Exploitation:** When an unauthenticated visitor requests `GET /crm/dashboard`, `$user` is `null`. The guard is bypassed completely. The application renders the latest 20 leads with full client names, unmasked phone numbers (`$l->client_phone`), budget ranges, and notes.

### 6.2 CRITICAL Risk 2: Host KPI & Private Financial Leakage to Unauthenticated Guests
- **File:** `routes/web.php:310` and `app/Http/Controllers/HostDashboardController.php:38-41`
- **Mechanism:** The route `GET /host/dashboard` is outside `Route::middleware('auth')`. Inside the controller:
  ```php
  if (! $host) {
      $host = Host::query()->first();
  }
  ```
- **Exploitation:** When an unauthenticated guest accesses `/host/dashboard`, `$host` is automatically populated with the very first Host in the database. The visitor sees that host's gross revenue, net payout, occupancy rate, ADR, cleaning tasks, and private damage claims.

### 6.3 CRITICAL Risk 3: Complete Payout Exposure & Unauthorized Settlement
- **File:** `app/Http/Controllers/HostPayoutController.php:31-35, 60-139`
- **Mechanism 1 (Exposure):** In `HostPayoutController::index`:
  ```php
  $query = HostPayout::query()->with(['booking', 'destination', 'adjustments']);
  if ($host && ! $user->hasRole('super_admin')) {
      $query->where('host_id', $host->id);
  }
  ```
  If an authenticated user is **not** a host (e.g. an ordinary buyer or tenant), `$host` is `null`. The `if` condition evaluates to `false`. Therefore, the query does NOT filter by `host_id`, and paginates through **every host payout on the platform**.
- **Mechanism 2 (Unchecked Settlement):** The actions `hold`, `release`, and `process` accept a payout UUID:
  ```php
  public function process(Request $request, string $uuid): RedirectResponse|JsonResponse
  {
      $payout = HostPayout::query()->where('uuid', $uuid)->firstOrFail();
      $settled = $this->processAction->execute($payout, $request->user());
      ...
  }
  ```
  Neither the controller nor `ProcessHostPayoutAction` checks whether `$request->user()` is a finance officer or administrator! Any logged-in user can trigger disbursement and financial ledger postings for any host payout.

### 6.4 CRITICAL Risk 4: Broken Object Level Authorization in Development Creation
- **File:** `app/Http/Controllers/ProjectController.php:215-219`
- **Mechanism:** When storing a new development project:
  ```php
  $companyId = $validated['company_id'] ?? $developerProfile?->company_id;
  if (! $companyId) {
      $companyId = DB::table('companies')->where('owner_user_id', $user->id)->value('id')
          ?? DB::table('companies')->value('id');
  }
  ```
- **Exploitation:** A developer user can pass an arbitrary `company_id` in the request, or if none is provided, it falls back to `DB::table('companies')->value('id')` (the first company in the database). Projects can thus be created and bound to competitor development companies.

### 6.5 HIGH Risk 5: System Settings & Website Translation Tampering
- **File:** `app/Http/Controllers/Admin/SettingController.php:16-71`
- **Mechanism:** `SettingController` contains no authorization check. Any authenticated user can view private system settings (`$settingService->all(allowPrivate: true)`) and submit translations to `POST /admin/settings/translations`, overwriting site-wide language files on disk.

### 6.6 HIGH Risk 6: Unintended Administrative Privilege via Overly Broad Permission
- **File:** `app/Http/Controllers/Admin/PropertyTaxonomyAdminController.php:29-35`
- **Mechanism:** To administer system categories, property types, listing types, and amenities, the controller checks:
  ```php
  if (method_exists($user, 'hasPermission') && $user->hasPermission('property.update')) {
      $isAuthorized = true;
  }
  ```
- **Exploitation:** In `RbacRolePermissionSeeder.php`, the permission `property.update` is granted to `property_owner`, `host`, `agent`, and `developer` so they can edit their listings. Because the taxonomy controller checks `property.update`, all four marketplace roles can create, update, toggle, and delete system-wide property types and categories.

---

## 7. Company-Level Isolation & Multi-Tenancy

### 7.1 What Exists & Works
- **`MultiTenantOrganizationGuard` (`app/Domains/Portfolio/Services/MultiTenantOrganizationGuard.php`):**
  - Reliably resolves an actor's company through ownership (`companies.owner_user_id`), branch management (`company_branches.manager_user_id`), or agent profile (`agent_profiles.user_id`).
  - Successfully enforces fail-closed company scoping in:
    - Invoicing (`app/Http/Controllers/InvoiceController.php`)
    - Portfolio dashboard & inventory matrix (`app/Http/Controllers/PortfolioController.php`)
    - Maintenance work orders (`app/Http/Controllers/MaintenanceController.php`)
  - Correctly distinguishes Head Office administrators from branch-scoped actors.

### 7.2 Multi-Tenancy Gaps
1. **Roles Are 100% Global, Not Company-Scoped:**
   - The tables `user_roles` and `user_permissions` have no `company_id` column.
   - If User A is granted the `agent` role, they are globally recognized as an agent across all modules.
   - A user cannot be an "Owner" in Agency A and an "Agent" in Agency B.
2. **Single-Company Assumption:**
   - `resolveActorCompanyId` resolves only the first company found for a user. There is no active company context selector or session tenant switcher.
3. **No Company-User Membership Pivot:**
   - There is no `company_user` table. Relationships are fragmented across `companies.owner_user_id`, `company_branches.manager_user_id`, `agent_profiles.company_id`, and `developer_profiles.company_id`.

---

## 8. Privilege Escalation & Role Assignment

### 8.1 Registration & Self-Service Gating (Verified Secure)
- **File:** `app/Domains/Identity/Actions/VerifyOtpAndAuthenticateAction.php:18-42`
- When registering via OTP, user input `role` is strictly sanitized against `ALLOWED_SELF_SERVICE_ROLES`:
  `['buyer', 'tenant', 'property_owner', 'host', 'agent']`.
- Submitting `super_admin`, `platform_admin`, or `moderator` automatically defaults to `'buyer'`.

### 8.2 Staff & Role Management Gap
- **Absence of Role Management UI:** There is no controller, endpoint, or admin interface to assign or revoke roles for users.
- **Absence of Permission Management UI:** Permissions cannot be assigned to roles or users through the application.
- **Direct Role Assignment:** Roles can currently only be assigned via database seeders or raw database operations.

### 8.3 Hierarchy Bypass in Account Status Management
- In `SecurityAdminController::toggleUserStatus`, any administrator with `security.manage` (e.g. `platform_admin`) can change the status of any user. There is no protection preventing a `platform_admin` from suspending or banning the `super_admin`.

---

## 9. Financial Separation of Duties

### 9.1 Double-Entry Reconciliation & Refunds
- **File:** `app/Http/Controllers/PaymentController.php:271-365`
- `PaymentController` implements a two-stage settlement architecture:
  - Stage 1: `refundUnapplied` (Debits Account 2040 Unapplied Funds, Credits Account 2050 Customer Refunds Payable).
  - Stage 2: `confirmRefundDisbursement` (Debits Account 2050, Credits Account 1010 Cash & Clearing).
- **Separation of Duties Weakness:** Both Stage 1 and Stage 2 check only:
  `$user && ($user->hasRole('super_admin') || $user->hasRole('platform_admin'))`.
  There is no check ensuring that the user confirming external disbursement is different from the user who authorized the refund. The same administrator can initiate, authorize, and confirm disbursements to arbitrary bank accounts.
- **Finance Officer Lockout:** In `RbacRolePermissionSeeder.php`, `finance_officer` is assigned `payment.refund` and `payment.reconcile`. However, `PaymentController` hardcodes checks for `super_admin` and `platform_admin`, completely locking out `finance_officer` from the reconciliation workbench and refund actions.

---

## 10. Audit Logging Architecture

### 10.1 Implementation Details
- **Service:** `app/Domains/Administration/Services/AuditService.php`
- **Model:** `app/Domains/Administration/Models/AuditLog.php`
- **Database Table:** `audit_logs` (UUID primary key, JSON `old_values`, JSON `new_values`, `ip_address`, `user_agent`, `created_at`).
- **Data Redaction:** `AuditService::redactSensitive()` automatically strips passwords, tokens, API keys, PINs, and hashes.

### 10.2 Immutability Assessment
- **Application Level:** `AuditLog::delete()` throws `LogicException('Audit logs are append-only and cannot be deleted.')`.
- **Gaps:**
  - `update()` is not overridden on `AuditLog`; individual models can theoretically be updated via Eloquent.
  - Query Builder operations (`DB::table('audit_logs')->delete()`) bypass Eloquent protection.
  - There are no database-level append-only triggers or table write permissions configured.

### 10.3 Operations Currently Audit-Logged
- Quota overrides in moderation (`listing_subscription_quota_override`, `denied`, `rejected`)
- Unapplied funds refund authorization (`reconciliation_refund_authorized`)
- Refund disbursement confirmation (`reconciliation_refund_disbursement_confirmed`)
- User status toggle (`user.status_changed`)
- Location creation & deactivation (`location.created`, `location.deactivated`)
- Translation updates & imports (`translations.imported`, `translations.bulk_updated`)

### 10.4 Operations Missing Audit Logging
- Role or permission assignment
- Listing approvals, rejections, or suspensions (where quota is not bypassed)
- Payout holds, releases, or processing
- Creation, modification, or deletion of subscription plans and feature limits
- Promotion tier price adjustments
- Advertisement campaign approvals or rejections
- Project creation or verification
- Document downloads without signed URL action

---

## 11. Subscription Entitlements vs RBAC Separation

The separation between RBAC (capabilities) and Subscriptions (monetized capacity) was hardened in Phase 5 Milestone 2 and verified during this audit:

1. **RBAC Governs Action Authority:**
   - Controls whether a user can perform an administrative action (e.g. `property.approve`, `listings.moderate.override_subscription_quota`, `subscription.manage`).
2. **Subscriptions Govern Resource Limits:**
   - Handled exclusively by `SubscriptionEntitlementService` (`app/Domains/Subscription/Services/SubscriptionEntitlementService.php`).
   - Quotas (`property.max_active_listings`, `property.max_images`, `property.team_members`, `virtual_tours`, `advanced_gis`) are validated against the user's active paid subscription plan or the Free default tier.
3. **Staff Quota Equality:**
   - Staff roles (`moderator`, `verification_officer`, `customer_support`) do **not** receive automatic listing quotas or free subscriptions. Only `super_admin` has a platform-testing exemption for core demonstration records.
4. **Administrative Overrides:**
   - Moderation approval overrides require the explicit RBAC permission `listings.moderate.override_subscription_quota`, a documented 5–500 character reason, and produce immutable audit logs.

---

## 12. Automated Test Verification

During the audit, the following feature test suites covering authentication, RBAC, moderation security, and entitlements were executed against the in-memory testing SQLite environment:

| Test Suite | Tests | Assertions | Status |
|---|---|---|---|
| `tests/Feature/FoundationAuthenticationAndRbacTest.php` | 8 | 47 | Passed |
| `tests/Feature/ModerationQuotaOverrideSecurityTest.php` | 7 | 36 | Passed |
| `tests/Feature/SubscriptionEntitlementAndQuotaTest.php` | 8 | 30 | Passed |
| `tests/Feature/Phase4Milestone2SecurityAndAuthorizationTest.php` | 23 | 99 | Passed |
| `tests/Feature/VerificationAndModerationTest.php` | 9 | 42 | Passed |
| **Total Automated Coverage** | **55** | **254** | **100% Passed** |

*Environment Limitation:* These automated tests prove logic correctness under SQLite in-memory testing. Native PostgreSQL multi-connection concurrency and PostgreSQL-specific role attributes were not evaluated.

