# ERE Role-Based Access Control (RBAC) Audit — Executive Summary
**Prepared for:** Project Owner, Ethiopian Real Estate Marketplace (ERE)  
**Date:** November 6, 2026  
**Audit Status:** Complete Repository & Database Read-Only Assessment

---

## 1. Overview in Plain Language

We have conducted a thorough, read-only inspection of the entire Ethiopian Real Estate Marketplace (ERE) application to examine how user roles, permissions, logins, private documents, company boundaries, and financial transactions are protected.

**The Bottom Line:**
ERE already has an impressive database foundation:
- It has **13 roles** and **51 distinct permissions** defined.
- It has a strong OTP phone authentication system with brute-force protection.
- It has a powerful company-isolation system for invoices and maintenance work orders (`MultiTenantOrganizationGuard`).
- It has a hardened subscription-quota system that prevents staff from publishing free listings without paid plans.

**However, there are several serious security gaps in how routes and pages are locked down:**
- Several management dashboards are left completely open to the public internet or ordinary logged-in users.
- An unauthenticated visitor can view real CRM client leads, unmasked phone numbers, and another host's private financial revenue simply by typing URLs directly into their browser.
- There are no Laravel Policy files in the codebase, meaning authorization checks are written manually and inconsistently across different files.
- Some administrative pages use the wrong permission check, allowing ordinary home sellers to change national property categories and types.

---

## 2. What ERE Currently Has

1. **13 Defined Roles in Database:**
   - **Marketplace (Public):** `buyer` (6 users), `tenant` (2 users), `property_owner` (2 users), `host` (2 users), `agent` (1 user), `developer` (0 users).
   - **Staff & Administration:** `super_admin` (1 user), `platform_admin` (0 users), `property_admin` (0 users), `verification_officer` (0 users), `moderator` (0 users), `finance_officer` (0 users), `customer_support` (0 users).
2. **51 Granular Permissions in Database:**
   - Spanning 13 modules (Property, Verification, Booking, Payments, Maintenance, Portfolio, Mandates, Tax, Invoicing, Security, Localization, GIS, and Subscription Moderation).
3. **Multi-Tenant Organization Guard:**
   - Enforces company and branch isolation for invoices, portfolio dashboards, and maintenance tickets.
4. **Append-Only Audit Service:**
   - Logs quota overrides, user status changes, location edits, and translation imports with automatic password/token redaction.
5. **Verified Passing Test Suites:**
   - 55 automated tests (254 assertions) covering authentication, moderation quota overrides, subscription entitlements, and portfolio authorization pass with 100% success.

---

## 3. What Works Well and Should Be Preserved

1. **OTP Authentication & Phone Verification:**
   - Phone numbers are normalized strictly into Ethiopian E.164 format (`+2519...`, `+2517...`).
   - Brute-force attacks are locked after 5 failed attempts.
   - Self-registration is restricted to safe marketplace roles (`buyer`, `tenant`, `property_owner`, `host`, `agent`). Malicious users cannot self-register as an administrator.
2. **Subscription Entitlements vs RBAC Separation:**
   - Subscription limits (number of active listings, image upload limits, 360° virtual tours) are managed cleanly by `SubscriptionEntitlementService`.
   - Staff do not get free subscription perks. Bypassing a quota during listing moderation requires the dedicated permission `listings.moderate.override_subscription_quota`, a mandatory documented reason (5–500 characters), and creates an audit record.
3. **Private Document Signed URL Vault:**
   - Identity documents and private deeds cannot be downloaded directly. They require 5-minute temporary cryptographic signed URLs with ownership verification, and every download is logged to `document_access_logs`.
4. **Portfolio & Invoicing Company Isolation:**
   - `MultiTenantOrganizationGuard` reliably stops Agency A from viewing Agency B's invoices, maintenance tickets, or portfolio matrix.

---

## 4. What Is Insecure (Ranked Security Findings)

### 🔴 Critical Vulnerabilities (Must Fix Immediately)

1. **Public Information Disclosure on CRM Dashboard (`GET /crm/dashboard`):**
   - **What happens:** The route is outside the login wall. When an unauthenticated visitor visits `/crm/dashboard`, the controller's user-check fails open.
   - **Consequence:** Any person on the internet can see recent real estate leads, client names, unmasked phone numbers, budget ranges, and scheduled viewing appointments.
2. **Host Financial & Booking Data Leaked to Public (`GET /host/dashboard`):**
   - **What happens:** The route is outside the login wall. When an unauthenticated visitor or non-host visits the page, the code automatically grabs the very first host in the database (`Host::first()`).
   - **Consequence:** Any visitor sees that host's gross revenue, net payouts, occupancy rate, cleaning tasks, and private damage claims.
3. **Host Payout Data Leakage & Unchecked Settlement (`/payouts`):**
   - **What happens:** In `HostPayoutController::index`, if a user is not a host (e.g. an ordinary buyer or tenant), the code fails to apply a host filter and displays all payouts on the platform.
   - **Worse:** The actions to put payouts on hold (`/payouts/{uuid}/hold`) or process payout settlements (`/payouts/{uuid}/process`) have **zero authorization checks**. Any logged-in user can trigger payout settlements or put host payouts on hold.
4. **Development Project Ownership Manipulation (BOLA/IDOR):**
   - **What happens:** When creating a development project in `ProjectController::store`, the submitted `company_id` is not validated against the user's company ownership, and falls back to `DB::table('companies')->value('id')` (the first company in the database).
   - **Consequence:** A developer can attach their new project to another developer's company.

---

### 🟠 High Severity Vulnerabilities

5. **Open Settings & Translation Tampering (`/admin/settings`):**
   - **What happens:** `SettingController` has no role or permission checks.
   - **Consequence:** Any logged-in buyer or tenant can view private system configuration settings and submit modified translations to `POST /admin/settings/translations`, overwriting live website language files.
6. **Marketplace Sellers Can Modify System-Wide Property Types & Categories:**
   - **What happens:** `PropertyTaxonomyAdminController` checks `$user->hasPermission('property.update')`.
   - **Consequence:** Because `property_owner`, `host`, `agent`, and `developer` possess `property.update` to edit their own listings, they inadvertently have full authority to create, update, toggle, and delete national property categories, amenities, and listing types.
7. **Listing Moderation Queue Visible to All Logged-In Users:**
   - **What happens:** While approving or rejecting listings is protected, the queue page (`/admin/moderation`) and detail view (`/admin/moderation/{listing}`) have no permission checks.
   - **Consequence:** Any logged-in user can inspect pending properties, seller fraud signals, and community abuse reports.

---

### 🟡 Medium Severity Vulnerabilities

8. **Lack of Financial Separation of Duties in Payment Reconciliation:**
   - In `PaymentController`, the same administrator who authorizes a customer refund can also confirm the cash disbursement to the bank without a second reviewer (no four-eyes principle).
9. **No Administrative Interface for Staff or Role Assignment:**
   - There is no screen or endpoint in the application to assign roles to staff members. Staff can currently only be assigned through manual database seeders or raw SQL.
10. **Inconsistent Audit Logging Coverage:**
    - While quota overrides and translation edits are logged, normal listing approvals, subscription plan changes, payout holds, and ad campaign approvals produce no audit logs.

---

## 5. What Is Missing

1. **Laravel Policy Layer:**
   - There are currently zero Policy classes in `app/Policies`. Adding standard policies (`ListingPolicy`, `PropertyPolicy`, `DevelopmentProjectPolicy`, `HostPayoutPolicy`, `InvoicePolicy`) will centralize authorization and eliminate repetitive, buggy checks across controllers.
2. **Route Authorization Middleware:**
   - Protected route groups currently only check `middleware('auth')`. There are no `'role:...'` or `'permission:...'` middleware applied to routes.
3. **Company-Scoped User Roles:**
   - All roles are currently global. A user cannot be an "Owner" in Agency A and an "Agent" in Agency B. A `company_user` membership table is needed.
4. **Four-Eyes Separation of Duties:**
   - Two distinct roles (`finance_officer` and `finance_manager`) should be required to authorize and disburse refunds and payouts.

---

## 6. Recommended Action Plan (Next Steps for Project Owner)

We recommend addressing these findings in a staged, risk-prioritized manner:

```
┌─────────────────────────────────────────────────────────────┐
│ STEP 1: Immediate Security Lockdown (Day 1 - 2)             │
│ • Move /crm/dashboard, /host/dashboard, /projects under     │
│   auth middleware.                                          │
│ • Fix scoping so unauthenticated guests receive 401/403.    │
│ • Lock down /payouts so non-hosts see nothing and only      │
│   finance officers can process payments.                    │
│ • Lock down /admin/settings and /admin/moderation queues.   │
│ • Change /admin/taxonomy check from property.update to      │
│   dedicated administrative authority.                       │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│ STEP 2: Standard Laravel Policy Layer (Week 1)              │
│ • Create app/Policies/ (ListingPolicy, HostPayoutPolicy,    │
│   DevelopmentProjectPolicy, InvoicePolicy).                 │
│ • Register route middleware ('role' & 'permission').        │
│ • Enforce policies on all resource endpoints.               │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│ STEP 3: Multi-Company Membership (Week 2)                   │
│ • Create company_user table to support users with           │
│   different roles across multiple agencies or developers.   │
│ • Add active company switcher to header dropdown.           │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│ STEP 4: Admin Staff Console & Complete Audit Logs (Week 3)   │
│ • Build administrative Staff Management screen.             │
│ • Implement separation of duties for financial payouts.     │
│ • Expand audit logging to cover all sensitive events.       │
└─────────────────────────────────────────────────────────────┘
```

