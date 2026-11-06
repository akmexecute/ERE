-- ============================================================================
-- ERE PostgreSQL Native Verification: Seller Accountability & Dual Fayda Verification
-- Database Engine: PostgreSQL 16+ / 18+
-- Verifies: Ownership Authority, Dual Fayda Methods, Unified Transaction Snapshots
-- ============================================================================

\echo '===> 1. Verifying Enhanced Verification & Ownership Schema Columns...'

-- 1.1 Clean temporary test schema
DROP TABLE IF EXISTS test_pg_verification_records CASCADE;
DROP TABLE IF EXISTS test_pg_properties CASCADE;
DROP TABLE IF EXISTS test_pg_property_offers CASCADE;
DROP TABLE IF EXISTS test_pg_support_tickets CASCADE;

-- 1.2 Verifications Table with Dual-Mode Method Support
CREATE TABLE test_pg_verification_records (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid(),
    verifiable_type VARCHAR(255) NOT NULL,
    verifiable_id BIGINT NOT NULL,
    verification_method VARCHAR(40) NOT NULL DEFAULT 'manual_review',
    verification_reference VARCHAR(64) NULL,
    provider_reference VARCHAR(128) NULL,
    requested_level INT NOT NULL DEFAULT 1,
    approved_level INT NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL DEFAULT 'submitted',
    evidence_type VARCHAR(64) NULL,
    authority_scope VARCHAR(64) NULL,
    requires_reverification BOOLEAN NOT NULL DEFAULT FALSE,
    reverification_reason TEXT NULL,
    reviewed_at TIMESTAMPTZ NULL,
    reviewed_by_user_id BIGINT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Constraint: Valid verification methods
ALTER TABLE test_pg_verification_records
    ADD CONSTRAINT chk_test_verification_method
    CHECK (verification_method IN ('manual_review', 'official_fayda_api', 'commercial_registry', 'power_of_attorney_court'));

-- 1.3 Properties Table with Separate Ownership & Selling Authority Columns
CREATE TABLE test_pg_properties (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid(),
    property_code VARCHAR(40) NOT NULL UNIQUE,
    owner_id BIGINT NOT NULL,
    ownership_status VARCHAR(32) NOT NULL DEFAULT 'unverified',
    ownership_evidence_type VARCHAR(64) NULL,
    ownership_verified_at TIMESTAMPTZ NULL,
    ownership_verified_by_user_id BIGINT NULL,
    authorized_seller_user_id BIGINT NULL,
    authority_valid_until DATE NULL,
    ownership_legal_disclaimer TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Constraint: Verified ownership must have evidence type and timestamp
ALTER TABLE test_pg_properties
    ADD CONSTRAINT chk_test_property_ownership_integrity
    CHECK (
        ownership_status != 'authority_verified' 
        OR (ownership_evidence_type IS NOT NULL AND ownership_verified_at IS NOT NULL)
    );

-- 1.4 Property Offers Table with Immutable Seller Identity Snapshot
CREATE TABLE test_pg_property_offers (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID NOT NULL DEFAULT gen_random_uuid(),
    offer_code VARCHAR(40) NOT NULL UNIQUE,
    property_id BIGINT NOT NULL REFERENCES test_pg_properties(id),
    buyer_user_id BIGINT NOT NULL,
    seller_user_id BIGINT NOT NULL,
    agreed_price_etb NUMERIC(18, 2) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'submitted',
    seller_identity_snapshot_json JSONB NULL,
    accepted_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 1.5 Support Tickets Table Linked to Both Contracts and Offers
CREATE TABLE test_pg_support_tickets (
    id BIGSERIAL PRIMARY KEY,
    ticket_code VARCHAR(40) NOT NULL UNIQUE,
    user_id BIGINT NOT NULL,
    offer_id BIGINT NULL REFERENCES test_pg_property_offers(id),
    subject VARCHAR(255) NOT NULL,
    category VARCHAR(64) NOT NULL,
    priority VARCHAR(32) NOT NULL DEFAULT 'medium',
    seller_snapshot_json JSONB NULL,
    evidence_json JSONB NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

\echo '===> 2. Testing Test Case 1: Manual Review Verification Insertion...'
INSERT INTO test_pg_verification_records (
    verifiable_type, verifiable_id, verification_method, verification_reference,
    requested_level, approved_level, status, evidence_type, authority_scope, reviewed_at
) VALUES (
    'App\Domains\User\Models\User', 1001, 'manual_review', 'VER-MAN-2026-001001',
    2, 2, 'approved', 'national_id_card', 'individual_owner', NOW()
);

\echo '===> 3. Testing Test Case 2: Official Fayda API Verification Insertion...'
INSERT INTO test_pg_verification_records (
    verifiable_type, verifiable_id, verification_method, verification_reference,
    provider_reference, requested_level, approved_level, status, evidence_type, reviewed_at
) VALUES (
    'App\Domains\User\Models\User', 1002, 'official_fayda_api', 'VER-FAYDA-2026-000042',
    'FAYDA-API-TXN-889921', 1, 1, 'approved', 'national_id_fayda', NOW()
);

\echo '===> 4. Testing Test Case 3: Property Ownership Verification Check...'
INSERT INTO test_pg_properties (
    property_code, owner_id, ownership_status, ownership_evidence_type,
    ownership_verified_at, ownership_verified_by_user_id, ownership_legal_disclaimer
) VALUES (
    'PROP-TEST-001', 1001, 'authority_verified', 'title_deed',
    NOW(), 9999, 'Internal platform document review only; not official government land registry certification.'
);

\echo '===> 5. Testing Test Case 4: Invalid Ownership Check (Verified without evidence - MUST FAIL)...'
DO $$
BEGIN
    BEGIN
        INSERT INTO test_pg_properties (
            property_code, owner_id, ownership_status, ownership_evidence_type, ownership_verified_at
        ) VALUES (
            'PROP-TEST-FAIL-001', 1001, 'authority_verified', NULL, NULL
        );
        RAISE EXCEPTION 'TEST FAILED: Property ownership verified without evidence was improperly allowed!';
    EXCEPTION
        WHEN check_violation THEN
            RAISE NOTICE 'SUCCESS: Check constraint correctly rejected authority_verified without evidence (SQLSTATE: 23514).';
    END;
END $$;

\echo '===> 6. Testing Test Case 5: Accepted Offer Storing Immutable JSONB Snapshot...'
INSERT INTO test_pg_property_offers (
    offer_code, property_id, buyer_user_id, seller_user_id,
    agreed_price_etb, status, seller_identity_snapshot_json, accepted_at
) VALUES (
    'OFR-TEST-2026-001', 1, 2001, 1001,
    18500000.00, 'accepted',
    '{
        "snapshot_version": "v1.0-unified",
        "seller_name": "Verified Seller Aster",
        "masked_national_id": "FAN-****-5544",
        "masked_phone": "+251911***44",
        "seller_type": "individual_owner",
        "verification_method": "manual_review",
        "verification_status": "internally_verified_by_ere",
        "verification_reference": "VER-MAN-2026-001001",
        "property_ownership": {
            "ownership_status": "authority_verified",
            "ownership_evidence_type": "title_deed",
            "ownership_legal_disclaimer": "Internal platform document review only"
        },
        "transaction": {
            "transaction_reference": "OFR-TEST-2026-001",
            "agreed_price_etb": 18500000.0
        }
    }'::jsonb,
    NOW()
);

\echo '===> 7. Testing Test Case 6: JSONB Snapshot Path Querying & Indexing...'
CREATE INDEX idx_test_offers_seller_id_masked 
ON test_pg_property_offers ((seller_identity_snapshot_json->>'masked_national_id'));

DO $$
DECLARE
    found_seller TEXT;
    found_masked_id TEXT;
BEGIN
    SELECT 
        seller_identity_snapshot_json->>'seller_name',
        seller_identity_snapshot_json->>'masked_national_id'
    INTO found_seller, found_masked_id
    FROM test_pg_property_offers
    WHERE offer_code = 'OFR-TEST-2026-001';

    IF found_seller != 'Verified Seller Aster' OR found_masked_id != 'FAN-****-5544' THEN
        RAISE EXCEPTION 'TEST FAILED: JSONB snapshot data mismatch: % / %', found_seller, found_masked_id;
    END IF;

    RAISE NOTICE 'SUCCESS: JSONB snapshot queried and indexed successfully (% / %)', found_seller, found_masked_id;
END $$;

\echo '===> 8. Testing Test Case 7: Linking Support Dispute Ticket to Offer and Snapshot...'
INSERT INTO test_pg_support_tickets (
    ticket_code, user_id, offer_id, subject, category, priority, seller_snapshot_json, evidence_json
) VALUES (
    'TCK-TEST-DISPUTE-01', 2001, 1,
    'Seller delay in subcity title deed registration', 'dispute', 'urgent',
    (SELECT seller_identity_snapshot_json FROM test_pg_property_offers WHERE id = 1),
    '{"offer_code": "OFR-TEST-2026-001", "claim_amount_etb": 18500000.00}'::jsonb
);

DO $$
DECLARE
    ticket_count INT;
BEGIN
    SELECT COUNT(*) INTO ticket_count FROM test_pg_support_tickets WHERE offer_id = 1;
    IF ticket_count != 1 THEN
        RAISE EXCEPTION 'TEST FAILED: Support ticket was not correctly linked to offer!';
    END IF;
    RAISE NOTICE 'SUCCESS: Support ticket correctly linked to offer with seller snapshot.';
END $$;

\echo '===> All PostgreSQL Native Seller Accountability & Verification Tests Completed Successfully!'

