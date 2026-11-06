import { test, expect } from '@playwright/test';

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

test.describe('Property Submission & Boolean Validation Suite', () => {

  test('User can post a property with negotiable price without boolean validation error', async ({ page }) => {
    // 1. Authenticate as landlord/seller
    await loginAsUser(page, '+251911223344', 'landlord');

    // 2. Open listing wizard
    await page.goto('/listings/wizard');
    await expect(page).toHaveURL(/.*listings\/wizard/);

    // 3. Step 1: Fill title and advance
    await page.fill('input[name="title_en"]', 'E2E Verified Luxury Villa Negotiable Price');
    await page.fill('textarea[name="description_en"]', 'Spacious luxury villa in Bole with asphalt road access and backup generator.');
    await page.click('button:has-text("Next Step")');

    // 4. Step 2: Location (fill coordinates)
    await page.fill('input[name="latitude"]', '9.0192');
    await page.fill('input[name="longitude"]', '38.7525');
    await page.click('button:has-text("Next Step")');

    // 5. Step 3: Dimensions & Rooms
    await page.fill('input[name="land_area_sqm"]', '350');
    await page.fill('input[name="building_area_sqm"]', '280');
    await page.click('button:has-text("Next Step")');

    // 6. Step 4: Media
    await page.click('button:has-text("Next Step")');

    // 7. Step 5: Pricing & Negotiability
    await page.fill('input[name="price_etb"]', '25000000');
    await page.selectOption('select[name="is_negotiable"]', '1');

    // Submit for moderation review
    await Promise.all([
      page.waitForURL(/.*dashboard.*/),
      page.click('button:has-text("Submit Listing for Moderation")'),
    ]);

    // 8. Assert redirect to dashboard and NO validation error
    await expect(page).toHaveURL(/.*dashboard.*/);
    await expect(page.locator('text=The is negotiable field must be true or false')).not.toBeVisible();
    await expect(page.locator('text=submitted for moderation review').first()).toBeVisible();

    // 9. Capture screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/property-negotiable-success.png', fullPage: true });
  });

  test('User can post a property with fixed (non-negotiable) price', async ({ page }) => {
    // 1. Authenticate as landlord/seller
    await loginAsUser(page, '+251911223344', 'landlord');

    // 2. Open listing wizard
    await page.goto('/listings/wizard');
    await expect(page).toHaveURL(/.*listings\/wizard/);

    // 3. Step 1: Fill title and advance
    await page.fill('input[name="title_en"]', 'E2E Verified Kazanchis Apartment Fixed Price');
    await page.fill('textarea[name="description_en"]', 'Contemporary apartment in central Kazanchis.');
    await page.click('button:has-text("Next Step")');

    // 4. Step 2: Location
    await page.fill('input[name="latitude"]', '9.0200');
    await page.fill('input[name="longitude"]', '38.7600');
    await page.click('button:has-text("Next Step")');

    // 5. Step 3: Dimensions
    await page.fill('input[name="land_area_sqm"]', '120');
    await page.fill('input[name="building_area_sqm"]', '120');
    await page.click('button:has-text("Next Step")');

    // 6. Step 4: Media
    await page.click('button:has-text("Next Step")');

    // 7. Step 5: Pricing & Non-Negotiable
    await page.fill('input[name="price_etb"]', '18000000');
    await page.selectOption('select[name="is_negotiable"]', '0');

    // Submit for moderation review
    await Promise.all([
      page.waitForURL(/.*dashboard.*/),
      page.click('button:has-text("Submit Listing for Moderation")'),
    ]);

    // 8. Assert redirect to dashboard and success
    await expect(page).toHaveURL(/.*dashboard.*/);
    await expect(page.locator('text=The is negotiable field must be true or false')).not.toBeVisible();
    await expect(page.locator('text=submitted for moderation review').first()).toBeVisible();

    // 9. Capture screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/property-fixed-price-success.png', fullPage: true });
  });

});
