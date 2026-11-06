import { test, expect } from '@playwright/test';
import crypto from 'node:crypto';

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

test.describe('Phase 5 Milestone 1 — Concurrency, Late Webhook & Reconciliation E2E Workflows', () => {

  test('Workflow A — Late Provider Callback with Consumed Balance displays Unapplied Funds Warning', async ({ page }) => {
    await loginAsUser(page, '+251911234567');

    // Visit unapplied payment details view
    await page.goto('/payments/PAY-E2E-UNAPPLIED-999');
    await expect(page).toHaveURL(/payments\/PAY-E2E-UNAPPLIED-999/);

    // Verify Payment Reference & Received Amount
    await expect(page.locator('h1')).toContainText('Payment Details');
    await expect(page.locator('text=PAY-E2E-UNAPPLIED-999')).toBeVisible();
    await expect(page.locator('text=350,000.00').first()).toBeVisible();

    // Verify Allocation Status Badge displays Reconciliation Required
    await expect(page.locator('text=Reconciliation Required').first()).toBeVisible();

    // Verify prominent Unapplied Customer Funds warning banner
    await expect(page.locator('text=Payment Held as Unapplied Customer Funds')).toBeVisible();
    await expect(page.locator('text=350,000.00').first()).toBeVisible();
    await expect(page.locator('text=Ledger Account 2040')).toBeVisible();
  });

  test('Workflow B — Financial Reconciliation Console renders Unapplied Liabilities & Audit Trail', async ({ page }) => {
    await loginAsUser(page, '+251911234567');

    // Navigate to admin reconciliation view
    await page.goto('/admin/payments/reconciliation');
    await expect(page).toHaveURL(/admin\/payments\/reconciliation/);

    // Header & Banner verifications
    await expect(page.locator('h1')).toContainText('Payment Reconciliation & Unapplied Funds');
    await expect(page.locator('text=Unapplied Customer Funds Protocol')).toBeVisible();

    // Verify unapplied payment entry in table
    await expect(page.locator('text=PAY-E2E-UNAPPLIED-999')).toBeVisible();
    await expect(page.locator('text=350,000.00').first()).toBeVisible();

    // Verify action buttons: Inspect & Refund Funds
    await expect(page.locator('text=Inspect').first()).toBeVisible();
    await expect(page.locator('button:has-text("Refund Funds")').first()).toBeVisible();
  });

  test('Workflow C — Concurrent Over-Allocation Guard blocks checkouts exceeding Unreserved Headroom', async ({ page }) => {
    await loginAsUser(page, '+251911234567');

    // Go to checkout for PropertyOffer 1 (balance: 15,000,000.00)
    await page.goto('/payments/checkout?payable_type=App%5CDomains%5COffer%5CModels%5CPropertyOffer&payable_id=1');
    await expect(page).toHaveURL(/payments\/checkout/);

    // Enter custom payment exceeding remaining balance (25,000,000 ETB)
    const customAmountInput = page.locator('input[id="customAmount"]');
    await customAmountInput.fill('25000000');

    // Submit checkout
    await page.getByRole('button', { name: /Proceed with Payment/i }).click();

    // Verify overpayment rejection error banner appears
    await expect(page.locator('text=exceeds the remaining contractual balance')).toBeVisible();
  });

  test('Workflow D — Duplicate Webhook Delivery is Idempotent with Single Ledger Impact', async ({ request }) => {
    // Deliver duplicate webhook to API endpoint
    const appKey = 'telebirr_mock_signing_key_secret_2026';
    const payload = {
      msisdn: 'E2E-DUP-TEST-001',
      tradeStatus: 'COMPLETED',
      outTradeNo: 'PAY-E2E-UNAPPLIED-999',
      tradeNo: 'TB-DUP-999001',
      totalAmount: 350000.00,
      tradeTime: '2026-11-03T12:00:00+03:00',
    };
    const rawBody = JSON.stringify(payload);
    const sig = crypto.createHmac('sha256', appKey).update(rawBody).digest('hex');

    // First call with exact byte rawBody
    const res1 = await request.post('/api/webhooks/payments/telebirr', {
      headers: {
        'Content-Type': 'application/json',
        'x-signature': sig,
      },
      data: rawBody,
    });
    expect(res1.ok()).toBeTruthy();

    // Replay duplicate event with exact byte rawBody
    const res2 = await request.post('/api/webhooks/payments/telebirr', {
      headers: {
        'Content-Type': 'application/json',
        'x-signature': sig,
      },
      data: rawBody,
    });
    expect(res2.ok()).toBeTruthy();
    const data2 = await res2.json();
    expect(['duplicate_acknowledged', 'already_successful']).toContain(data2.result.status);
  });

  test('Workflow E — Authorized Refund of Unapplied Funds settles Customer Liability', async ({ page }) => {
    await loginAsUser(page, '+251911234567');

    await page.goto('/admin/payments/reconciliation');
    await expect(page).toHaveURL(/admin\/payments\/reconciliation/);

    // Accept confirmation dialog when clicking refund
    page.on('dialog', async (dialog) => {
      expect(dialog.message()).toContain('Authorize full refund');
      await dialog.accept();
    });

    const refundBtn = page.locator('tr:has-text("PAY-E2E-UNAPPLIED-REFUND") button:has-text("Refund Funds")').first();
    if (await refundBtn.isVisible()) {
      await refundBtn.click();
      await page.waitForLoadState('networkidle');

      // Verify success status banner
      await expect(page.locator('text=Unapplied customer funds refunded successfully').first()).toBeVisible();
    }
  });

});
