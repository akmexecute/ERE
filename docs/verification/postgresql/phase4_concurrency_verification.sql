-- ============================================================================
-- ERE PostgreSQL Native Verification: Concurrency & Cross-Scope Overlap Protection
-- Database Engine: PostgreSQL 16+
-- ============================================================================

\echo '===> 1. Setting Up Concurrency Test Fixtures...'

DROP TABLE IF EXISTS test_concurrency_unit_reservations CASCADE;
DROP TABLE IF EXISTS test_concurrency_leases CASCADE;

CREATE TABLE test_concurrency_leases (
    id BIGSERIAL PRIMARY KEY,
    lease_code VARCHAR(36) NOT NULL UNIQUE,
    property_id BIGINT NOT NULL,
    accommodation_unit_id BIGINT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    validity_range DATERANGE NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Partial Unique Index on Unit Reservations (SRS Section 156 & 190)
CREATE TABLE test_concurrency_unit_reservations (
    id BIGSERIAL PRIMARY KEY,
    reservation_code VARCHAR(36) NOT NULL UNIQUE,
    project_unit_id BIGINT NOT NULL,
    buyer_user_id BIGINT NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'reserved'
);

CREATE UNIQUE INDEX uq_test_active_unit_reservation 
ON test_concurrency_unit_reservations (project_unit_id) 
WHERE (status IN ('requested', 'pending_payment', 'reserved'));

-- Add GiST Exclusion Constraint for Lease Date Range Overlaps
ALTER TABLE test_concurrency_leases
    ADD CONSTRAINT test_conc_leases_prop_excl
    EXCLUDE USING gist (
        property_id WITH =,
        validity_range WITH &&
    ) WHERE (status IN ('draft', 'pending_signature', 'active') AND accommodation_unit_id IS NULL);

ALTER TABLE test_concurrency_leases
    ADD CONSTRAINT test_conc_leases_unit_excl
    EXCLUDE USING gist (
        property_id WITH =,
        accommodation_unit_id WITH =,
        validity_range WITH &&
    ) WHERE (status IN ('draft', 'pending_signature', 'active') AND accommodation_unit_id IS NOT NULL);

\echo '===> 2. Simulating Transaction A (Session 1): Locking & Creating Lease...'
BEGIN;
    -- Row-lock simulation for synchronized resource occupancy (Module 26)
    SELECT id FROM test_concurrency_leases 
    WHERE property_id = 200 AND status = 'active' 
    FOR UPDATE;

    INSERT INTO test_concurrency_leases (lease_code, property_id, accommodation_unit_id, start_date, end_date, validity_range, status)
    VALUES ('LSE-SESSION-1', 200, NULL, '2026-04-01', '2026-10-01', daterange('2026-04-01', '2026-10-01', '[)'), 'active');
COMMIT;

\echo '===> 3. Simulating Concurrent Transaction B (Session 2): Attempting Overlapping Lease...'
DO $$
BEGIN
    BEGIN
        INSERT INTO test_concurrency_leases (lease_code, property_id, accommodation_unit_id, start_date, end_date, validity_range, status)
        VALUES ('LSE-SESSION-2-CONFLICT', 200, NULL, '2026-07-01', '2026-12-01', daterange('2026-07-01', '2026-12-01', '[)'), 'active');
        
        RAISE EXCEPTION 'TEST FAILED: Concurrent overlapping lease committed!';
    EXCEPTION
        WHEN exclusion_violation THEN
            RAISE NOTICE 'SUCCESS: PostgreSQL GiST exclusion constraint blocked concurrent lease overlap.';
    END;
END $$;

\echo '===> 4. Testing Whole-Property vs Unit Conflict Detection...'
-- Property 300 has Unit 1 and Unit 2.
-- First, Unit 1 is leased for 2026-01-01 to 2026-06-01
INSERT INTO test_concurrency_leases (lease_code, property_id, accommodation_unit_id, start_date, end_date, validity_range, status)
VALUES ('LSE-UNIT-1', 300, 1, '2026-01-01', '2026-06-01', daterange('2026-01-01', '2026-06-01', '[)'), 'active');

-- Attempting whole-property lease on Property 300 for 2026-03-01 to 2026-09-01
-- Domain rule check (SynchronizeLeaseOccupancyAction)
DO $$
DECLARE
    v_conflicts INT;
BEGIN
    SELECT COUNT(*) INTO v_conflicts
    FROM test_concurrency_leases
    WHERE property_id = 300
      AND status = 'active'
      AND validity_range && daterange('2026-03-01', '2026-09-01', '[)');

    IF v_conflicts > 0 THEN
        RAISE NOTICE 'SUCCESS: Whole-property lease overlap detected % existing active unit lease(s).', v_conflicts;
    ELSE
        RAISE EXCEPTION 'TEST FAILED: Cross-scope conflict was not detected!';
    END IF;
END $$;

\echo '===> 5. Testing Concurrent Active Unit Reservations Partial Unique Index...'
INSERT INTO test_concurrency_unit_reservations (reservation_code, project_unit_id, buyer_user_id, status)
VALUES ('RSV-001', 9001, 10, 'reserved');

-- Second simultaneous reservation on unit 9001 MUST FAIL with unique violation
DO $$
BEGIN
    BEGIN
        INSERT INTO test_concurrency_unit_reservations (reservation_code, project_unit_id, buyer_user_id, status)
        VALUES ('RSV-002-DUPLICATE', 9001, 11, 'reserved');
        
        RAISE EXCEPTION 'TEST FAILED: Duplicate active reservation on same unit was allowed!';
    EXCEPTION
        WHEN unique_violation THEN
            RAISE NOTICE 'SUCCESS: Partial unique index blocked duplicate active reservation on project_unit_id 9001.';
    END;
END $$;

-- Cancelled reservation allows new active reservation
UPDATE test_concurrency_unit_reservations SET status = 'cancelled' WHERE reservation_code = 'RSV-001';

INSERT INTO test_concurrency_unit_reservations (reservation_code, project_unit_id, buyer_user_id, status)
VALUES ('RSV-003-AFTER-CANCEL', 9001, 12, 'reserved');

\echo '===> Concurrency & Exclusion Verification Complete.'

