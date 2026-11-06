# Subscription Entitlement & Moderation Override Security Report
## Rigorous Authorization Hardening, Dedicated Permissions & Immutable Audit Trail

**Project:** Ethiopian Real Estate Marketplace (ERE)  
**Scope:** Phase 5 Milestone 2 — Modules 33 & 34 Security & Authorization Hardening  
**Date:** November 5, 2026  
**Status:** Fully Hardened · Zero Permissive Leakage · 100% Tests Passing

---

## 1. Executive Summary

This security report details the comprehensive audit, architectural separation, and hardening applied to the Super Administrator and staff subscription entitlement engine, administrative moderation quota overrides, and immutable audit logging within the Ethiopian Real Estate Marketplace (ERE).

### Key Hardening Outcomes:
1. **Separated Administrative Authority from Subscription Entitlements:**
   - Completely eradicated blanket `isStaff()` checks from the subscription entitlement pipeline.
   - Staff roles (`moderator`, `property_admin`, `verification_officer`, `customer_support`, `finance_officer`) now evaluate standard subscription quotas for their own listings and properties like any ordinary seller.
   - Restricted the platform-testing exemption strictly to `super_admin` for core platform seed and demo records in `validateListingPublishing()`, removing unrestricted subscription benefits across photos, tours, GIS, and other monetization features.
2. **Dedicated Quota Override Permission (`listings.moderate.override_subscription_quota`):**
   - Disentangled general listing approval authority (`property.approve` / `canModerate()`) from quota override authority.
   - Seeded and assigned `listings.moderate.override_subscription_quota` exclusively to `platform_admin` and `super_admin`. Ordinary moderators cannot override listing quotas.
3. **Mandatory Administrative Override Reason & Validation:**
   - Enforced server-side validation requiring a documented administrative reason (5 to 500 characters) whenever an override is requested.
   - Missing, whitespace-only, or invalid override reasons are rejected and recorded as rejected override attempts.
4. **Immutable Audit Trail Integration (`AuditService` & `AuditLog`):**
   - Every quota override is captured in the append-only `audit_logs` table (`listing_subscription_quota_override`) with full pre- and post-state snapshots (owner ID, plan code, quota limit, active count, moderator ID, role, and override reason).
   - Unauthorized override attempts by ordinary moderators are intercepted, rejected with HTTP 403 Forbidden, and audited (`listing_subscription_quota_override_denied`).
   - Audit logs remain strictly append-only; attempts to delete an audit log throw a `LogicException`.
5. **Contextual UI Protection in Moderation Workbench:**
   - The override checkbox and reason input only render for users with the dedicated `listings.moderate.override_subscription_quota` permission. Ordinary moderators cannot see or activate the override controls.
6. **Strictly Scoped Override Lifecycle:**
   - A quota override applies solely to the specific listing approved. It does not alter the owner's subscription plan, does not modify their feature snapshot, and does not grant future publication privileges.

---

## 2. Initial Security Findings Audit

Prior to hardening, the implementation was audited across all affected classes and entrypoints. Findings were classified according to platform security standards:

| Component / Method | Previous Implementation State | Security Classification | Vulnerability & Architectural Impact |
|---|---|---|---|
| `SubscriptionEntitlementService::hasElevatedPrivileges()` | Checked `$user->hasRole('super_admin') \|\| $user->isStaff()` | **Overly Permissive** | Granted all 7 staff roles (`customer_support`, `moderator`, `finance_officer`, etc.) unlimited subscription benefits on personal listings, bypassing monetization. |
| `SubscriptionEntitlementService::can()` & `getLimit()` | Returned `true` / `PHP_INT_MAX` for all staff | **Overly Permissive** | Disabled image limits, virtual tour restrictions, and GIS entitlement gates for any staff member. |
| `ModerateListingAction::execute()` | Accepted `bool $bypassSubscriptionQuota` without dedicated permission check | **Missing Authorization** | Any moderator with basic approval rights could bypass seller listing quotas by manually passing `bypass_quota=1`. |
| `ListingModerationController::approve()` | Checked `$request->boolean('bypass_quota')` without checking dedicated permission | **Missing Authorization** | Allowed form-level override without validating whether the moderator was granted quota waiver privileges. |
| Moderation Override Reason | Optional or unvalidated | **Defective** | Administrators could bypass quotas silently without documenting any justification. |
| Override Audit Logging | Not integrated with `AuditService` | **Missing Audit Coverage** | Quota waivers left no trace in `audit_logs`, violating regulatory transparency under Ethiopian Proclamation No. 1357/2024. |
| Moderation Blade Workbench (`show.blade.php`) | Checkbox rendered unconditionally for all users | **Missing Authorization in UI** | Ordinary moderators were presented with administrative override controls they lacked authority to use. |
| `ModerateListingAction` Identity Resolution | Correctly resolved `$listing->publishedByUser` | **Secure & Working** | Listing owner was evaluated, avoiding confusion with moderator identity. |
| Listing Scoping | Did not touch `$seller->userSubscriptions` | **Secure & Working** | Override did not permanently upgrade the seller's plan. |

---

## 3. Detailed Architectural & Code Changes

### 3.1 Separation of Subscriptions from Staff Status
In `app/Domains/Subscription/Services/SubscriptionEntitlementService.php`:
- Completely removed `hasElevatedPrivileges()` and the broad `isStaff()` check.
- Added `hasPlatformTestingExemption(User $user)` strictly restricted to `super_admin` for core platform seed records.
- Removed blanket bypasses from `can()`, `getEntitlement()`, `getLimit()`, `isUnlimited()`, and `validatePropertyImages()`.
- Staff members listing properties as owners now evaluate against their assigned plan (e.g. Free plan: 2 listings, 8 photos).

### 3.2 Granular RBAC Permission Seeding
In `database/seeders/RbacRolePermissionSeeder.php`:
- Added permission:
  ```php
  ['slug' => 'listings.moderate.override_subscription_quota', 'module' => 'moderation']
  ```
- Mapped exclusively to `platform_admin` (and inherited by `super_admin`).
- Ordinary `moderator`, `property_admin`, and operational staff roles are excluded from this permission.

### 3.3 Hardened `ModerateListingAction.php`
- Added dedicated authority check:
  ```php
  public function canOverrideSubscriptionQuota(User $user): bool
  {
      if (! $this->canModerate($user) || $user->status !== 'active') {
          return false;
      }
      return method_exists($user, 'hasPermission') && $user->hasPermission('listings.moderate.override_subscription_quota');
  }
  ```
- In `execute()`:
  - If `$bypassSubscriptionQuota === true`, verifies `$this->canOverrideSubscriptionQuota($moderator)` or throws `AuthorizationException`.
  - Enforces `$overrideReason` is non-empty, string between 5 and 500 characters or throws `InvalidArgumentException`.
  - Captures seller quota snapshot (`owner_plan_code`, `owner_listing_limit`, `owner_active_listings_count`, `quota_exceeded`) and logs event `'listing_subscription_quota_override'` via `AuditService`.
  - Records `ModerationAction` with `reason_code = 'quota_override'` and notes.

### 3.4 Controller Security in `ListingModerationController.php`
- When `bypass_quota` is requested:
  - Verifies `$action->canOverrideSubscriptionQuota($request->user())`.
  - If unauthorized: logs `'listing_subscription_quota_override_denied'` and terminates with **HTTP 403 Forbidden**.
  - If reason is missing or $< 5$ characters: logs `'listing_subscription_quota_override_rejected'` and returns with validation error.
- Gracefully handles business domain exceptions (`DomainException`, `InvalidArgumentException`) without emitting HTTP 500 crashes.

### 3.5 Contextual Moderation Workbench (`resources/views/admin/moderation/show.blade.php`)
- Evaluates `$canOverride = auth()->check() && app(ModerateListingAction::class)->canOverrideSubscriptionQuota(auth()->user())`.
- If `$canOverride` is false: Override controls are completely omitted from the DOM.
- If `$canOverride` is true: Displays an interactive Alpine.js checkbox (`x-model="bypassQuota"`) which conditionally reveals the mandatory `override_reason` textarea and explanatory compliance notice.

---

## 4. Authorization Matrix

The table below reflects the actual, tested authorization behavior enforced across all platform roles:

| Platform Role | Can Moderate Listings (`canModerate`) | Can Approve Listing Within Quota | Can Override Exceeded Quota (`canOverrideSubscriptionQuota`) | Receives Automatic Unlimited Subscription Benefits |
|---|---|---|---|---|
| **Ordinary Seller (`property_owner`)** | ❌ No | ❌ No | ❌ No | ❌ No (Plan Quota Enforced) |
| **Real Estate Agent (`agent`)** | ❌ No | ❌ No | ❌ No | ❌ No (Plan Quota Enforced) |
| **Customer Support (`customer_support`)** | ❌ No | ❌ No | ❌ No | ❌ No (Plan Quota Enforced) |
| **Verification Officer (`verification_officer`)** | ❌ No | ❌ No | ❌ No | ❌ No (Plan Quota Enforced) |
| **Finance Officer (`finance_officer`)** | ❌ No | ❌ No | ❌ No | ❌ No (Plan Quota Enforced) |
| **Ordinary Moderator (`moderator`)** | ✅ Yes | ✅ Yes | ❌ No (HTTP 403 Forbidden) | ❌ No (Plan Quota Enforced) |
| **Property Admin (`property_admin`)** | ✅ Yes | ✅ Yes | ❌ No (HTTP 403 Forbidden) | ❌ No (Plan Quota Enforced) |
| **Platform Admin (`platform_admin`)** | ✅ Yes | ✅ Yes | ✅ Yes (Mandatory Reason Required) | ❌ No (Plan Quota Enforced) |
| **Super Administrator (`super_admin`)** | ✅ Yes | ✅ Yes | ✅ Yes (Mandatory Reason Required) | Narrow platform-testing seed exemption only |

---

## 5. Audit Trail Evidence

### 5.1 Successful Quota Override Event
Recorded in `audit_logs` table upon authorized override approval:
```json
{
  "action": "listing_subscription_quota_override",
  "auditable_type": "App\\Domains\\Listing\\Models\\Listing",
  "auditable_id": "33",
  "user_id": 1,
  "reason": "Government institutional demonstration showcase exception",
  "old_values": {
    "listing_id": 33,
    "listing_uuid": "63c2b784-169b-420a-92d3-25c994d3906c",
    "listing_code": "LST-2026-0000024",
    "previous_status": "pending_review",
    "listing_owner_id": 10,
    "owner_plan_code": "free",
    "owner_listing_limit": 2,
    "owner_active_listings_count": 2,
    "quota_exceeded": true
  },
  "new_values": {
    "decision": "approve",
    "new_status": "published",
    "override_applied": true,
    "override_reason": "Government institutional demonstration showcase exception",
    "acting_administrator_id": 1,
    "acting_administrator_role": "super_admin",
    "acting_administrator_permissions": ["listings.moderate.override_subscription_quota"]
  },
  "ip_address": "127.0.0.1",
  "user_agent": "Mozilla/5.0 ...",
  "created_at": "2026-11-05 14:08:44"
}
```

### 5.2 Denied Unauthorized Override Attempt Event
Recorded when an unauthorized user or ordinary moderator attempts to pass `bypass_quota=1`:
```json
{
  "action": "listing_subscription_quota_override_denied",
  "auditable_type": "App\\Domains\\Listing\\Models\\Listing",
  "auditable_id": "13",
  "user_id": 5,
  "reason": "Moderator lacks listings.moderate.override_subscription_quota permission",
  "old_values": {
    "listing_id": 13,
    "listing_code": "LST-SEC-13-8472",
    "status": "pending_review",
    "attempted_action": "bypass_quota"
  },
  "new_values": {
    "attempted_by_user_id": 5,
    "attempted_by_roles": ["moderator"],
    "denial_reason": "missing_override_permission"
  },
  "created_at": "2026-11-05 14:08:43"
}
```

### 5.3 Rejected Malformed Override Attempt Event
Recorded when an authorized administrator requests an override with an empty or insufficient reason ($< 5$ chars):
```json
{
  "action": "listing_subscription_quota_override_rejected",
  "auditable_type": "App\\Domains\\Listing\\Models\\Listing",
  "auditable_id": "23",
  "user_id": 7,
  "reason": "Administrative override reason is required (5-500 characters)",
  "old_values": {
    "listing_id": 23,
    "listing_code": "LST-PEND-23-4412",
    "attempted_action": "bypass_quota"
  },
  "new_values": {
    "attempted_by_user_id": 7,
    "denial_reason": "missing_or_invalid_override_reason",
    "submitted_reason": ""
  },
  "created_at": "2026-11-05 14:08:43"
}
```

---

## 6. Automated Verification Evidence

### 6.1 Dedicated Security Test Suite (`tests/Feature/ModerationQuotaOverrideSecurityTest.php`)
```
Command: php artisan test tests/Feature/ModerationQuotaOverrideSecurityTest.php
Result: 7 passed, 0 failed, 0 skipped (36 assertions, 2.59s)
```
- `test_unauthorized_user_without_moderation_authority_cannot_approve`: **PASSED** (HTTP 403 Forbidden).
- `test_ordinary_moderator_can_approve_when_seller_is_within_quota`: **PASSED** (Approved and published).
- `test_ordinary_moderator_cannot_override_quota_and_attempt_is_audited`: **PASSED** (HTTP 403 Forbidden, `listing_subscription_quota_override_denied` persisted).
- `test_authorized_platform_admin_requires_valid_override_reason`: **PASSED** (Validation redirect, `listing_subscription_quota_override_rejected` persisted).
- `test_authorized_admin_can_approve_with_override_and_audit_log_is_recorded`: **PASSED** (Listing published, `listing_subscription_quota_override` persisted).
- `test_override_is_strictly_scoped_and_does_not_upgrade_seller_or_grant_future_privileges`: **PASSED** (Seller remains on Free plan, 2-listing limit preserved, future listing 44 blocked).
- `test_audit_logs_are_append_only_and_cannot_be_deleted`: **PASSED** (`LogicException` thrown on delete attempt).

### 6.2 Subscription Entitlement Test Suite (`tests/Feature/SubscriptionEntitlementAndQuotaTest.php`)
```
Command: php artisan test tests/Feature/SubscriptionEntitlementAndQuotaTest.php
Result: 8 passed, 0 failed, 0 skipped (30 assertions, 2.63s)
```
- Includes verification that staff members (`moderator`) do NOT receive platform testing exemptions or unlimited benefits.

### 6.3 Core Feature Regression Suites
```
Command: php artisan test tests/Feature/VerificationAndModerationTest.php tests/Feature/PropertyUploadAndDevelopmentCreationTest.php tests/Feature/SubscriptionFeaturesAndPlansTest.php tests/Feature/PromotionsAndAdvertisingTest.php tests/Feature/VirtualTourSecurityAndWorkflowTest.php tests/Feature/PostGisNeighborhoodHeatmapTest.php
Result: 32 passed, 0 failed, 0 skipped (183 assertions, 9.85s)
```

### 6.4 Playwright Browser Automation Suites
```
1. tests/e2e/property-upload-and-development-creation.spec.ts & tests/e2e/phase5-milestone2-modules33-34-workflows.spec.ts:
   - 7 / 7 PASSED (25.1s)
2. tests/e2e/phase5-milestone2-live-destinations.spec.ts:
   - 8 / 8 PASSED (20.6s)
```
**Total Playwright Tests:** 15 / 15 Passed (100%)

---

## 7. Remaining Considerations & Configuration Notes

1. **Role Delegation Policy:**
   Only users holding the `platform_admin` or `super_admin` role possess `listings.moderate.override_subscription_quota`. If an organization wishes to grant a senior chief moderator this privilege, it must be granted explicitly via RBAC assignment rather than by editing role definitions.
2. **PostgreSQL Concurrency Tests:**
   The SQLite automated feature and browser suites execute with full isolation. Native PostgreSQL concurrent locking tests remain documented in `docs/verification/postgresql/` and are scheduled for staging deployment.

---

## 8. Final Assessment

The administrative moderation override, subscription entitlement boundaries, and audit logging subsystems are **fully hardened and secure**. 

- No broad `isStaff()` bypasses exist.
- Overrides require independent server-side permission checks and mandatory documented reasons.
- The override scope is strictly confined to individual approval operations.
- Every authorized override and unauthorized bypass attempt is immutably recorded in the platform audit ledger.

