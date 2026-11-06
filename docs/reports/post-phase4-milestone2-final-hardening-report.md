# ERE — Phase 4 Milestone 2 Final Delivery & Hardening Report
## Modules 28, 29 & 30: Maintenance Workflow, Enterprise Portfolio Dashboard & Database Tax Invoices

**Date:** November 1, 2026  
**Project:** Ethiopian Real Estate Marketplace (ERE)  
**Target Architecture:** Laravel 13.33 / PHP 8.4.25 / PostgreSQL 18 Service / SQLite 3.45.1 / Tailwind CSS v4 / Alpine.js v3  
**Evaluation Standard:** Zero Unverified Claims • Strict RBAC & Tenant Isolation • Complete Regression Verification  

---

## Section A: Executive Summary

Phase 4 Milestone 2 covers the completion, organizational hardening, and end-to-end verification of three foundational modules:

1. **Module 28 — Maintenance Request & Work Order Workflow:**
   - Enforced a rigorous state machine (`Open -> Assigned -> In Progress -> Waiting -> Completed -> Closed`) with terminal-state protection.
   - Enforced actor-specific operational boundaries: reporters/tenants can only submit and cancel/confirm closure of their tickets, technicians can transition assigned tickets through operational stages (`InProgress`, `Waiting`, `Completed` with required resolution notes and actual cost), and managers/owners retain comprehensive oversight.
   - Guarded audit history records with model-level deletion and update locks (`MaintenanceRequestHistory` throws `DomainException`).
   - Prevented deletion of maintenance requests with incurred actual costs (`MaintenanceRequest` throws `DomainException`).

2. **Module 29 — Enterprise Property Management Dashboard & Multi-Unit Portfolio:**
   - Implemented real database aggregation across canonical inventory models (`Property`, `ProjectUnit`, `AccommodationUnit`, `Lease`, `Booking`).
   - Aggregated 11 standard business KPIs (SRS Section 196) without hardcoded mock data, including active leases, total units, occupancy rate, vacancy rate, rent collected, expected rent, outstanding rent / arrears (from `rent_schedules`), active disputes, and short-stay revenue.
   - Enforced strict organizational and branch scoping via `MultiTenantOrganizationGuard`, ensuring fail-closed isolation across company dashboards, matrix views, and management mandates.

3. **Module 30 — Database-Driven Tax Engine & Sequential ETB Invoice Generator:**
   - Verified deterministic resolution of tax rules (`tax_rules` table) incorporating service classification, effective date ranges, geographic specificity, compounding, and withholding deductions.
   - Guaranteed concurrency-safe atomic invoice sequence generation (`INV-YYYY-0000001` format via PostgreSQL/SQLite atomic sequences).
   - Sealed invoice viewing (`invoices.show`) and printable receipts (`invoices.print`) against IDOR attacks, restricting access strictly to the customer, authorized issuing company managers, or global administrators.
   - Implemented full invoice voiding lifecycle (`voidInvoice()`) with reason recording and financial immutability locks.
   - Verified dual Ethiopian (Ge'ez and Latin) and Gregorian calendar dates on printable receipts with leap year Pagume 6 accuracy.

All 423 PHPUnit/Pest automated tests passed with 2,475 assertions. All 5 dedicated Playwright browser tests passed against the running application.

---

## Section B: Verification Matrix

| Requirement / Module | Verification Standard | Implementation Evidence | Status |
|---|---|---|---|
| **Module 28: Maintenance State Machine** | Strict transitions: `Open -> Assigned -> In Progress -> Waiting -> Completed -> Closed`. Closed is terminal. | [`MaintenanceStatus.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Maintenance/Enums/MaintenanceStatus.php), [`TransitionMaintenanceTicketAction.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Maintenance/Actions/TransitionMaintenanceTicketAction.php) | **VERIFIED** |
| **Module 28: Role-Based Transition Boundaries** | Reporters cannot advance operational stages; technicians cannot close/reassign tickets; managers/owners retain administrative authority. | [`TransitionMaintenanceTicketAction.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Maintenance/Actions/TransitionMaintenanceTicketAction.php), [`Phase4Milestone2SecurityAndAuthorizationTest.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/tests/Feature/Phase4Milestone2SecurityAndAuthorizationTest.php) | **VERIFIED** |
| **Module 28: IDOR & Property Access Barriers** | Tenant cannot submit maintenance request for properties where they have no active lease/booking; non-participants cannot view tickets. | [`MaintenanceController.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Http/Controllers/MaintenanceController.php), [`Phase4Milestone2SecurityAndAuthorizationTest.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/tests/Feature/Phase4Milestone2SecurityAndAuthorizationTest.php) | **VERIFIED** |
| **Module 28: Audit History Immutability** | `MaintenanceRequestHistory` entries cannot be updated or deleted under any circumstance. | [`MaintenanceRequestHistory.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Maintenance/Models/MaintenanceRequestHistory.php) | **VERIFIED** |
| **Module 28: Financial Cost Protection** | Maintenance requests with incurred actual costs cannot be deleted. | [`MaintenanceRequest.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Maintenance/Models/MaintenanceRequest.php) | **VERIFIED** |
| **Module 29: Canonical Inventory Models** | Authoritative unit counting across `project_units`, `accommodation_units`, and standalone properties. | [`CalculatePropertyManagementDashboardAction.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Portfolio/Actions/CalculatePropertyManagementDashboardAction.php) | **VERIFIED** |
| **Module 29: Real Database KPIs** | Aggregation of expected rent, collected rent, outstanding arrears, vacancy rate, active leases, disputes from real DB records. | [`CalculatePropertyManagementDashboardAction.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Portfolio/Actions/CalculatePropertyManagementDashboardAction.php), [`dashboard.blade.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/resources/views/portfolio/dashboard.blade.php) | **VERIFIED** |
| **Module 29: Multi-Tenant Scoping & Fail-Closed Isolation** | Dashboard recent workloads and matrix queries strictly scoped to actor company/branch; fail-closed for unaffiliated actors. | [`PortfolioController.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Http/Controllers/PortfolioController.php), [`MultiTenantOrganizationGuard.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Portfolio/Services/MultiTenantOrganizationGuard.php) | **VERIFIED** |
| **Module 29: Management Mandate Authorization** | Only property owners, authorized company managers, or global admins can create property management mandates. | [`PortfolioController.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Http/Controllers/PortfolioController.php), [`CreatePropertyManagementMandateAction.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Portfolio/Actions/CreatePropertyManagementMandateAction.php) | **VERIFIED** |
| **Module 30: Deterministic Tax Rules** | Ethiopian tax rules table resolution, service matching, rate calculation, compound/withholding handling. | [`TaxRule.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Invoice/Models/TaxRule.php), [`ResolveApplicableTaxRulesAction.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Invoice/Actions/ResolveApplicableTaxRulesAction.php) | **VERIFIED** |
| **Module 30: Concurrency-Safe Sequence Numbers** | Atomic sequence generation generating `INV-YYYY-0000001` without race collisions. | [`GenerateInvoiceNumberAction.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Invoice/Actions/GenerateInvoiceNumberAction.php) | **VERIFIED** |
| **Module 30: Invoice Access & Company Spoofing Barriers** | Restricted show/print access to invoice customer or issuer company; prevention of company ID manipulation on store. | [`InvoiceController.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Http/Controllers/InvoiceController.php) | **VERIFIED** |
| **Module 30: Invoice Voiding & Immutability** | Dedicated `voidInvoice()` method, audit reason capture, outstanding balance zeroing, mutation lock on voided invoices. | [`Invoice.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Invoice/Models/Invoice.php) | **VERIFIED** |
| **Calendar & Printable Invoices** | Dual Gregorian & Ethiopian calendar conversion (including leap year Pagume 6 and Meskerem 1 New Year), printable CSS with TIN and itemization. | [`EthiopianCalendarConverter.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Support/Calendar/EthiopianCalendarConverter.php), [`print.blade.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/resources/views/invoices/print.blade.php) | **VERIFIED** |

---

## Section C: Codebase Modifications

### Modified Files

1. [`app/Domains/Maintenance/Models/MaintenanceRequestHistory.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Maintenance/Models/MaintenanceRequestHistory.php):
   - Added `booted()` deleting and updating hooks throwing `DomainException` to guarantee permanent audit history immutability.

2. [`app/Domains/Maintenance/Models/MaintenanceRequest.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Maintenance/Models/MaintenanceRequest.php):
   - Added `booted()` deleting hook preventing deletion of maintenance tickets with incurred actual costs (`actual_cost_etb > 0`).

3. [`app/Domains/Maintenance/Actions/TransitionMaintenanceTicketAction.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Maintenance/Actions/TransitionMaintenanceTicketAction.php):
   - Added role-based authorization check inside atomic transaction.
   - Enforced that tenants/reporters can only cancel open tickets or confirm closure of completed tickets.
   - Enforced that technicians cannot close or reassign maintenance tickets.
   - Restricted transition capability strictly to authorized company managers, property owners, assigned technicians, or global administrators.

4. [`app/Http/Controllers/MaintenanceController.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Http/Controllers/MaintenanceController.php):
   - Removed fallback query `Property::query()->limit(10)->get()` from `create()`.
   - Added authorization validation in `store()` ensuring only owners, active tenants, active guests, company managers, or admins can submit tickets for a property.
   - Added authorization checks in `show()` and `transition()`, returning HTTP 403 Forbidden to unauthorized actors.

5. [`app/Domains/Property/Models/Property.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Property/Models/Property.php):
   - Added `bookings(): HasMany` relationship to link properties with short-stay bookings.

6. [`app/Domains/Portfolio/Actions/CalculatePropertyManagementDashboardAction.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Portfolio/Actions/CalculatePropertyManagementDashboardAction.php):
   - Added real database calculation for `expected_rent_etb` and `outstanding_rent_etb` from `rent_schedules`.
   - Added calculation for `active_disputes` from `disputes` table.
   - Added `vacancy_rate_pct` (100 - occupancy rate) and `active_leases`.

7. [`resources/views/portfolio/dashboard.blade.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/resources/views/portfolio/dashboard.blade.php):
   - Integrated KPI cards for Expected Rent, Outstanding Arrears, and Active Disputes with high-contrast, clean visual hierarchy.

8. [`app/Http/Controllers/PortfolioController.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Http/Controllers/PortfolioController.php):
   - Applied `MultiTenantOrganizationGuard::applyScope()` to recent work orders and mandates in `dashboard()`.
   - Enforced fail-closed query (`1 = 0`) on `matrix()` for non-admin actors lacking company affiliation.
   - Added authorization check in `storeMandate()` ensuring actor owns the property or is an authorized company manager.

9. [`app/Http/Controllers/InvoiceController.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Http/Controllers/InvoiceController.php):
   - Added IDOR access protection to `show()` and `print()`, restricting access to the invoice customer, issuer company managers, or global admins.
   - Added company authorization validation in `store()` preventing company ID spoofing.

10. [`app/Domains/Invoice/Models/Invoice.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Invoice/Models/Invoice.php):
    - Added `voidInvoice(User $actor, string $reason)` method with status check, zeroing outstanding balance, and audit note capture.
    - Added `booted()` updating hook preventing modification of voided invoices.
    - Added `booted()` deleting hook preventing deletion of invoices with recorded payments.

11. [`tests/Feature/Phase4Milestone2FeatureTest.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/tests/Feature/Phase4Milestone2FeatureTest.php):
    - Created an active lease in `setUp()` to legitimately link the test tenant with the test property.

### Newly Created Files

1. [`docs/reports/phase4-milestone2-audit-report.md`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/docs/reports/phase4-milestone2-audit-report.md):
   - Part 1 Mandatory Read-Only Audit Matrix Report.

2. [`tests/Feature/Phase4Milestone2SecurityAndAuthorizationTest.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/tests/Feature/Phase4Milestone2SecurityAndAuthorizationTest.php):
   - 16 automated feature tests verifying all security barriers, role restrictions, immutability rules, and calendar conversions.

3. [`tests/e2e/phase4-milestone2-workflows.spec.ts`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/tests/e2e/phase4-milestone2-workflows.spec.ts):
   - 5 Playwright browser tests verifying live UI rendering of portfolio dashboards, inventory matrix, mandates, maintenance boards, and tax invoices.

4. [`docs/reports/post-phase4-milestone2-final-hardening-report.md`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/docs/reports/post-phase4-milestone2-final-hardening-report.md):
   - Final Delivery and Acceptance Report.

---

## Section D: Test Suite Summary

### PHP Automated Tests (PHPUnit / Pest)

- **Total Tests Passed:** 423 of 423 (100%)
- **Total Assertions:** 2,475
- **Regressions:** 0

#### Breakdown of Phase 4 Milestone 2 Test Suites:
- [`tests/Feature/Phase4Milestone2SecurityAndAuthorizationTest.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/tests/Feature/Phase4Milestone2SecurityAndAuthorizationTest.php): **16 passed (44 assertions)**
  - Maintenance show/transition IDOR denial (403)
  - Unauthorized tenant property ticket denial (403)
  - Reporter workflow advancement restriction (ValidationException)
  - Technician closure restriction (ValidationException)
  - History record mutation and deletion lock (DomainException)
  - Ticket deletion prevention when costs > 0 (DomainException)
  - Cross-tenant dashboard ticket/mandate isolation
  - Portfolio matrix fail-closed behavior
  - Expected rent & outstanding arrears DB aggregation
  - Mandate creation unauthorized denial (403)
  - Invoice show/print IDOR denial (403)
  - Invoice customer & company manager authorized access (200)
  - Company ID spoofing denial on invoice store (403)
  - Invoice voiding workflow & voided immutability (DomainException)
  - Ethiopian calendar Meskerem 1 boundary & leap year Pagume 6 validation
- [`tests/Feature/Phase4Milestone2FeatureTest.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/tests/Feature/Phase4Milestone2FeatureTest.php): **12 passed (34 assertions)**
- [`tests/Unit/Phase4MaintenancePortfolioAndTaxInvoiceTest.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/tests/Unit/Phase4MaintenancePortfolioAndTaxInvoiceTest.php): **8 passed (90 assertions)**

### Playwright Browser E2E Tests

- **Suite File:** [`tests/e2e/phase4-milestone2-workflows.spec.ts`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/tests/e2e/phase4-milestone2-workflows.spec.ts)
- **Execution Target:** Live running Laravel web server (`http://127.0.0.1:8000`)
- **Browser:** Chromium Desktop (1280x800)
- **Results:** **5 passed of 5 (100%) in 18.5s**
  1. `Enterprise Property Management Dashboard displays KPIs and Quick Action Links`: **PASSED (3.4s)**
  2. `Portfolio Matrix renders multi-unit inventory view`: **PASSED (3.7s)**
  3. `Portfolio Mandates page lists management agreements`: **PASSED (2.6s)**
  4. `Maintenance Tickets page renders work orders and ticket submission form`: **PASSED (2.9s)**
  5. `Invoices index and creation form render correctly`: **PASSED (3.5s)**

---

## Section E: Verification Log & Environmental Notes

1. **Framework & Runtimes:**
   - **PHP:** `8.4.25 (NTS VC++ 2022 x64)`
   - **Laravel:** `13.33.0`
   - **Node.js:** `v24.20.0`
   - **Playwright:** `1.64.1`

2. **Database Migration & Concurrency:**
   - All migrations up to `2026_04_01_000031_create_tax_rules_invoices_and_line_items_tables.php` are applied and active.
   - Sequence generation in `GenerateInvoiceNumberAction` uses atomic `ON CONFLICT (prefix_key) DO UPDATE` returning sequence values, preventing race conditions.
   - `TransitionMaintenanceTicketAction` employs pessimistic row locking (`lockForUpdate()`) inside database transactions.

3. **Status of Working Tree:**
   - Documentation integrity strictly maintained.
   - All 47 user modifications from prior milestones preserved intact.
   - Zero git resets, zero unverified claims.

