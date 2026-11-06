# PostgreSQL Native Verification Test Plan & Environment Report

## 1. Environment Audit & Blocker Analysis

### 1.1 Installed Host Environment
- **Operating System**: Windows 11 Enterprise (amd64)
- **Host PostgreSQL Service**: `postgresql-x64-18` (Status: `Running`)
- **Service Binaries**: `C:\Program Files\PostgreSQL\18\bin` (`psql.exe`, `pg_isready.exe`, `pg_ctl.exe`)
- **Reported Engine Version**: PostgreSQL 18.0 (Development / Preview Edition)
- **Port**: 5432 (State: `accepting connections`)
- **PHP CLI Driver**: PHP 8.4.25 (Laravel Herd CLI)
  - `PDO` enabled: Yes
  - `pdo_pgsql` enabled: Yes
  - `pgsql` enabled: Yes

### 1.2 Access Diagnosis & Blocker Documentation
- **Connection Attempt**: `pgsql:host=127.0.0.1;port=5432;dbname=postgres`
- **Result**: `PDOException: SQLSTATE[08006] [7] connection to server at "127.0.0.1", port 5432 failed: FATAL: password authentication failed for user "postgres"`.
- **Finding**: The Windows PostgreSQL 18 service was installed with a custom administrative password not provided in the workspace `.env` or system environment variables. In strict accordance with security guidelines ("Never defeat or bypass an access control, credential requirement..."), access credentials were not probed or bypassed.
- **Production Engine Discrepancy**: The target production architecture for ERE is **PostgreSQL 16 with PostGIS 3.4**. The locally installed Windows service is PostgreSQL 18.0. To guarantee verifiable compliance without false assertions, complete executable SQL verification scripts and instructions have been packaged into `docs/verification/postgresql/`.

---

## 2. Test Verification Matrix

| Test ID | Feature Under Test | Expected PostgreSQL Behavior | Canonical Migration Reference | SQL Verification Script |
|---|---|---|---|---|
| **PG-01** | `btree_gist` extension | Enables GiST indexing on scalar columns (`bigint`, `integer`) alongside ranges | `2026_04_01_000027` | `phase4_schema_verification.sql` (Line 8) |
| **PG-02** | GiST Exclusion (`leases_property_overlap_excl`) | Blocks overlapping date ranges on the same property for whole-property leases | `2026_04_01_000027` | `phase4_schema_verification.sql` (Line 31) |
| **PG-03** | Half-Open `[)` Date Ranges | Upper bound is exclusive: adjacent leases `[A, B)` and `[B, C)` succeed without error | `2026_10_31_020000` | `phase4_schema_verification.sql` (Line 48) |
| **PG-04** | GiST Exclusion (`leases_unit_overlap_excl`) | Blocks overlapping date ranges on the same accommodation unit | `2026_04_01_000027` | `phase4_schema_verification.sql` (Line 72) |
| **PG-05** | JSONB Indexing & Containment | GIN index on `metadata_json` allows efficient `@>` queries | `2026_04_01_000027` | `phase4_schema_verification.sql` (Line 88) |
| **PG-06** | Financial Trigger (`trg_no_update_payment_amount`) | Direct SQL `UPDATE amount_etb` raises exception per SRS Section 189 | `2026_04_01_000028` | `phase4_financial_triggers.sql` (Line 47) |
| **PG-07** | Financial Trigger (`trg_no_update_or_delete_adjustments`) | Direct SQL `UPDATE` or `DELETE` on adjustments raises immutability exception | `2026_04_01_000028` | `phase4_financial_triggers.sql` (Line 67) |
| **PG-08** | Concurrent Overlap Protection | Simultaneous transaction attempting overlap fails with `exclusion_violation` (23P01) | `2026_04_01_000027` | `phase4_concurrency_verification.sql` (Line 42) |
| **PG-09** | Whole-Property vs Unit Conflict | Active unit lease prevents conflicting whole-property lease | `SynchronizeLeaseOccupancyAction` | `phase4_concurrency_verification.sql` (Line 55) |
| **PG-10** | Partial Unique Index on Unit Holds | Exactly one active hold (`requested`, `pending_payment`, `reserved`) per unit | `2026_03_01_000024` | `phase4_concurrency_verification.sql` (Line 76) |

---

## 3. Execution Verification Protocol

When valid credentials are provided for the local PostgreSQL service or target PostgreSQL 16 environment:
```bash
# 1. Run all three verification scripts
psql -U postgres -d ere_phase4_pg_verification -f docs/verification/postgresql/phase4_schema_verification.sql
psql -U postgres -d ere_phase4_pg_verification -f docs/verification/postgresql/phase4_financial_triggers.sql
psql -U postgres -d ere_phase4_pg_verification -f docs/verification/postgresql/phase4_concurrency_verification.sql
```

All 10 test cases are designed to output `SUCCESS: ...` upon passing and fail fast with an informative `RAISE EXCEPTION` if any constraint or trigger behavior regresses.

