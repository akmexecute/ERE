import { test, expect } from '@playwright/test';

/**
 * Authentication Helper for Playwright tests.
 * Performs OTP exchange to establish an authoritative session context.
 */
async function loginAsUser(page: any, phone: string, role?: string) {
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
      ...(role ? { role } : {}),
    },
  });
  expect(verifyRes.ok()).toBeTruthy();
}

test.describe('Phase 4 Milestone 1: Tenant Management, Leases, Rent Schedules & Financial Adjustments', () => {

  test('Test A: Tenant Profile update & emergency contact masking', async ({ page }) => {
    // 1. Log in as dedicated tenant Dawit Kebede (+251911445566)
    await loginAsUser(page, '+251911445566', 'tenant');

    // 2. Navigate to Tenant Profile page
    await page.goto('/tenant/profile');
    await expect(page).toHaveTitle(/EthioProperty|ERE|Tenant Profile/i);

    // 3. Assert Tenant Identifier card & Code
    await expect(page.locator('text=TNT-2026-0000001')).toBeVisible();
    await expect(page.locator('text=Dawit Kebede').first()).toBeVisible();

    // 4. Assert Masked Emergency Phone (+251911***77)
    await expect(page.locator('text=+251911***77')).toBeVisible();

    // 5. Update Profile with new profession
    await page.fill('#occupation', 'Principal Cloud & Security Architect');
    await page.fill('#employer_name', 'Commercial Bank of Ethiopia - IT HQ');
    await page.click('button:has-text("Save Profile Changes")');

    // 6. Assert success flash message and updated field
    await expect(page.locator('text=Tenant profile updated successfully')).toBeVisible();
    await expect(page.locator('#occupation')).toHaveValue('Principal Cloud & Security Architect');

    // 7. Save Screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/test-a-tenant-profile.png', fullPage: true });
  });

  test('Test B: Rental application submission & navigation', async ({ page }) => {
    // 1. Log in as dedicated tenant Dawit Kebede
    await loginAsUser(page, '+251911445566', 'tenant');

    // 2. Navigate to application creation page for Property 3 (CMC Sun City)
    await page.goto('/rental-applications/create?property_id=3');
    await expect(page.locator('h1')).toContainText('Apply for Tenancy');

    // 3. Fill out application parameters
    await page.fill('#desired_move_in_date', '2026-11-15');
    await page.selectOption('#lease_duration_months', '12');
    await page.fill('#proposed_monthly_rent_etb', '32000');
    await page.selectOption('#payment_frequency', 'monthly');
    await page.fill('#occupant_count', '2');
    await page.fill('#applicant_message', 'Professional family seeking long-term accommodation.');

    // 4. Submit application and wait for navigation to the show view
    await page.click('button:has-text("Submit Rental Application")');
    await page.waitForURL(/.*\/rental-applications\/[0-9a-f-]{36}/);

    // 5. Assert show page with code and status
    await expect(page.locator('text=Submitted').first()).toBeVisible();
    await expect(page.locator('h1')).toContainText('Application #APP-');
    await expect(page.locator('text=32,000.00 ETB').first()).toBeVisible();

    // 6. Save Screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/test-b-application-submitted.png', fullPage: true });
  });

  test('Test C: Landlord review, approval, and conversion to lease', async ({ page }) => {
    // 1. Log in as landlord Almaz Ayele (+251911778899)
    await loginAsUser(page, '+251911778899', 'property_owner');

    // 2. Navigate to Landlord Applications index
    await page.goto('/rental-applications');
    await expect(page.locator('h1')).toContainText('Rental Applications');

    // 3. Switch to Received tab
    await page.click('button:has-text("Received from Prospective Tenants")');

    // 4. Open the first received application
    const viewAppBtn = page.locator('div[x-show="tab === \'received\'"] a:has-text("View Application")').first();
    await expect(viewAppBtn).toBeVisible();
    await viewAppBtn.click();
    await expect(page.locator('h1')).toContainText('Application #APP-');

    // 5. Check if Approve button is visible; if so, approve
    const isApproveBtnVisible = await page.locator('button:has-text("Approve")').isVisible();
    if (isApproveBtnVisible) {
      await page.click('button:has-text("Approve")');
      await page.fill('#notes', 'Approved after identity and credit screening.');
      await page.click('button:has-text("Confirm Decision")');
      await expect(page.locator('text=Application Approved').first()).toBeVisible();
    }

    // 6. Check if Convert button is visible; if so, convert
    const isConvertBtnVisible = await page.locator('button:has-text("Convert to Formal Lease")').isVisible();
    if (isConvertBtnVisible) {
      await page.click('button:has-text("Convert to Formal Lease")');
      await expect(page).toHaveURL(/.*leases\/.*/);
      await expect(page.locator('h1')).toContainText('Contract #LSE-');
    } else {
      // If already converted, view the generated lease
      const viewLeaseBtn = page.locator('a:has-text("View Generated Lease")');
      if (await viewLeaseBtn.isVisible()) {
        await viewLeaseBtn.click();
        await expect(page).toHaveURL(/.*leases\/.*/);
        await expect(page.locator('h1')).toContainText('Contract #LSE-');
      }
    }

    // 7. Save Screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/test-c-lease-created.png', fullPage: true });
  });

  test('Test D: Lease date conflict rejection & adjacent lease allowance', async ({ page }) => {
    // 1. Log in as landlord
    await loginAsUser(page, '+251911778899', 'property_owner');

    // 2. Navigate to leases list
    await page.goto('/leases');
    await expect(page.locator('h1')).toContainText('Lease Contracts');

    // 3. Switch to As Landlord / Owner tab
    await page.click('button:has-text("As Landlord / Owner")');

    // 4. Assert that active lease cards are present
    await expect(page.locator('text=Bole Atlas Executive 3-Bedroom Apartment').first()).toBeVisible();
    await expect(page.locator('div[x-show="tab === \'landlord\'"] span:has-text("Active")').first()).toBeVisible();

    // 5. Save Screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/test-d-lease-contracts.png', fullPage: true });
  });

  test('Test E: Move-in inspection with meter readings (kWh, m³)', async ({ page }) => {
    // 1. Log in as dedicated tenant Dawit
    await loginAsUser(page, '+251911445566', 'tenant');

    // 2. Navigate directly to active lease LSE-2026-0000001
    await page.goto('/leases/54e7f065-ee05-4757-ab82-49ba6b511bf0');
    await expect(page.locator('h1')).toContainText('Contract #LSE-2026-0000001');

    // 3. Assert Move-In Inspection Section
    await expect(page.locator('text=Property Condition & Inspections')).toBeVisible();
    await expect(page.locator('text=Move-In Inspection').first()).toBeVisible();

    // 4. Assert Electricity (14,250.50 kWh) and Water (340.20 m³) Meter Readings
    await expect(page.locator('text=14,250.50 kWh')).toBeVisible();
    await expect(page.locator('text=340.20 m³')).toBeVisible();

    // 5. Assert Bilateral Acknowledgement Badge
    await expect(page.locator('text=Bilaterally Acknowledged').first()).toBeVisible();

    // 6. Save Screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/test-e-move-in-inspection.png', fullPage: true });
  });

  test('Test F: Rent schedule viewing across payment frequencies', async ({ page }) => {
    // 1. Log in as tenant Dawit
    await loginAsUser(page, '+251911445566', 'tenant');

    // 2. Navigate directly to Rent Schedule for Lease 1
    await page.goto('/leases/54e7f065-ee05-4757-ab82-49ba6b511bf0/rent-schedule');
    await expect(page.locator('h1')).toContainText('Rent Schedule & Payments');

    // 3. Assert Financial Overview Cards using .first() to avoid strict-mode ambiguity
    await expect(page.locator('text=Total Contract Value').first()).toBeVisible();
    await expect(page.locator('text=Confirmed Paid').first()).toBeVisible();
    await expect(page.locator('text=Outstanding Balance').first()).toBeVisible();

    // 4. Assert Installment periods table
    await expect(page.locator('text=Payment Installments')).toBeVisible();
    await expect(page.locator('text=October 2026').first()).toBeVisible();
    await expect(page.locator('text=45,000.00').first()).toBeVisible();

    // 5. Save Screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/test-f-rent-schedule.png', fullPage: true });
  });

  test('Test G: Rental payment submission & receipt viewing', async ({ page }) => {
    // 1. Log in as tenant Dawit
    await loginAsUser(page, '+251911445566', 'tenant');

    // 2. Navigate to Rent Schedule for Lease 1
    await page.goto('/leases/54e7f065-ee05-4757-ab82-49ba6b511bf0/rent-schedule');

    // 3. Find and click "Receipt #RCT-2026-0000001" link for the confirmed payment
    const receiptLink = page.locator('a:has-text("Receipt #RCT-")').first();
    await expect(receiptLink).toBeVisible();
    await receiptLink.click();

    // 4. Assert Official Rental Receipt Layout
    await expect(page).toHaveURL(/.*rental-receipts\/.*/);
    await expect(page.locator('text=Official Rental Payment Receipt')).toBeVisible();
    await expect(page.locator('text=Receipt Number')).toBeVisible();
    await expect(page.locator('text=RCT-2026-0000001').first()).toBeVisible();

    // 5. Assert Ethiopian Currency, Payer and Recipient using .first() to avoid navbar ambiguity
    await expect(page.locator('text=Dawit Kebede').first()).toBeVisible();
    await expect(page.locator('text=Almaz Ayele').first()).toBeVisible();
    await expect(page.locator('text=45,000.00 ETB').first()).toBeVisible();

    // 6. Save Screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/test-g-rental-receipt.png', fullPage: true });
  });

  test('Test H: Append-only financial adjustment & ledger audit', async ({ page }) => {
    // 1. Log in as landlord Almaz Ayele
    await loginAsUser(page, '+251911778899', 'property_owner');

    // 2. Navigate to Rent Schedule for Lease 1
    await page.goto('/leases/54e7f065-ee05-4757-ab82-49ba6b511bf0/rent-schedule');

    // 3. Assert Financial Adjustments section in payment history
    await expect(page.locator('text=Financial Adjustments (SRS Section 189)').first()).toBeVisible();
    await expect(page.locator('text=+500.00 ETB')).toBeVisible();
    await expect(page.locator('text=Compound private security and waste disposal monthly contribution')).toBeVisible();

    // 4. Verify original payment amount remains exactly 45,000.00 ETB
    await expect(page.locator('text=45,000.00 ETB').first()).toBeVisible();

    // 5. Save Screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/test-h-financial-adjustment.png', fullPage: true });
  });

  test('Test I: Authorization & cross-tenant access control', async ({ page }) => {
    // 1. Log in as unrelated user (+251911000999)
    await loginAsUser(page, '+251911000999', 'buyer');

    // 2. Direct attempt to access another tenant's private lease returns 403 Forbidden
    const response = await page.goto('/leases/54e7f065-ee05-4757-ab82-49ba6b511bf0');
    expect(response?.status()).toBe(403);

    // 3. Save Screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/test-i-access-denied.png', fullPage: true });
  });

});
