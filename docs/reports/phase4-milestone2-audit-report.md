# ERE — Phase 4 Milestone 2 Audit Matrix Report
## Modules 28–30: Maintenance Requests, Enterprise Portfolio Dashboard & Database Tax Invoices

**Date:** November 1, 2026  
**Project:** Ethiopian Real Estate Marketplace (ERE)  
**Environment:** PHP 8.4.25, Laravel 13.33.0, PostgreSQL 18 Service / SQLite 3.45.1  

---

### Audit Matrix

| Requirement / Module | Existing Implementation | Evidence / Files | Status | Required Action |
|---|---|---|---|---|
| **Module 28: Maintenance Ticket State Machine** | Fully defined in enum `MaintenanceStatus` (`Open`, `Assigned`, `InProgress`, `Waiting`, `Completed`, `Closed`) with transition rules and `isTerminal()`. Handled via `TransitionMaintenanceTicketAction` and `MaintenanceController`. | [`MaintenanceStatus.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Maintenance/Enums/MaintenanceStatus.php), [`TransitionMaintenanceTicketAction.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Maintenance/Actions/TransitionMaintenanceTicketAction.php), [`MaintenanceController.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Http/Controllers/MaintenanceController.php) | **VERIFIED** | Enforce actor authorization checks in `transition()`: reporter/tenant may only cancel/close open tickets; technician transitions assigned tickets; manager/admin transitions all. |
| **Module 28: Work Order Assignment & Tracking** | Technician user assignment, contractor name, phone, estimated cost, actual cost, milestone timestamps (`assigned_at`, `started_at`, `completed_at`, `closed_at`). | [`create_maintenance_requests_and_work_orders_tables.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/database/migrations/2026_04_01_000029_create_maintenance_requests_and_work_orders_tables.php), [`MaintenanceRequest.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Maintenance/Models/MaintenanceRequest.php) | **VERIFIED** | Validate cost precision and non-negative constraints. Ensure billable party assignment adheres to domain rules. |
| **Module 28: Authorization & IDOR Protection** | Controller methods `show()`, `transition()`, and `create()` lacked tenant-to-property relationship verification; `create()` had fallback exposing random properties. | [`MaintenanceController.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Http/Controllers/MaintenanceController.php) | **PARTIALLY IMPLEMENTED** | Remove fallback `Property::limit(10)`. Enforce access checks on `show()` and `transition()` ensuring only reporter, assigned technician, property owner, active tenant/guest, or company manager have access. |
| **Module 28: Audit History Immutability** | `maintenance_request_histories` records actor, from/to status, cost snapshot, notes. Lacked model-level immutable guards. | [`MaintenanceRequestHistory.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Maintenance/Models/MaintenanceRequestHistory.php) | **PARTIALLY IMPLEMENTED** | Add `booted()` deleting and updating hooks throwing `DomainException` to guarantee immutable history. Prevent deletion of tickets with actual costs or history. |
| **Module 29: Canonical Inventory Models** | Models for `Property`, `ProjectUnit`, `AccommodationUnit`, `Lease`, `Booking` exist. Total units computed as authoritative inventory across multi-unit portfolios. | [`Property.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Property/Models/Property.php), [`ProjectUnit.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Developer/Models/ProjectUnit.php), [`AccommodationUnit.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Stay/Models/AccommodationUnit.php) | **VERIFIED** | Preserve canonical inventory integration across matrix and dashboard views. |
| **Module 29: Database-Driven KPIs** | Real DB aggregation for `properties_managed`, `total_units`, `occupied_units`, `vacant_units`, `occupancy_rate_pct`, `tenants`, `guests`, `rent_collected_etb`, `short_stay_revenue_etb`, `open_maintenance_requests`, `active_cleaning_operations`. Missing `expected_rent_etb`, `outstanding_rent_etb`, `active_disputes`. | [`CalculatePropertyManagementDashboardAction.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Portfolio/Actions/CalculatePropertyManagementDashboardAction.php), [`dashboard.blade.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/resources/views/portfolio/dashboard.blade.php) | **PARTIALLY IMPLEMENTED** | Add calculations for `expected_rent_etb`, `outstanding_rent_etb` from `rent_schedules`, and `active_disputes`. Add cards in `dashboard.blade.php`. |
| **Module 29: MultiTenantOrganizationGuard Isolation** | `MultiTenantOrganizationGuard` applies company and branch scoping, failing closed when company is absent. However, `PortfolioController::dashboard` queried `recentTickets` and `recentMandates` globally without scoping, and `matrix()` lacked fail-closed behavior for unaffiliated users. | [`MultiTenantOrganizationGuard.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Portfolio/Services/MultiTenantOrganizationGuard.php), [`PortfolioController.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Http/Controllers/PortfolioController.php) | **PARTIALLY IMPLEMENTED** | Apply `MultiTenantOrganizationGuard::applyScope()` to recent tickets and mandates in `dashboard()`. Fail-closed in `matrix()` if non-admin has no company. |
| **Module 29: Property Management Mandates** | `PropertyManagementMandate` model, migration, overlap protection, and `CreatePropertyManagementMandateAction`. | [`PropertyManagementMandate.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Portfolio/Models/PropertyManagementMandate.php), [`CreatePropertyManagementMandateAction.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Portfolio/Actions/CreatePropertyManagementMandateAction.php) | **VERIFIED** | Add authorization check in `storeMandate()` to ensure actor owns property or manages company. |
| **Module 30: Database-Driven Tax Engine** | `tax_rules` table with service types, geographic specificity, compounding, withholding, calculation order, and validity dates. Resolved deterministically in `ResolveApplicableTaxRulesAction`. | [`TaxRule.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Invoice/Models/TaxRule.php), [`ResolveApplicableTaxRulesAction.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Invoice/Actions/ResolveApplicableTaxRulesAction.php), [`EthiopianTaxRulesSeeder.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/database/seeders/EthiopianTaxRulesSeeder.php) | **VERIFIED** | Verify compound and withholding calculations with decimal-safe precision. |
| **Module 30: Concurrency-Safe Invoice Numbering** | `GenerateInvoiceNumberAction` uses atomic sequence generation (`property_code_sequences` with `ON CONFLICT DO UPDATE`) generating `INV-YYYY-0000001` format. | [`GenerateInvoiceNumberAction.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Invoice/Actions/GenerateInvoiceNumberAction.php) | **VERIFIED** | Maintain atomic generation under concurrent traffic. |
| **Module 30: Invoice Lifecycle & IDOR Protection** | `CreatePersistedInvoiceAction` creates invoices and itemized line items. However, `InvoiceController::show()` and `print()` had no authorization checks, and `store()` allowed arbitrary company ID spoofing. | [`CreatePersistedInvoiceAction.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Domains/Invoice/Actions/CreatePersistedInvoiceAction.php), [`InvoiceController.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Http/Controllers/InvoiceController.php) | **PARTIALLY IMPLEMENTED** | Restrict `show()` and `print()` to invoice customer, issuer company managers, or admins. Restrict `store()` to company affiliated with actor. Add `voidInvoice()` method to `Invoice`. |
| **Calendar & Printable Receipts** | `EthiopianCalendarConverter` converts Gregorian dates to Ethiopian calendar. Printable receipt in `resources/views/invoices/print.blade.php` displays dual Gregorian/Ethiopian dates, ETB currency, tax breakdown, and issuer TIN. | [`EthiopianCalendarConverter.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/app/Support/Calendar/EthiopianCalendarConverter.php), [`print.blade.php`](file:///c:/Users/Akrem%20Mohmmed/Desktop/projects/ERE/resources/views/invoices/print.blade.php) | **VERIFIED** | Add dedicated test cases for New Year (Meskerem 1) and leap year Pagume boundaries. |

---

### Summary of Actions Required

1. **Module 28 Hardening:**
   - Add actor role authorization inside `TransitionMaintenanceTicketAction` and `MaintenanceController::transition()`.
   - Secure `MaintenanceController::create()` and `show()` against unauthorized property submission and IDOR data leaks.
   - Guard `MaintenanceRequestHistory` with model-level deletion and mutation locks.
   - Guard `MaintenanceRequest` soft-deletion against tickets with actual financial costs.

2. **Module 29 Hardening:**
   - Scope `recentTickets` and `recentMandates` queries on portfolio dashboard to prevent cross-tenant data leakage.
   - Enforce fail-closed behavior on portfolio matrix for unaffiliated users.
   - Calculate missing financial KPIs (`expected_rent_etb`, `outstanding_rent_etb`, `active_disputes`) from real database records.
   - Enforce property ownership and company authority on mandate creation.

3. **Module 30 Hardening:**
   - Guard `InvoiceController::show()` and `InvoiceController::print()` against IDOR access by unassociated users.
   - Prevent company ID spoofing on `InvoiceController::store()`.
   - Provide explicit invoice voiding workflow on `Invoice` model.

4. **Testing & Verification:**
   - Develop comprehensive feature tests verifying all access barriers, immutability constraints, and KPI calculations.
   - Run complete automated test suite and Playwright browser tests.

