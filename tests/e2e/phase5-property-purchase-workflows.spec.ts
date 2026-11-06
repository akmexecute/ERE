import { test, expect } from '@playwright/test';

/**
 * Authentication Helper for Playwright tests.
 * Performs OTP exchange to establish session for test user.
 */
async function loginAsUser(page: any, phone: string = '+251911234567') {
  const sendRes = await page.request.post('/auth/otp/send', {
    headers: { 'Accept': 'application/json' },
    data: {
      phone: phone,
      purpose: 'login',
    },
  });
  const sendData = await sendRes.json();
  const code = sendData.debug_code || '187680';

  const verifyRes = await page.request.post('/auth/otp/verify', {
    headers: { 'Accept': 'application/json' },
    data: {
      phone: phone,
      code: code,
      purpose: 'login',
    },
  });
  expect(verifyRes.ok()).toBeTruthy();
}

test.describe('Phase 5 Milestone 1 — Property & Land Purchase Hardening Workflows', () => {

  test('Workflow 1 — Property and Land Acquisition Checkout displays authoritative balance and legal title notice', async ({ page }) => {
    await loginAsUser(page, '+251911234567');

    // Navigate to checkout for accepted PropertyOffer
    await page.goto('/payments/checkout?payable_type=App%5CDomains%5COffer%5CModels%5CPropertyOffer&payable_id=1');
    await expect(page).toHaveURL(/payments\/checkout/);

    // Verify Title & Banner
    await expect(page.locator('h1')).toContainText('Secure Unified Checkout');
    await expect(page.locator('text=Property / Land Acquisition').first()).toBeVisible();
    await expect(page.locator('text=OFR-E2E-TEST-001')).toBeVisible();

    // Verify Authoritative Financial Numbers
    await expect(page.locator('text=17,000,000.00').first()).toBeVisible(); // Agreed Price
    await expect(page.locator('text=2,000,000.00').first()).toBeVisible();  // Confirmed Paid
    await expect(page.locator('text=15,000,000.00').first()).toBeVisible(); // Remaining Balance

    // Invariant Notice: Legal title separation per Ethiopian Proclamation
    await expect(page.locator('text=Notice: Financial settlement records funds on the ledger')).toBeVisible();
    await expect(page.locator('text=Legal title deed transfer remains separate under civil registration')).toBeVisible();

    // Verify custom amount input is prefilled with remaining balance
    const customAmountInput = page.locator('input[id="customAmount"]');
    await expect(customAmountInput).toBeVisible();
    await expect(customAmountInput).toHaveValue('15000000.00');

    // Test entering a custom installment amount
    await customAmountInput.fill('5000000');
    await expect(customAmountInput).toHaveValue('5000000');
  });

  test('Workflow 2 — Checkout allows selecting Ethiopian payment providers for property purchases', async ({ page }) => {
    await loginAsUser(page, '+251911234567');

    await page.goto('/payments/checkout?payable_type=App%5CDomains%5COffer%5CModels%5CPropertyOffer&payable_id=1');

    // Verify all four payment methods are available
    await expect(page.locator('text=Telebirr Ethio Telecom SuperApp')).toBeVisible();
    await expect(page.locator('text=CBE Birr Commercial Bank')).toBeVisible();
    await expect(page.locator('text=Visa / Mastercard')).toBeVisible();
    await expect(page.locator('text=Bank Deposit / Slip')).toBeVisible();

    // Switch between payment methods
    const telebirrRadio = page.locator('input[type="radio"][value="telebirr"]');
    await expect(telebirrRadio).toBeChecked();

    const cbeBirrRadio = page.locator('input[type="radio"][value="cbe_birr"]');
    await cbeBirrRadio.check();
    await expect(cbeBirrRadio).toBeChecked();

    const bankRadio = page.locator('input[type="radio"][value="bank_transfer"]');
    await bankRadio.check();
    await expect(bankRadio).toBeChecked();

    // Verify submission button is ready
    const submitBtn = page.getByRole('button', { name: /Proceed with Payment/i });
    await expect(submitBtn).toBeVisible();
  });

  test('Workflow 3 — Buyer views sales transaction contract, verified seller snapshot, and payment history', async ({ page }) => {
    await loginAsUser(page, '+251911234567');

    // Navigate to buyer contract / offer details view
    await page.goto('/buyer/contracts/OFR-E2E-TEST-001');
    await expect(page).toHaveURL(/buyer\/contracts\/OFR-E2E-TEST-001/);

    // Verify Deal Details & Transaction Code
    await expect(page.locator('text=OFR-E2E-TEST-001')).toBeVisible();

    // Verify Seller Identity Snapshot & Verification Evidence
    await expect(page.locator('text=Verified Seller & Developer Identity Record')).toBeVisible();
    await expect(page.locator('text=Immutable transaction-time snapshot preserved for consumer accountability')).toBeVisible();

    // Verify Financial Terms
    await expect(page.locator('text=17,000,000.00').first()).toBeVisible();

    // Verify Payment History Table lists the confirmed payment
    await expect(page.locator('text=Payment Transactions & Ledger Proofs')).toBeVisible();
    await expect(page.locator('text=PAY-ERE-PROP-DEMO01')).toBeVisible();
    await expect(page.locator('text=2,000,000.00').first()).toBeVisible();
  });

  test('Workflow 4 — Payment details view provides slip upload instructions for bank transfers', async ({ page }) => {
    await loginAsUser(page, '+251911234567');

    // Navigate to bank payment details view
    await page.goto('/payments/1');
    await expect(page).toHaveURL(/payments\/1/);

    // Verify Payment Details Header
    await expect(page.locator('h1')).toContainText('Payment Details');
    await expect(page.locator('text=PAY-ERE-BANK-DEMO01')).toBeVisible();

    // Verify CBE deposit instructions
    await expect(page.locator('text=Commercial Bank of Ethiopia (CBE)')).toBeVisible();
    await expect(page.locator('text=1000234567891')).toBeVisible();

    // Verify Slip submission form fields
    await expect(page.locator('text=Bank Deposit Slip Submission')).toBeVisible();
    await expect(page.locator('input[name="slip_file"]')).toBeVisible();
    await expect(page.locator('input[name="bank_name"]')).toBeVisible();
    await expect(page.locator('input[name="transfer_reference"]')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Submit Slip for Officer Verification' })).toBeVisible();
  });

  test('Workflow 5 — Buyer contract view preserves formal agreement terms, Proclamation No. 1357 statutory disclosure, and legal notice', async ({ page }) => {
    await loginAsUser(page, '+251911234567');

    await page.goto('/buyer/contracts/OFR-E2E-TEST-001');
    await expect(page).toHaveURL(/buyer\/contracts\/OFR-E2E-TEST-001/);

    // Verify statutory reference
    await expect(page.locator('text=Statutory Reference: Proclamation No. 1357/2024')).toBeVisible();

    // Verify agreed transaction summary
    await expect(page.locator('text=Agreed Transaction Summary')).toBeVisible();
    await expect(page.locator('text=17,000,000.00').first()).toBeVisible();

    // Verify milestone progress bar shows offer accepted
    await expect(page.locator('text=Statutory Transaction Lifecycle & Legal Conveyance Milestones')).toBeVisible();
    await expect(page.locator('text=Offer Accepted')).toBeVisible();

    // Verify direct checkout action button exists
    const makePaymentBtn = page.getByRole('link', { name: /Make Payment/i });
    await expect(makePaymentBtn).toBeVisible();
  });

  test('Workflow 6 — Overpayment validation guards reject payments exceeding remaining contractual balance', async ({ page }) => {
    await loginAsUser(page, '+251911234567');

    await page.goto('/payments/checkout?payable_type=App%5CDomains%5COffer%5CModels%5CPropertyOffer&payable_id=1');
    await expect(page).toHaveURL(/payments\/checkout/);

    // Enter an amount of 25,000,000 ETB which exceeds the 15,000,000 remaining balance
    const customAmountInput = page.locator('input[id="customAmount"]');
    await customAmountInput.fill('25000000');

    // Click Proceed with Payment
    await page.getByRole('button', { name: /Proceed with Payment/i }).click();

    // Verify error banner is displayed
    await expect(page.locator('text=exceeds the remaining contractual balance')).toBeVisible();
  });

});
