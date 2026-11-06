# ERE — Phase 5 Milestone 1 Initial Repository & Architecture Audit Report
## Modules 31 & 32: Unified Ethiopian Payment Engine, Idempotent Webhooks, Auditable Financial Ledger & Host/Landlord Payout Engine

**Audit Date:** November 2, 2026  
**Repository Branch:** `main`  
**Current Commit:** `1091f9b`  
**Framework & Runtime:** PHP 8.4.25 (cli), Laravel 13.33.0  
**Database Drivers:** SQLite 3.45.1 (in-memory test harness), PostgreSQL 18.0 (Active service on port 5432)  
**Queue Driver:** `sync` (testing), `database` (default configuration)  

---

## 1. Executive Summary & Preservation Check

Before modifying any application files, a complete read-only repository and architectural audit was performed in compliance with Phase 5 Milestone 1 requirements.

- **Preservation Check:** All existing code and assets across Modules 00 through 30 were inspected. Zero files were deleted or reset. All existing migrations, models, controllers, tests, and documentation are preserved in place.
- **Architectural Findings:** ERE previously featured isolated payment-related mechanisms:
  1. Short-stay bookings (`bookings` table) stored itemized pricing, deposits, and idempotency keys, but lacked a unified payment gateway processor.
  2. Developer installment contracts (`contract_payment_transactions`) handled manual bank transfer uploads for off-plan development units.
  3. Rental leases (`rental_payments`) recorded rental schedule collections and manual bank slips.
  4. Commission payouts (`commission_payouts`) tracked broker splits.
  5. Sequential tax invoices (`invoices`) handled Ethiopian VAT, withholding, and voiding.
- **Identified Gap:** The repository currently lacks:
  1. A unified payment engine (`payments` table) connecting all payment purposes across the platform under a consistent interface.
  2. An eleventh payment type: `property_purchase` for land and existing property transactions.
  3. Real provider adapter architecture (Telebirr, CBE Birr, Chapa, Card, Bank Transfer) with strict credential handling.
  4. Database-backed transaction initiation idempotency and authenticated webhook processing with deduplication (`UNIQUE(provider, provider_event_id)`).
  5. A canonical double-entry append-only financial ledger with balanced debits and credits (`sum(debits) == sum(credits)`).
  6. A complete host/landlord payout engine with decimal-safe waterfall calculations and configurable scheduling (`immediately`, `after_checkin`, `after_checkout`, `weekly`, `monthly`).

---

## 2. Technology & Runtime Audit

| Component | Target in Codebook | Actual in Repository | Status & Decision |
| :--- | :--- | :--- | :--- |
| **PHP Runtime** | PHP 8.4 with strict typing | PHP 8.4.25 (NTS x64) with `bcmath` enabled | Supported & Verified |
| **Laravel Framework** | Laravel 12 | Laravel 13.33.0 | Preserved (no downgrade) |
| **Database Engine** | PostgreSQL 16 | PostgreSQL 18.0 (Service active on 5432) / SQLite 3.45.1 (Test Harness) | Dual-mode: SQLite test harness + PostgreSQL scripts |
| **Queue Backend** | Redis 7 / Horizon | `sync` for tests; `database` default; Horizon not installed | Safe idempotent job structure |
| **Frontend Stack** | Tailwind CSS 3.4+ / Alpine.js 3.14+ | Tailwind CSS 3.4+, Alpine.js 3.14+ | Maintained |
| **Math Precision** | Decimal-safe arithmetic | `ext-bcmath` active; `EtbFormatter` available | Decimal-safe string/bcmath operations |

---

## 3. Required Audit Matrix (Section 2.6)

| Requirement | Existing implementation | Evidence (File Paths) | Status | Required action |
| :--- | :--- | :--- | :---: | :--- |
| **Unified payments** | None | No central `payments` table or service | Missing | Create unified `Payment` model, migration, and payment lifecycle engine supporting all 11 payment types. |
| **Telebirr integration** | None | `config/services.php` lacks Telebirr | Missing | Create `TelebirrAdapter` implementing standard provider interface with HMAC validation & env-based credentials. |
| **CBE Birr integration** | None | `config/services.php` lacks CBE Birr | Missing | Create `CbeBirrAdapter` with standardized interface, payload normalization, and webhook verification. |
| **Bank transfer verification** | Partial | `contract_payment_transactions` & `rental_payments` | Partial | Unify into `BankTransferProof` and `ReviewBankTransferAction` with private storage, immutable audit history, and role-based review. |
| **Card integration** | None | No card gateway adapter | Missing | Create `ChapaAdapter` supporting card and local payment rails. |
| **Payment idempotency** | Partial | `bookings` & `rental_payments` have individual keys | Partial | Create database-backed `PaymentIdempotency` table and initiation guard preventing concurrent duplicate creation. |
| **Authenticated webhooks** | None | No webhook controller or HMAC validation | Missing | Create `PaymentWebhookController`, `WebhookEvent` table with unique constraint `(provider, provider_event_id)`, and state transition matrix. |
| **Financial ledger** | None | Commission payouts exist, but no general journal | Missing | Create canonical `LedgerAccount`, `JournalPostingGroup`, and `LedgerEntry` tables with append-only protection. |
| **Double-entry postings** | None | No posting service | Missing | Create `DoubleEntryPostingService` enforcing strict mathematical balancing (`sum(debits) == sum(credits)`). |
| **Host payout engine** | Partial | `Host` model has `payout_schedule`; dispute table has host payable column | Partial | Create `HostPayoutCalculator` executing the waterfall: `Booking Revenue - Platform Deductions + Adjustments = Host Payable`. |
| **Payout schedules** | Partial | Enum in `Host` table (`immediately`, `after_checkin`, `after_checkout`, `weekly`, `monthly`) | Partial | Implement release-date calculation and eligibility validation rules for all 5 schedules. |
| **Payment reconciliation** | None | No automated captured vs expected reconciliation | Missing | Implement server-side expected amount calculation and captured-amount reconciliation. |

---

## 4. Existing Business Relationships Integration Mapping

| Business Event | Source Model | Payable Model Relationship | Payment Type | Financial Posting Accounts | Payout Impact |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Short-Stay Booking** | `Booking` | `payable_type = Booking::class`, `payable_id = booking.id` | `booking` | Dr: Clearing (1010)<br>Cr: Host Payable (2010)<br>Cr: Platform Fee (4010)<br>Cr: Taxes Payable (2020) | Generates `HostPayout` on configured schedule |
| **Property Reservation** | `UnitReservation` | `payable_type = UnitReservation::class`, `payable_id = reservation.id` | `reservation` | Dr: Clearing (1010)<br>Cr: Customer Funds / Escrow (2030) | Held in escrow pending contract conversion |
| **Property / Land Purchase** | `PropertyOffer` (accepted) or `DeveloperSalesContract` | `payable_type = PropertyOffer::class`, `payable_id = offer.id` | `property_purchase` | Dr: Clearing (1010)<br>Cr: Customer Funds / Seller Escrow (2030) | Tracked against agreed price; separate from legal title transfer |
| **Developer Installment** | `ContractInstallment` | `payable_type = ContractInstallment::class`, `payable_id = installment.id` | `developer_installment` | Dr: Clearing (1010)<br>Cr: Developer Escrow / Payable (2030) | Applied to specific installment schedule |
| **Rent Collection** | `RentSchedule` | `payable_type = RentSchedule::class`, `payable_id = schedule.id` | `rent` | Dr: Clearing (1010)<br>Cr: Landlord Payable (2010)<br>Cr: Management Fee (4010) | Generates Landlord Payout |
| **Sequential Tax Invoice** | `Invoice` | `payable_type = Invoice::class`, `payable_id = invoice.id` | `service` / `listing` | Dr: Clearing (1010)<br>Cr: Revenue (4010/4020)<br>Cr: VAT Payable (2020) | Settles invoice balance |

---

## 5. Security & Concurrency Considerations

1. **Polymorphic Payable Guard:** Client requests must never supply arbitrary model class names. Only whitelisted models (`Booking`, `UnitReservation`, `PropertyOffer`, `DeveloperSalesContract`, `ContractInstallment`, `RentSchedule`, `Invoice`, `Listing`) are allowed.
2. **Actor Authorization:** Users can only initiate payments for payables they personally own or are authorized to settle.
3. **Double-Payment Locking:** Database transactions with `lockForUpdate()` must prevent concurrent attempts to pay the same payable or replay the same idempotency key.
4. **Append-Only Immutability:** Posted ledger entries cannot be updated or deleted. Database-level trigger mechanisms for PostgreSQL alongside Eloquent event hooks ensure strict tamper-resistance.
5. **No Automatic Title Transfer:** Paying for property/land via `property_purchase` updates the financial ledger and balance payable, but does NOT transfer title deed or legal ownership.

---
*Initial audit completed. Proceeding to implementation.*

