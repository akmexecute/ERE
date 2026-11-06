import { test, expect } from '@playwright/test';
import path from 'node:path';

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

test.describe('Property Upload Failure Fix, Creation & Development Management E2E', () => {

  test('Test 1 — Buy Property Creation with 5 Photos and 1 Document via Website Form', async ({ page }) => {
    // 1. Authenticate as authorized user
    await loginAsUser(page, '+251911234567');

    // 2. Open Property Creation Wizard (BUY mode)
    await page.goto('/listings/wizard?type=sale');
    await expect(page).toHaveURL(/listings\/wizard/);
    await expect(page.locator('h1')).toContainText('Post a Property & Create Listing');

    // STEP 1: Mode & Taxonomy
    // Category: Residential
    await page.locator('select[name="category_code"]').selectOption('RES');
    // Property Type: Apartment
    await page.locator('select[name="property_type_code"]').selectOption('APT');
    // Title & Description
    await page.locator('input[name="title_en"]').fill('QA Manual Property Creation — BUY — Bole');
    await page.locator('textarea[name="description_en"]').fill('Luxury modern 2-bedroom apartment located in Bole with underground parking, dual elevators, high security, and backup generator.');

    // Advance to Step 2
    await page.locator('button:has-text("Next Step →")').click();

    // STEP 2: Location
    // Fill coordinates
    await page.locator('input[name="latitude"]').fill('9.0010000');
    await page.locator('input[name="longitude"]').fill('38.7850000');

    // Advance to Step 3
    await page.locator('button:has-text("Next Step →")').click();

    // STEP 3: Specifications
    await page.locator('input[name="building_area_sqm"]').fill('120');
    await page.locator('input[name="bedrooms"]').fill('2');
    await page.locator('input[name="bathrooms"]').fill('2');

    // Advance to Step 4: Media & Documents
    await page.locator('button:has-text("Next Step →")').click();

    // STEP 4: Upload 5 Photos and 1 Document
    const photoFiles = [
      path.resolve('tests/Fixtures/photo_1.jpg'),
      path.resolve('tests/Fixtures/photo_2.jpg'),
      path.resolve('tests/Fixtures/photo_3.jpg'),
      path.resolve('tests/Fixtures/photo_4.jpg'),
      path.resolve('tests/Fixtures/photo_5.jpg'),
    ];
    const docFile = path.resolve('tests/Fixtures/sample_deed.pdf');

    // Upload photos
    const photoInput = page.locator('input[name="photos[]"]');
    await photoInput.setInputFiles(photoFiles);
    await expect(page.locator('text=5 photo(s) selected')).toBeVisible();

    // Upload document
    const docInput = page.locator('input[name="documents[]"]');
    await docInput.setInputFiles([docFile]);
    await expect(page.locator('text=1 document(s) selected')).toBeVisible();

    // Advance to Step 5: Price Engine
    await page.locator('button:has-text("Next Step →")').click();

    // STEP 5: Pricing
    await page.locator('input[name="price_etb"]').fill('8500000');
    await expect(page.locator('text=70,833')).toBeVisible();

    // Submit for Moderation
    await page.locator('button:has-text("Submit Listing for Moderation")').click();

    // Verify redirected to dashboard without upload failures
    await expect(page).toHaveURL(/dashboard/);
    await expect(page.locator('text=failed to upload')).not.toBeVisible();
    await expect(page.locator('text=submitted for moderation review')).toBeVisible();

    // Verify listing is listed in dashboard
    await expect(page.locator('text=QA Manual Property Creation — BUY — Bole').first()).toBeVisible();
  });

  test('Test 2 — Development Project Creation & Unit Inventory Management via UI', async ({ page }) => {
    // 1. Authenticate as Developer / Super Admin
    await loginAsUser(page, '+251911234567');

    // 2. Open Project Creation Form
    await page.goto('/projects/create');
    await expect(page).toHaveURL(/projects\/create/);
    await expect(page.locator('h1')).toContainText('Create New Development Project');

    // 3. Complete Project Fields
    await page.locator('input[name="title_en"]').fill('QA Manual Development Creation — Addis Test Residences');
    await page.locator('input[name="title_am"]').fill('አዲስ ቴስት ሬዚደንስ');
    await page.locator('textarea[name="description_en"]').fill('Premier masterplanned multi-tower residential project in Bole featuring international earthquake standards, rooftop terrace, and underground parking.');
    await page.locator('select[name="project_type"]').selectOption('residential_complex');
    await page.locator('select[name="status"]').selectOption('under_construction');
    await page.locator('input[name="address_line"]').fill('Bole Rwanda, Behind Edna Mall, Addis Ababa');
    await page.locator('input[name="land_area_sqm"]').fill('4000');
    await page.locator('input[name="overall_progress_pct"]').fill('25');

    // 4. Submit Project Creation Form
    await page.locator('button:has-text("Create Development Project →")').click();

    // 5. Verify Redirected to Developer Management Dashboard
    await expect(page).toHaveURL(/projects\/qa-manual-development-creation-addis-test-residences.*\/manage/);
    await expect(page.locator('h1')).toContainText('QA Manual Development Creation — Addis Test Residences');
    await expect(page.locator('text=created successfully')).toBeVisible();

    // 6. Navigate to 5. Unit Inventory Management Tab
    const unitsTabBtn = page.locator('button:has-text("5. Unit Inventory Management")');
    await expect(unitsTabBtn).toBeVisible();
    await unitsTabBtn.click();

    // 7. Add Unit A-101 to the new project
    await page.locator('input[name="unit_number"]').fill('A-101');
    await page.locator('select[name="unit_type"]').selectOption('2br');
    await page.locator('input[name="floor_number"]').fill('1');
    await page.locator('input[name="area_sqm"]').fill('110');
    await page.locator('input[name="bedrooms"]').fill('2');
    await page.locator('input[name="bathrooms"]').fill('2');
    await page.locator('input[name="price_etb"]').fill('8800000');
    await page.locator('select[name="status"]').selectOption('available');

    // Submit Add Unit
    await page.locator('button:has-text("+ Add Unit to Inventory")').click();

    // 8. Verify Unit appears in table
    await expect(page).toHaveURL(/manage\?tab=units/);
    await expect(page.locator('text=Unit A-101 added successfully')).toBeVisible();
    await expect(page.locator('table').getByText('A-101')).toBeVisible();
    await expect(page.locator('table').getByText('8,800,000.00 ETB')).toBeVisible();

    // 9. Verify Public Showcase Page displays Project & Unit
    const manageUrl = page.url();
    const showcaseUrl = manageUrl.replace(/\/manage.*/, '');
    await page.goto(showcaseUrl);
    await expect(page.locator('h1')).toContainText('QA Manual Development Creation — Addis Test Residences');
    await expect(page.locator('text=A-101').first()).toBeVisible();
    await expect(page.locator('text=8,800,000')).toBeVisible();
  });

  test('Test 3 — Existing Six Demonstration Destinations and Bole Tower Residences remain intact', async ({ page }) => {
    // 1. Destination 1 — BUY
    await page.goto('/search?mode=BUY');
    await expect(page.locator('text=Modern 2-Bedroom Apartment for Sale in Bole')).toBeVisible();
    await expect(page.locator('text=8,500,000').first()).toBeVisible();

    // 2. Destination 2 — RENT
    await page.goto('/search?mode=RENT');
    await expect(page.locator('text=Furnished 2-Bedroom Apartment for Rent in Kazanchis')).toBeVisible();
    await expect(page.locator('text=35,000').first()).toBeVisible();

    // 3. Destination 3 — LEASE
    await page.goto('/search?mode=LEASE');
    await expect(page.locator('text=Modern Commercial Office Space for Lease in Bole')).toBeVisible();
    await expect(page.locator('text=180,000').first()).toBeVisible();

    // 4. Destination 4 — LAND
    await page.goto('/search?category=land');
    await expect(page.locator('text=Residential Land Plot for Sale in Summit')).toBeVisible();
    await expect(page.locator('text=6,500,000').first()).toBeVisible();

    // 5. Destination 5 — STAYS
    await page.goto('/stays');
    await expect(page.locator('text=Furnished Short-Stay Studio Apartment in Bole')).toBeVisible();
    await expect(page.locator('text=2,500').first()).toBeVisible();

    // 6. Destination 6 — DEVELOPMENTS (Bole Tower Residences)
    await page.goto('/projects/bole-tower-residences');
    await expect(page.locator('h1')).toContainText('Bole Horizon Luxury Residences');
    const towerBBtn = page.locator('button:has-text("Tower B (Horizon East)")');
    if (await towerBBtn.isVisible()) {
      await towerBBtn.click();
    }
    await expect(page.locator('text=B-102').first()).toBeVisible();
    await expect(page.locator('text=9,200,000')).toBeVisible();
  });

});
