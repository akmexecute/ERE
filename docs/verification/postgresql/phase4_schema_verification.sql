-- ============================================================================
-- ERE PostgreSQL Native Verification: Schema, Extensions & GiST Exclusion Constraints
-- Database Engine: PostgreSQL 16+ (btree_gist, daterange, exclusion constraints)
-- ============================================================================

\echo '===> 1. Verifying Required Extensions...'
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "btree_gist";

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'btree_gist') THEN
        RAISE EXCEPTION 'CRITICAL: btree_gist extension is NOT available!';
    END IF;
    RAISE NOTICE 'SUCCESS: btree_gist extension is active and available.';
END $$;

-- Verify btree_gist supports GiST on integer, bigint, and daterange
\echo '===> 2. Verifying GiST Exclusion Constraints with Half-Open [) daterange...'

-- Clean temporary test schema
DROP TABLE IF EXISTS test_pg_verification_leases CASCADE;

CREATE TABLE test_pg_verification_leases (
    id BIGSERIAL PRIMARY KEY,
    lease_code VARCHAR(36) NOT NULL UNIQUE,
    property_id BIGINT NOT NULL,
    accommodation_unit_id BIGINT NULL,
    validity_range DATERANGE NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    metadata_json JSONB NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Constraint A: Whole-property level exclusion (where unit IS NULL)
ALTER TABLE test_pg_verification_leases
    ADD CONSTRAINT test_leases_property_overlap_excl
    EXCLUDE USING gist (
        property_id WITH =,
        validity_range WITH &&
    ) WHERE (status IN ('draft', 'pending_signature', 'active') AND accommodation_unit_id IS NULL);

-- Constraint B: Unit-level exclusion (where unit IS NOT NULL)
ALTER TABLE test_pg_verification_leases
    ADD CONSTRAINT test_leases_unit_overlap_excl
    EXCLUDE USING gist (
        property_id WITH =,
        accommodation_unit_id WITH =,
        validity_range WITH &&
    ) WHERE (status IN ('draft', 'pending_signature', 'active') AND accommodation_unit_id IS NOT NULL);

\echo '===> 3. Testing Test Case 1: First Lease Insertion [2026-01-01, 2026-06-01)...'
INSERT INTO test_pg_verification_leases (lease_code, property_id, accommodation_unit_id, validity_range, status, metadata_json)
VALUES ('LSE-TEST-001', 101, NULL, daterange('2026-01-01', '2026-06-01', '[)'), 'active', '{"rent_etb": 35000, "currency": "ETB"}');

\echo '===> 4. Testing Test Case 2: Adjacent Lease Insertion [2026-06-01, 2026-12-01) - MUST SUCCEED...'
-- With half-open ranges [), the upper bound 2026-06-01 is exclusive, so next starting 2026-06-01 must not conflict
INSERT INTO test_pg_verification_leases (lease_code, property_id, accommodation_unit_id, validity_range, status, metadata_json)
VALUES ('LSE-TEST-002', 101, NULL, daterange('2026-06-01', '2026-12-01', '[)'), 'active', '{"rent_etb": 38000, "currency": "ETB"}');

\echo '===> 5. Testing Test Case 3: Overlapping Lease [2026-03-01, 2026-08-01) - MUST FAIL WITH EXCLUSION VIOLATION...'
DO $$
BEGIN
    BEGIN
        INSERT INTO test_pg_verification_leases (lease_code, property_id, accommodation_unit_id, validity_range, status)
        VALUES ('LSE-TEST-003-CONFLICT', 101, NULL, daterange('2026-03-01', '2026-08-01', '[)'), 'active');
        
        RAISE EXCEPTION 'TEST FAILED: Overlapping lease was allowed without exclusion violation!';
    EXCEPTION
        WHEN exclusion_violation THEN
            RAISE NOTICE 'SUCCESS: Exclusion constraint correctly caught overlapping lease (SQLSTATE: 23P01).';
    END;
END $$;

\echo '===> 6. Testing Test Case 4: Non-conflicting different property [2026-03-01, 2026-08-01) - MUST SUCCEED...'
INSERT INTO test_pg_verification_leases (lease_code, property_id, accommodation_unit_id, validity_range, status)
VALUES ('LSE-TEST-004-DIFF-PROP', 102, NULL, daterange('2026-03-01', '2026-08-01', '[)'), 'active');

\echo '===> 7. Testing Test Case 5: Unit-level non-conflicting units on same property...'
INSERT INTO test_pg_verification_leases (lease_code, property_id, accommodation_unit_id, validity_range, status)
VALUES ('LSE-TEST-UNIT-A', 103, 501, daterange('2026-01-01', '2026-12-31', '[)'), 'active');

INSERT INTO test_pg_verification_leases (lease_code, property_id, accommodation_unit_id, validity_range, status)
VALUES ('LSE-TEST-UNIT-B', 103, 502, daterange('2026-01-01', '2026-12-31', '[)'), 'active');

\echo '===> 8. Testing Test Case 6: Unit-level conflict on Unit 501 - MUST FAIL...'
DO $$
BEGIN
    BEGIN
        INSERT INTO test_pg_verification_leases (lease_code, property_id, accommodation_unit_id, validity_range, status)
        VALUES ('LSE-TEST-UNIT-A-CONFLICT', 103, 501, daterange('2026-06-01', '2026-12-31', '[)'), 'active');
        
        RAISE EXCEPTION 'TEST FAILED: Overlapping unit lease was allowed!';
    EXCEPTION
        WHEN exclusion_violation THEN
            RAISE NOTICE 'SUCCESS: Unit-level exclusion constraint correctly caught collision on Unit 501.';
    END;
END $$;

\echo '===> 9. Testing JSONB Indexing & Querying...'
CREATE INDEX idx_test_leases_metadata ON test_pg_verification_leases USING gin (metadata_json);

SELECT lease_code, validity_range, metadata_json->>'rent_etb' as rent_etb
FROM test_pg_verification_leases
WHERE metadata_json @> '{"currency": "ETB"}';

\echo '===> Schema Verification Complete.'

