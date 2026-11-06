import { test, expect } from '@playwright/test';

/**
 * Authentication Helper for Playwright tests.
 * Performs OTP exchange to establish session for a buyer.
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

test.describe('Seller Identity Accountability & Buyer Protection E2E', () => {

  test('Buyer can view contract with verified seller record and masked National ID', async ({ page }) => {
    // 1. Authenticate as the contract buyer
    await loginAsUser(page, '+251911234567');

    // 2. Navigate to Dashboard Purchases tab
    await page.goto('/dashboard?tab=purchases');
    await expect(page).toHaveURL(/dashboard/);

    // 3. Navigate directly to the Sales Contract
    await page.goto('/buyer/contracts/CNT-2026-005940');
    await expect(page.locator('h1')).toContainText('CNT-2026-005940');

    // 4. Verify Verified Seller Identity Card
    const sellerCard = page.locator('text=Verified Seller & Developer Identity Record');
    await expect(sellerCard).toBeVisible();

    // 5. Verify Masked National ID format (FAN-****-XXXX or similar, no full unmasked ID)
    const maskedIdElement = page.locator('text=FAN-');
    await expect(maskedIdElement).toBeVisible();
    const idText = await maskedIdElement.textContent();
    expect(idText).toMatch(/FAN-\*{2,4}-\d{4}/);

    // 6. Verify Statutory Compliance Reference
    await expect(page.locator('text=Proclamation No. 1357/2024').first()).toBeVisible();

    // 7. Verify Developer Company Details
    await expect(page.locator('text=Seller / Developer Entity')).toBeVisible();
    await expect(page.locator('text=Business Registration & Tax')).toBeVisible();
    await expect(page.locator('text=TIN:')).toBeVisible();

    // 8. Capture visual proof screenshot
    await page.screenshot({
      path: 'tests/e2e/screenshots/verified-seller-identity-contract.png',
      fullPage: true,
    });
  });

  test('Buyer can file a transaction dispute linked to the seller identity snapshot', async ({ page }) => {
    // 1. Authenticate as the buyer
    await loginAsUser(page, '+251911234567');

    // 2. Navigate to contract
    await page.goto('/buyer/contracts/CNT-2026-005940');

    // 3. Scroll to dispute form
    const subjectInput = page.locator('input[name="subject"]');
    await expect(subjectInput).toBeVisible();

    // 4. Fill in dispute details
    await subjectInput.fill('Construction milestone delay inquiry and discrepancy report');
    await page.selectOption('select[name="category"]', 'property');
    await page.locator('textarea[name="description"]').fill(
      'The foundation stage milestone was scheduled for completion last month. The verified company on record has not provided the required structural engineering inspection report. Requesting formal platform dispute escalation under Proclamation No. 1357/2024.'
    );

    // 5. Submit dispute
    await page.locator('button:has-text("Submit Formal Dispute")').click();

    // 6. Verify successful redirection and confirmation banner
    await expect(page).toHaveURL(/buyer\/contracts\/CNT-2026-005940/);
    const successBanner = page.locator('text=lodged successfully').first();
    await expect(successBanner).toBeVisible();

    // 7. Capture visual proof screenshot
    await page.screenshot({
      path: 'tests/e2e/screenshots/buyer-transaction-dispute-filed.png',
      fullPage: true,
    });
  });
});
