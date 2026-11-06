-- ============================================================================
-- ERE PostgreSQL Native Verification: Financial Immutability Triggers (SRS Section 189)
-- Database Engine: PostgreSQL 16+
-- ============================================================================

\echo '===> 1. Setting Up Test Tables for Rental Financial Immutability...'

DROP TABLE IF EXISTS test_rental_payment_adjustments CASCADE;
DROP TABLE IF EXISTS test_rental_payments CASCADE;

CREATE TABLE test_rental_payments (
    id BIGSERIAL PRIMARY KEY,
    receipt_code VARCHAR(36) NOT NULL UNIQUE,
    amount_etb NUMERIC(18, 2) NOT NULL,
    payment_method VARCHAR(32) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'confirmed',
    recorded_by_user_id BIGINT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE test_rental_payment_adjustments (
    id BIGSERIAL PRIMARY KEY,
    adjustment_code VARCHAR(36) NOT NULL UNIQUE,
    payment_id BIGINT NOT NULL REFERENCES test_rental_payments(id),
    adjustment_type VARCHAR(32) NOT NULL, -- refund, concession, correction, NSF_reversal
    amount_etb NUMERIC(18, 2) NOT NULL,
    reason TEXT NOT NULL,
    authorized_by_user_id BIGINT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

\echo '===> 2. Creating Immutable Payment Triggers (matching migration 2026_04_01_000028)...'

CREATE OR REPLACE FUNCTION fn_check_rental_payment_immutable()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.amount_etb IS DISTINCT FROM NEW.amount_etb THEN
        RAISE EXCEPTION 'Financial immutability violation: Once recorded, payment amount_etb cannot be updated directly (SRS Section 189). Use an append-only adjustment instead.';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_no_update_payment_amount
BEFORE UPDATE ON test_rental_payments
FOR EACH ROW
EXECUTE FUNCTION fn_check_rental_payment_immutable();

CREATE OR REPLACE FUNCTION fn_check_rental_adjustment_append_only()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'UPDATE' THEN
        RAISE EXCEPTION 'Financial immutability violation: Rental adjustments are append-only and cannot be modified (SRS Section 189).';
    ELSIF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'Financial immutability violation: Rental adjustments are append-only audit records and cannot be deleted (SRS Section 189).';
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_no_update_or_delete_adjustments
BEFORE UPDATE OR DELETE ON test_rental_payment_adjustments
FOR EACH ROW
EXECUTE FUNCTION fn_check_rental_adjustment_append_only();

\echo '===> 3. Testing Case 1: Inserting Authoritative Payment...'
INSERT INTO test_rental_payments (receipt_code, amount_etb, payment_method, status, recorded_by_user_id)
VALUES ('RCT-PG-001', 45000.00, 'cbe_birr', 'confirmed', 1);

\echo '===> 4. Testing Case 2: Attempting Direct Update of Payment Amount - MUST BE BLOCKED BY TRIGGER...'
DO $$
BEGIN
    BEGIN
        UPDATE test_rental_payments
        SET amount_etb = 50000.00
        WHERE receipt_code = 'RCT-PG-001';
        
        RAISE EXCEPTION 'TEST FAILED: Payment amount was modified directly!';
    EXCEPTION
        WHEN OTHERS THEN
            IF SQLERRM LIKE '%Financial immutability violation%' THEN
                RAISE NOTICE 'SUCCESS: trg_no_update_payment_amount blocked illegal amount tampering: %', SQLERRM;
            ELSE
                RAISE EXCEPTION 'Unexpected error: %', SQLERRM;
            END IF;
    END;
END $$;

\echo '===> 5. Testing Case 3: Legitimate Metadata Update (status change) - MUST SUCCEED...'
UPDATE test_rental_payments
SET status = 'settled', updated_at = NOW()
WHERE receipt_code = 'RCT-PG-001';

\echo '===> 6. Testing Case 4: Inserting Append-Only Adjustment...'
INSERT INTO test_rental_payment_adjustments (adjustment_code, payment_id, adjustment_type, amount_etb, reason, authorized_by_user_id)
VALUES ('ADJ-PG-001', 1, 'concession', -3000.00, 'Approved moving allowance rebate', 2);

\echo '===> 7. Testing Case 5: Attempting Direct UPDATE of Adjustment - MUST BE BLOCKED...'
DO $$
BEGIN
    BEGIN
        UPDATE test_rental_payment_adjustments
        SET amount_etb = -4000.00
        WHERE adjustment_code = 'ADJ-PG-001';
        
        RAISE EXCEPTION 'TEST FAILED: Adjustment record was modified!';
    EXCEPTION
        WHEN OTHERS THEN
            IF SQLERRM LIKE '%Rental adjustments are append-only%' THEN
                RAISE NOTICE 'SUCCESS: trg_no_update_or_delete_adjustments blocked adjustment update: %', SQLERRM;
            ELSE
                RAISE EXCEPTION 'Unexpected error: %', SQLERRM;
            END IF;
    END;
END $$;

\echo '===> 8. Testing Case 6: Attempting Direct DELETE of Adjustment - MUST BE BLOCKED...'
DO $$
BEGIN
    BEGIN
        DELETE FROM test_rental_payment_adjustments
        WHERE adjustment_code = 'ADJ-PG-001';
        
        RAISE EXCEPTION 'TEST FAILED: Adjustment record was deleted!';
    EXCEPTION
        WHEN OTHERS THEN
            IF SQLERRM LIKE '%Rental adjustments are append-only%' THEN
                RAISE NOTICE 'SUCCESS: trg_no_update_or_delete_adjustments blocked adjustment deletion: %', SQLERRM;
            ELSE
                RAISE EXCEPTION 'Unexpected error: %', SQLERRM;
            END IF;
    END;
END $$;

\echo '===> Financial Triggers Verification Complete.'

