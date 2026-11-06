import { test, expect } from '@playwright/test';

test.describe('Phase 5 Milestone 2 — Live Six Destinations & Cross-Filter Verification', () => {

  test('Destination 1 — BUY: http://localhost:8000/search?mode=BUY displays Modern 2-Bedroom Apartment in Bole and opens detail view', async ({ page }) => {
    // 1. Visit BUY search destination
    const response = await page.goto('/search?mode=BUY');
    expect(response?.status()).toBe(200);

    // 2. Verify Demonstration Property Card
    const cardTitle = page.locator('text=Modern 2-Bedroom Apartment for Sale in Bole');
    await expect(cardTitle).toBeVisible();

    // 3. Verify Price & Specifications
    await expect(page.locator('text=8,500,000').first()).toBeVisible();
    await expect(page.locator('text=Bole').first()).toBeVisible();

    // 4. Open Property Detail View
    const detailLink = page.locator('a:has-text("Modern 2-Bedroom Apartment for Sale in Bole")').first();
    await detailLink.click();
    await expect(page).toHaveURL(/\/listings\/modern-2-bedroom-apartment-for-sale-in-bole/);
    await expect(page.locator('h1')).toContainText('Modern 2-Bedroom Apartment for Sale in Bole');
    await expect(page.locator('text=8,500,000 ETB')).toBeVisible();
  });

  test('Destination 2 — RENT: http://localhost:8000/search?mode=RENT displays Furnished 2-Bedroom Apartment in Kazanchis', async ({ page }) => {
    // 1. Visit RENT search destination
    const response = await page.goto('/search?mode=RENT');
    expect(response?.status()).toBe(200);

    // 2. Verify Demonstration Property Card
    const cardTitle = page.locator('text=Furnished 2-Bedroom Apartment for Rent in Kazanchis');
    await expect(cardTitle).toBeVisible();

    // 3. Verify Price (35,000 ETB)
    await expect(page.locator('text=35,000').first()).toBeVisible();

    // 4. Open Property Detail View
    await cardTitle.click();
    await expect(page).toHaveURL(/\/listings\/furnished-2-bedroom-apartment-for-rent-in-kazanchis/);
    await expect(page.locator('h1')).toContainText('Furnished 2-Bedroom Apartment for Rent in Kazanchis');
    await expect(page.locator('text=35,000 ETB')).toBeVisible();
  });

  test('Destination 3 — LEASE: http://localhost:8000/search?mode=LEASE displays Modern Commercial Office Space in Bole', async ({ page }) => {
    // 1. Visit LEASE search destination
    const response = await page.goto('/search?mode=LEASE');
    expect(response?.status()).toBe(200);

    // 2. Verify Demonstration Property Card
    const cardTitle = page.locator('text=Modern Commercial Office Space for Lease in Bole');
    await expect(cardTitle).toBeVisible();

    // 3. Verify Price (180,000 ETB) and Area (250 m²)
    await expect(page.locator('text=180,000').first()).toBeVisible();
    await expect(page.locator('text=250 m²').first()).toBeVisible();

    // 4. Open Property Detail View
    await cardTitle.click();
    await expect(page).toHaveURL(/\/listings\/modern-commercial-office-space-for-lease-in-bole/);
    await expect(page.locator('h1')).toContainText('Modern Commercial Office Space for Lease in Bole');
    await expect(page.locator('text=180,000 ETB')).toBeVisible();
  });

  test('Destination 4 — LAND: http://localhost:8000/search?category=land displays Residential Land Plot in Summit', async ({ page }) => {
    // 1. Visit LAND search destination
    const response = await page.goto('/search?category=land');
    expect(response?.status()).toBe(200);

    // 2. Verify Demonstration Property Card
    const cardTitle = page.locator('text=Residential Land Plot for Sale in Summit');
    await expect(cardTitle).toBeVisible();

    // 3. Verify Price (6,500,000 ETB) and Land Area (300 m²)
    await expect(page.locator('text=6,500,000').first()).toBeVisible();
    await expect(page.locator('text=300 m²').first()).toBeVisible();

    // 4. Open Property Detail View
    await cardTitle.click();
    await expect(page).toHaveURL(/\/listings\/residential-land-plot-for-sale-in-summit/);
    await expect(page.locator('h1')).toContainText('Residential Land Plot for Sale in Summit');
    await expect(page.locator('text=6,500,000 ETB')).toBeVisible();
  });

  test('Destination 5 — STAYS: http://localhost:8000/stays displays Furnished Short-Stay Studio Apartment in Bole', async ({ page }) => {
    // 1. Visit Stays showcase
    const response = await page.goto('/stays');
    expect(response?.status()).toBe(200);

    // 2. Verify Demonstration Short Stay Property
    const stayTitle = page.locator('text=Furnished Short-Stay Studio Apartment in Bole');
    await expect(stayTitle).toBeVisible();

    // 3. Verify Nightly Rate (2,500 ETB)
    await expect(page.locator('text=2,500').first()).toBeVisible();

    // 4. Open Stay Details
    await stayTitle.click();
    await expect(page).toHaveURL(/\/stays\/furnished-short-stay-studio-apartment-in-bole/);
    await expect(page.locator('h1')).toContainText('Furnished Short-Stay Studio Apartment in Bole');
  });

  test('Destination 6 — DEVELOPMENTS: http://localhost:8000/projects/bole-tower-residences displays Bole Tower Residences & Available 2BR Unit B-102', async ({ page }) => {
    // 1. Visit Project Showcase
    const response = await page.goto('/projects/bole-tower-residences');
    expect(response?.status()).toBe(200);

    // 2. Verify Project Master Identity
    await expect(page.locator('h1')).toContainText('Bole Horizon Luxury Residences');
    await expect(page.locator('text=Permit: AA/CON/BP-2025-001429')).toBeVisible();

    // 3. Switch to Tower B (Horizon East) where the available demo unit B-102 is located
    const towerBButton = page.locator('button:has-text("Tower B (Horizon East)")');
    if (await towerBButton.isVisible()) {
      await towerBButton.click();
    }

    // 4. Verify Unit Stacking Matrix has Unit B-102
    const unitBadge = page.locator('text=B-102');
    await expect(unitBadge).toBeVisible();

    // 5. Select Unit B-102
    await unitBadge.click();

    // 6. Verify Drawer / Inspection displays 2BR, 115 m², 9,200,000 ETB
    await expect(page.locator('text=9,200,000').first()).toBeVisible();
    await expect(page.locator('text=115 m²').first()).toBeVisible();
  });

  test('Cross-Filter Verification — Isolation between Transaction Modes and Categories', async ({ page }) => {
    // BUY destination must NOT display RENT or LEASE demo properties
    await page.goto('/search?mode=BUY');
    await expect(page.locator('text=Modern 2-Bedroom Apartment for Sale in Bole')).toBeVisible();
    await expect(page.locator('text=Furnished 2-Bedroom Apartment for Rent in Kazanchis')).not.toBeVisible();
    await expect(page.locator('text=Modern Commercial Office Space for Lease in Bole')).not.toBeVisible();

    // RENT destination must NOT display BUY or LEASE demo properties
    await page.goto('/search?mode=RENT');
    await expect(page.locator('text=Furnished 2-Bedroom Apartment for Rent in Kazanchis')).toBeVisible();
    await expect(page.locator('text=Modern 2-Bedroom Apartment for Sale in Bole')).not.toBeVisible();
    await expect(page.locator('text=Modern Commercial Office Space for Lease in Bole')).not.toBeVisible();

    // LEASE destination must NOT display BUY or RENT demo properties
    await page.goto('/search?mode=LEASE');
    await expect(page.locator('text=Modern Commercial Office Space for Lease in Bole')).toBeVisible();
    await expect(page.locator('text=Modern 2-Bedroom Apartment for Sale in Bole')).not.toBeVisible();
    await expect(page.locator('text=Furnished 2-Bedroom Apartment for Rent in Kazanchis')).not.toBeVisible();
  });

  test('Module 34 — Live 360° Virtual Tour Viewer for Demo Buy Property', async ({ page }) => {
    // Visit published virtual tour as unauthenticated guest
    const response = await page.goto('/virtual-tours/tour-live-demo-uuid-002');
    expect(response?.status()).toBe(200);

    // Verify Title & Guest Access
    await expect(page.locator('h1')).toContainText('Bole Modern 2-Bedroom 360° Virtual Walkthrough');
    await expect(page.locator('text=Public 360° Virtual Walkthrough · All Visitors Welcome')).toBeVisible();

    // Verify Panorama container and room selector
    await expect(page.locator('#panorama-container')).toBeVisible();
    const select = page.locator('select');
    await expect(select).toContainText('Living Room');
    await expect(select).toContainText('Master Bedroom');
  });

});
