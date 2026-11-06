# ERE RBAC Recommended Architecture & Gap Analysis
**Project:** Ethiopian Real Estate Marketplace (ERE)  
**Date:** November 6, 2026  
**Document Purpose:** Architectural Comparison, Gap Analysis & Target Design Specification  
**Mode:** Architecture Recommendation (No Direct Implementation)

---

## 1. Executive Summary & Problem Statement

The ERE application possesses a strong relational foundation for authentication, custom RBAC tables, and enterprise portfolio scoping (`MultiTenantOrganizationGuard`). However, authorization enforcement across the codebase is currently fragmented:
- Zero Laravel Policy classes exist.
- Zero Laravel Gates exist.
- Routes lack role and permission middleware.
- Authorization checks are performed ad-hoc inside controllers and action classes using inconsistent methods (`hasRole`, `hasAnyRole`, `hasPermission`, `isStaff`, or custom closures).
- Several high-risk endpoints (CRM Dashboard, Host Dashboard, Host Payouts, Setting Controller) inadvertently bypass authentication or lack object-level ownership checks.
- Company membership and organizational roles are currently global rather than company-scoped.

This document presents a comprehensive gap analysis between the existing system and a modern, secure, enterprise-grade RBAC architecture tailored to Ethiopian real estate operations.

---

## 2. Comparison of Existing vs. Recommended Role Groups

### 2.1 Role Groups Comparison Matrix

| Recommended Role | Category | Current Status in ERE | Gap Analysis & Recommendation |
|---|---|---|---|
| `super_admin` | Platform | **Exists (Active)** | Retain. Hardcoded bypass in `User::hasPermission()` should be documented and audited. Needs protection against status changes by lower admins. |
| `platform_admin` | Platform | **Exists (Active)** | Retain. Serves as platform technical operator possessing all operational permissions except super-admin emergency controls. |
| `moderator` | Operations | **Exists (Active)** | Retain. In database seeder, lacks `property.approve`. Needs explicit `property.approve` permission and route-level protection on moderation queue. |
| `property_admin` | Operations | **Exists (Active)** | Retain. Represents national property operations manager. Needs separation from individual listing owners. |
| `verification_officer` | Operations | **Exists (Active)** | Retain. Dedicated to Fayda/National ID and document verification. Needs dedicated UI and controller endpoints connected to `ReviewVerificationRequestAction`. |
| `finance_officer` | Operations | **Exists (Active)** | Retain. Currently locked out of `PaymentController` reconciliation and refunds. Must be granted access to reconciliation and payout reviews. |
| `finance_manager` | Operations | **MISSING** | **Recommend Creating.** Needed for separation of duties (approving high-value refunds and payouts initiated by finance officers). |
| `customer_support` | Operations | **Exists (Active)** | Retain. Handles ticketing, messaging disputes, and read-only property/booking inquiries. |
| `marketing_manager` | Operations | **MISSING** | **Recommend Creating.** Currently, ad approvals and promotions require `platform_admin`. A dedicated marketing role prevents granting full platform admin rights to marketing staff. |
| `audit_officer` | Operations | **MISSING** | **Recommend Creating.** Independent compliance officer with read-only access to audit logs, security watchdogs, and transaction journals. |
| `gis_analyst` | Operations | **MISSING** | **Recommend Creating.** Dedicated role for geographic boundaries, location hierarchy updates, and PostGIS heatmap recalculation (`gis.view_advanced`, `location.manage`). |
| `property_owner` | Marketplace | **Exists (Active)** | Retain. Direct seller/landlord role. Owns individual properties and listings. |
| `host` | Marketplace | **Exists (Active)** | Retain. Short-stay host role. Manages house manuals, calendars, cleaning tasks, and stay profiles. |
| `agent` | Marketplace | **Exists (Active)** | Retain for independent brokers/agents. In corporate settings, should map to company-scoped agency roles. |
| `buyer` | Marketplace | **Exists (Active)** | Retain. Baseline public consumer role for offers, purchases, and booking stays. |
| `tenant` | Marketplace | **Exists (Active)** | Retain. Public consumer role for long-term rentals, lease signing, and maintenance requests. |
| `agency_owner` | Organization | **MISSING (Simulated)** | **Recommend Formalizing.** Currently simulated by `Company::where('owner_user_id', $user->id)`. Needs company-scoped permissions. |
| `agency_manager` | Organization | **MISSING (Simulated)** | **Recommend Formalizing.** Currently simulated by `CompanyBranch::where('manager_user_id', $user->id)`. Needs branch-scoped access. |
| `agency_agent` | Organization | **MISSING (Simulated)** | **Recommend Formalizing.** Currently simulated by `AgentProfile::where('company_id', ...)`. Needs company-restricted listing and lead access. |
| `developer` | Organization | **Exists (Active)** | Retain as company-level developer role for enterprise projects and unit matrices. |
| `developer_manager`| Organization | **MISSING** | **Recommend Creating.** Project-level construction manager for updating milestone progress and logs without corporate billing authority. |
| `property_manager` | Organization | **MISSING (Simulated)** | **Recommend Formalizing.** Manages buildings, leases, and tenant rent collections under an agency mandate. |

---

## 3. Recommended Permission Architecture & Naming Conventions

### 3.1 Naming Convention Standard
Permissions should strictly adhere to the dot-notation standard: `<domain>.<resource>.<action>`.

Examples:
- `listings.view`, `listings.create`, `listings.update`, `listings.delete`, `listings.approve`, `listings.override_quota`
- `verifications.identity.review`, `verifications.identity.approve`, `verifications.company.review`
- `finance.payouts.view`, `finance.payouts.hold`, `finance.payouts.process`
- `finance.refunds.authorize`, `finance.refunds.disburse`
- `system.translations.manage`, `system.settings.view`, `system.settings.manage`

### 3.2 Permission Gap Analysis

| Domain | Current Implementation Status | Missing / Inadequately Protected Permissions | Risk & Required Action |
|---|---|---|---|
| **Moderation** | Partial | `listings.moderate.view_queue` | Currently `/admin/moderation` has no permission check. Any logged-in user can view unapproved listings and fraud signals. |
| **Taxonomy** | Defective | `taxonomy.manage` | Currently uses `property.update`. This allows all sellers, agents, and hosts to modify global property categories and amenities! Must replace with `taxonomy.manage`. |
| **Settings** | Missing | `settings.view`, `settings.manage`, `translations.manage` | Currently `/admin/settings` has zero permission checks. Must add explicit administrative permissions. |
| **Finance** | Inconsistent | `payouts.view`, `payouts.hold`, `payouts.release`, `payouts.process` | Currently `/payouts` actions have zero authorization checks. Any logged in user can settle payouts! |
| **Finance (SoD)**| Missing | `refunds.confirm_disbursement` (distinct from `refunds.authorize`) | Same admin can currently authorize and disburse refunds without a second reviewer. |
| **CRM** | Missing | `crm.view_all_branches`, `crm.leads.export` | Unauthenticated guests currently receive unscoped leads if accessing `/crm/dashboard`. |
| **Identity** | Partial | `verification.agent.verify`, `verification.company.verify` | `ReviewVerificationRequestAction` has no HTTP controller endpoint. |
| **Subscriptions**| Redundant / Dead | `subscriptions.entitlements.bypass` (ID 51) | Seeded in database but assigned to zero roles and never evaluated. |

---

## 4. Proposed Target Role-Permission Matrix

The target matrix enforces strict least-privilege access, segregation of duties, and operational boundaries:

| Permission Category | Permission Slug | Super Admin | Platform Admin | Moderator | Property Admin | Finance Officer | Finance Manager | Verification Officer | Marketing Manager | Audit Officer | Marketplace Users | Company Owners / Managers |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| **Listings & Moderation** | `listings.view_public` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| | `listings.create_own` | Yes | Yes | No | No | No | No | No | No | No | Yes (Sellers) | Yes (Agents) |
| | `listings.update_own` | Yes | Yes | No | No | No | No | No | No | No | Yes (Owner) | Yes (Assigned) |
| | `listings.view_queue` | Yes | Yes | Yes | Yes | No | No | No | No | Yes | No | No |
| | `listings.approve` | Yes | Yes | Yes | Yes | No | No | No | No | No | No | No |
| | `listings.reject` | Yes | Yes | Yes | Yes | No | No | No | No | No | No | No |
| | `listings.override_quota`| Yes | Yes | No | No | No | No | No | No | No | No | No |
| **Identity & Verification**| `verification.review` | Yes | Yes | No | No | No | No | Yes | No | Yes | No | No |
| | `verification.approve` | Yes | Yes | No | No | No | No | Yes | No | No | No | No |
| | `verification.agent` | Yes | Yes | No | No | No | No | Yes | No | No | No | No |
| **Payments & Accounting** | `payments.view_all` | Yes | Yes | No | No | Yes | Yes | No | No | Yes | No | No |
| | `reconciliation.access` | Yes | Yes | No | No | Yes | Yes | No | No | Yes | No | No |
| | `refunds.authorize` | Yes | Yes | No | No | Yes | Yes | No | No | No | No | No |
| | `refunds.disburse` | Yes | Yes | No | No | No* | Yes* | No | No | No | No | No |
| | `payouts.view` | Yes | Yes | No | No | Yes | Yes | No | No | Yes | Own (Hosts) | No |
| | `payouts.hold` | Yes | Yes | No | No | Yes | Yes | No | No | No | No | No |
| | `payouts.release` | Yes | Yes | No | No | No* | Yes* | No | No | No | No | No |
| | `payouts.process` | Yes | Yes | No | No | No* | Yes* | No | No | No | No | No |
| **Tax & Invoicing** | `tax.manage` | Yes | Yes | No | No | Yes | Yes | No | No | No | No | No |
| | `invoices.create` | Yes | Yes | No | Yes | Yes | Yes | No | No | No | No | Yes (Company) |
| | `invoices.void` | Yes | Yes | No | No | No* | Yes* | No | No | No | No | Yes (Owner) |
| **System & Security** | `security.watchdog.view`| Yes | Yes | No | No | No | No | No | No | Yes | No | No |
| | `security.users.status` | Yes | Yes | No | No | No | No | No | No | No | No | No |
| | `roles.assign` | Yes | No | No | No | No | No | No | No | No | No | No |
| | `settings.manage` | Yes | Yes | No | No | No | No | No | No | No | No | No |
| | `translations.manage` | Yes | Yes | No | No | No | No | No | No | No | No | No |
| | `taxonomy.manage` | Yes | Yes | No | Yes | No | No | No | No | No | No | No |
| **Advertising & Promo** | `advertising.manage` | Yes | Yes | No | No | No | No | No | Yes | No | No | No |
| | `advertising.approve` | Yes | Yes | No | No | No | No | No | Yes | No | No | No |
| | `promotions.manage` | Yes | Yes | No | No | No | No | No | Yes | No | No | No |
| **Audit & Compliance** | `audit_logs.view` | Yes | Yes | No | No | No | No | No | No | Yes | No | No |

*\*Separation of Duties Rule:\* A single user cannot both authorize and disburse a refund or hold and settle a payout.

---

## 5. Company-Level Multi-Tenant Architecture Design

### 5.1 The Missing `company_user` Membership Model
Currently, users are linked to companies via single owner or profile relationships. To support enterprise agencies, developers, and property managers, ERE should introduce a formal company membership pivot table:

```
Table: company_user
-------------------------------------------------------------------
id                      bigint unsigned (PK)
company_id              bigint unsigned (FK -> companies.id)
user_id                 bigint unsigned (FK -> users.id)
company_role            enum('owner', 'manager', 'agent', 'accountant')
branch_id               bigint unsigned nullable (FK -> company_branches.id)
is_active               boolean default true
joined_at               timestamp
created_at, updated_at  timestamp
-------------------------------------------------------------------
Unique: [company_id, user_id]
```

### 5.2 Contextual Company Authorization Flow
1. **Active Company in Session:**
   - Store `current_company_id` in the authenticated session.
   - When an agent belongs to Agency A and Agency B, allow them to toggle active workspace via a header dropdown.
2. **Context-Aware Permission Resolution:**
   ```php
   // Target helper method on User model
   public function hasCompanyRole(string $role, ?int $companyId = null): bool
   {
       $companyId = $companyId ?? session('active_company_id');
       return DB::table('company_user')
           ->where('user_id', $this->id)
           ->where('company_id', $companyId)
           ->where('company_role', $role)
           ->where('is_active', true)
           ->exists();
   }
   ```
3. **Revocation Guarantee:**
   - Deactivating a record in `company_user` immediately revokes all access to that company's leads, properties, invoices, and branch matrices without affecting the user's personal buyer or tenant marketplace identity.

---

## 6. Resource Ownership Architecture (Laravel Policies)

To eliminate IDOR vulnerabilities and ad-hoc controller queries, ERE should implement standard Laravel Policy classes registered in `app/Policies/`:

### 6.1 Required Policy Classes
1. **`ListingPolicy` (`app/Policies/ListingPolicy.php`):**
   - `view(User $user, Listing $listing)`: Public if published; otherwise only author, property owner, assigned company agent, or staff with `listings.view_queue`.
   - `update(User $user, Listing $listing)`: Only publisher, property owner, or authorized company agent. Staff cannot edit listing contents directly.
   - `delete(User $user, Listing $listing)`: Only owner or company head-office admin before publication; archived after publication.
   - `moderate(User $user, Listing $listing)`: Requires `listings.approve` or `listings.reject`.
2. **`PropertyPolicy` (`app/Policies/PropertyPolicy.php`):**
   - Validates that `existing_property_id` in listing creation belongs strictly to `$user->id` or `$user->current_company_id`.
3. **`DevelopmentProjectPolicy` (`app/Policies/DevelopmentProjectPolicy.php`):**
   - Strictly enforces that when creating or managing a project, `company_id` must match a developer company where the user is an owner or verified developer manager.
4. **`HostPayoutPolicy` (`app/Policies/HostPayoutPolicy.php`):**
   - `view(User $user, HostPayout $payout)`: Permitted if user owns the host account OR user possesses `payouts.view`. Non-hosts cannot view payouts.
   - `hold(User $user, HostPayout $payout)`: Requires `payouts.hold`.
   - `process(User $user, HostPayout $payout)`: Requires `payouts.process` AND ensures `$user->id !== $payout->held_by_user_id`.
5. **`CrmLeadPolicy` (`app/Policies/CrmLeadPolicy.php`):**
   - Enforces authentication. Scopes leads strictly to assigned agent, branch manager, or agency head office.

---

## 7. Financial Separation of Duties (Four-Eyes Principle)

To safeguard funds under Ethiopian financial regulations and double-entry integrity:

```
[Customer Payment Exceeds Balance]
               │
               ▼
   [Account 2040 Unapplied Funds]
               │
               ▼
┌─────────────────────────────────────────────────┐
│ Stage 1: Authorization                          │
│ Performed by: Finance Officer (Initiator)       │
│ Checks: listings.refund.authorize               │
│ Action: Debits 2040, Credits 2050 (Payable)     │
│ Writes: AuditLog (stage: authorized)            │
└─────────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────┐
│ Stage 2: Cash Disbursement Confirmation         │
│ Performed by: Finance Manager (Reviewer)        │
│ Checks: listings.refund.disburse                │
│ Constraint: reviewer_id !== initiator_id       │
│ Action: Debits 2050, Credits 1010 (Cash/Bank)   │
│ Writes: AuditLog (stage: disbursement_confirmed)│
└─────────────────────────────────────────────────┘
```

---

## 8. Staged Implementation Roadmap (Recommended Order)

The project owner should implement the RBAC architectural improvements in four distinct phases:

### Phase 1: Immediate Vulnerability Mitigation (Critical Security Fixes)
- Wrap `/crm/dashboard`, `/host/dashboard`, and `/projects` routes in `auth` middleware.
- In `CrmDashboardController`, ensure unauthenticated visitors cannot reach the lead query.
- In `HostDashboardController`, remove fallback to `Host::first()`; redirect non-hosts to host onboarding.
- In `HostPayoutController::index`, ensure non-hosts with no host profile are rejected with 403 or empty array.
- In `HostPayoutController` (`hold`, `release`, `process`), enforce `super_admin`, `platform_admin`, or `finance_officer` role gating.
- In `SettingController`, add strict `platform_admin` / `super_admin` authorization.
- In `PropertyTaxonomyAdminController`, replace `property.update` check with a dedicated administrative check (`property_admin` or `super_admin`).
- In `ListingModerationController::index` and `show`, add `canModerate()` check to prevent public user access.

### Phase 2: Policy Foundation & Route Middleware
- Create `app/Policies` directory and implement `ListingPolicy`, `PropertyPolicy`, `DevelopmentProjectPolicy`, `HostPayoutPolicy`, `InvoicePolicy`.
- Register Route Authorization Middleware aliases in `bootstrap/app.php`:
  `'role' => \App\Http\Middleware\EnsureUserHasRole::class`  
  `'permission' => \App\Http\Middleware\EnsureUserHasPermission::class`
- Apply route middleware across all admin and operational route groups.

### Phase 3: Company Membership & Contextual Roles
- Create `company_user` migration and seeders.
- Upgrade `MultiTenantOrganizationGuard` to evaluate `company_user` with active session company context.
- Introduce company-scoped roles (`agency_owner`, `agency_manager`, `agency_agent`, `developer_manager`).

### Phase 4: Administrative UI & Audit Log Completion
- Build administrative Staff Management UI for assigning and revoking staff roles.
- Implement database-level trigger or strict model enforcement preventing updates/deletions on `audit_logs`.
- Expand `AuditService::log()` coverage to include all role assignments, plan modifications, ad approvals, and payout transitions.

