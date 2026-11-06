# PostgreSQL Native Verification Package (Phase 4 Milestone 1 & 2)

## 1. Executive Summary

This verification package provides the authoritative, executable SQL scripts and execution instructions for validating native PostgreSQL features of the **ERE — Ethiopian Real Estate Marketplace** application.

The ERE production architecture targets **PostgreSQL 16 with PostGIS 3.4 and `btree_gist`**.
This package contains exact SQL statements, trigger tests, concurrency scenarios, and regression tests for Phase 4 Milestone 1 (Modules 25–27: Rentals, Leases, Inspections, Rent Schedules, and Financial Immutability) and Milestone 2.

---

## 2. Environment Prerequisites

- **Database Engine**: PostgreSQL 16.x (or PostgreSQL 18.x with version caveats documented)
- **Required Extensions**:
  - `uuid-ossp` (UUID generation)
  - `postgis` (Geographic coordinates & spatial indexing)
  - `btree_gist` (GiST indexing on scalar columns: `bigint`, `daterange`, `tstzrange` for exclusion constraints)
- **Database User Permissions**:
  - `CREATE EXTENSION`
  - `CREATE TABLE`, `CREATE INDEX`, `CREATE TRIGGER`, `CREATE FUNCTION`
  - `SELECT`, `INSERT`, `UPDATE`, `DELETE`

---

## 3. Setup Instructions for Isolated Verification

To run verification without affecting any existing databases:

```bash
# 1. Connect to PostgreSQL server as superuser (e.g. postgres)
psql -U postgres -h 127.0.0.1 -p 5432

# 2. Create dedicated isolated test database
CREATE DATABASE ere_phase4_pg_verification WITH ENCODING 'UTF8';

# 3. Connect to the test database
\c ere_phase4_pg_verification

# 4. Enable required extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "btree_gist";
CREATE EXTENSION IF NOT EXISTS "postgis";
```

Alternatively, configure Laravel's `.env.testing`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ere_phase4_pg_verification
DB_USERNAME=postgres
DB_PASSWORD=your_password_here
```

And run:
```bash
php artisan migrate --database=pgsql
```

---

## 4. Verification Execution Scripts

Execute the SQL scripts in sequence:

```bash
# Step 1: Schema, btree_gist extension and GiST exclusion constraints
psql -U postgres -d ere_phase4_pg_verification -f docs/verification/postgresql/phase4_schema_verification.sql

# Step 2: Financial triggers and immutability enforcement
psql -U postgres -d ere_phase4_pg_verification -f docs/verification/postgresql/phase4_financial_triggers.sql

# Step 3: Concurrency, row locking, and simultaneous conflict detection
psql -U postgres -d ere_phase4_pg_verification -f docs/verification/postgresql/phase4_concurrency_verification.sql

# Step 4: Seller accountability, ownership authority & dual Fayda verification
psql -U postgres -d ere_phase4_pg_verification -f docs/verification/postgresql/phase4_seller_accountability_and_ownership_verification.sql
```

---

## 5. Script Inventory

| File | Purpose | Key Checks |
|---|---|---|
| `phase4_schema_verification.sql` | Verifies PostgreSQL extensions, `daterange`, and GiST exclusion constraints | `btree_gist`, `leases_property_overlap_excl`, `leases_unit_overlap_excl`, JSONB |
| `phase4_financial_triggers.sql` | Tests triggers preventing unauthorized updates to payments & adjustments | `trg_no_update_payment_amount`, `trg_no_update_or_delete_adjustments` |
| `phase4_concurrency_verification.sql` | Tests cross-session concurrent transactions and race conditions | `SELECT ... FOR UPDATE`, simultaneous overlapping leases, whole-property vs unit conflicts |
| `phase4_seller_accountability_and_ownership_verification.sql` | Tests ownership authority constraints, dual Fayda methods, and immutable JSONB transaction snapshots | `chk_test_verification_method`, `chk_test_property_ownership_integrity`, JSONB snapshot index, support ticket links |
| `phase4_test_plan.md` | Test cases, expected results, blocker diagnosis, and verification matrices | Detailed test cases and PostgreSQL 16 vs 18 notes |

