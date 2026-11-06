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

test.describe('Phase 5 Milestone 1 — Unified Ethiopian Payment Engine & Payout Console E2E', () => {

  test('Workflow A — Customer Unified Checkout Screen renders authoritative pricing and payment providers', async ({ page }) => {
    // 1. Authenticate as customer/buyer
    await loginAsUser(page, '+251911234567');

    // 2. Navigate to Checkout with seeded Booking payable
    await page.goto('/payments/checkout?payable_type=App%5CDomains%5CBooking%5CModels%5CBooking&payable_id=1');
    await expect(page).toHaveURL(/payments\/checkout/);

    // 3. Verify Header and Page Title
    await expect(page.locator('h1')).toContainText('Secure Unified Checkout');
    await expect(page.locator('text=Unified Ethiopian payment engine')).toBeVisible();

    // 4. Verify Payment Provider Options
    await expect(page.locator('text=Telebirr Ethio Telecom SuperApp')).toBeVisible();
    await expect(page.locator('text=CBE Birr Commercial Bank')).toBeVisible();
    await expect(page.locator('text=Visa / Mastercard')).toBeVisible();
    await expect(page.locator('text=Bank Deposit / Slip')).toBeVisible();

    // 5. Verify Server-Authoritative Price Breakdown
    await expect(page.locator('text=Payment Summary')).toBeVisible();
    await expect(page.locator('text=ETB').first()).toBeVisible();
    await expect(page.locator('text=45,000.00').first()).toBeVisible();

    // 6. Test Payment Method Selection interactivity
    const telebirrRadio = page.locator('input[type="radio"][value="telebirr"]');
    await expect(telebirrRadio).toBeChecked();

    const cbeBirrRadio = page.locator('input[type="radio"][value="cbe_birr"]');
    await cbeBirrRadio.check();
    await expect(cbeBirrRadio).toBeChecked();

    const cardRadio = page.locator('input[type="radio"][value="card"]');
    await cardRadio.check();
    await expect(cardRadio).toBeChecked();

    const bankRadio = page.locator('input[type="radio"][value="bank_transfer"]');
    await bankRadio.check();
    await expect(bankRadio).toBeChecked();

    await expect(page.getByRole('button', { name: /Proceed with Payment/i })).toBeVisible();
  });

  test('Workflow B — High-Value Property / Land Acquisition Checkout renders contract terms and legal notice', async ({ page }) => {
    await loginAsUser(page, '+251911234567');

    // 1. Navigate to Checkout with seeded PropertyOffer payable
    await page.goto('/payments/checkout?payable_type=App%5CDomains%5COffer%5CModels%5CPropertyOffer&payable_id=1');
    await expect(page).toHaveURL(/payments\/checkout/);

    // 2. Verify Property Acquisition specific banners and terms
    await expect(page.locator('text=Property / Land Acquisition')).toBeVisible();
    await expect(page.locator('text=OFR-E2E-TEST-001')).toBeVisible();
    await expect(page.locator('text=17,000,000.00').first()).toBeVisible(); // Agreed price

    // 3. Verify Civil Registration legal title disclaimer
    await expect(page.locator('text=Civil Registration')).toBeVisible();
    await expect(page.locator('text=Legal title deed transfer remains separate under civil registration')).toBeVisible();

    // 4. Verify Custom Amount Input allows milestone / installment payments
    const customAmountInput = page.locator('input[id="customAmount"]');
    await expect(customAmountInput).toBeVisible();
    await customAmountInput.fill('5000000');
    await expect(customAmountInput).toHaveValue('5000000');
  });

  test('Workflow C — Payment Details & Bank Transfer Deposit Slip Review View', async ({ page }) => {
    await loginAsUser(page, '+251911234567');

    // 1. Navigate to the seeded bank payment details view
    await page.goto('/payments/1');
    await expect(page).toHaveURL(/payments\/1/);

    // 2. Verify Payment Reference & Method Information
    await expect(page.locator('h1')).toContainText('Payment Details');
    await expect(page.locator('text=PAY-ERE-BANK-DEMO01')).toBeVisible();
    await expect(page.locator('text=Bank Transfer (Slip Upload)').first()).toBeVisible();

    // 3. Verify Bank Deposit Instructions & Settlement Guidelines
    await expect(page.locator('text=Commercial Bank of Ethiopia (CBE)')).toBeVisible();
    await expect(page.locator('text=1000234567891')).toBeVisible();

    // 4. Verify Upload Slip Interface & File Input
    await expect(page.locator('text=Bank Deposit Slip Submission')).toBeVisible();
    await expect(page.locator('input[name="slip_file"]')).toBeVisible();
    await expect(page.locator('input[name="bank_name"]')).toBeVisible();
    await expect(page.locator('input[name="transfer_reference"]')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Submit Slip for Officer Verification' })).toBeVisible();
  });

  test('Workflow D — Host & Landlord Payout Console displays financial waterfall and management controls', async ({ page }) => {
    await loginAsUser(page, '+251911234567');

    // 1. Navigate to Host Payout Console
    await page.goto('/payouts');
    await expect(page).toHaveURL(/payouts/);

    // 2. Verify Payout Console Title & Navigation
    await expect(page.locator('h1')).toContainText('Host & Landlord Payout Console');
    await expect(page.locator('text=Auditable financial waterfall')).toBeVisible();

    // 3. Verify Seeded Host Payout and Financial Waterfall Breakdown
    await expect(page.locator('text=PO-ERE-E2E-001')).toBeVisible();
    await expect(page.locator('text=45,000.00').first()).toBeVisible(); // Gross Revenue
    await expect(page.locator('text=41,850.00').first()).toBeVisible(); // Net Payable

    // 4. Verify Verified Destination Account
    await expect(page.locator('text=Commercial Bank of Ethiopia').first()).toBeVisible();
    await expect(page.locator('text=•••••••••6789')).toBeVisible();

    // 5. Verify Action Buttons for Management Controls
    const holdButton = page.locator('button:has-text("Hold")').first();
    await expect(holdButton).toBeVisible();

    const settleButton = page.locator('button:has-text("Settle")').first();
    await expect(settleButton).toBeVisible();
  });

});
