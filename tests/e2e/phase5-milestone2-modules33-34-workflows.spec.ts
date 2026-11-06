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

test.describe('Phase 5 Milestone 2 — Subscription Monetization, 360 Tours & GIS Heatmaps E2E', () => {

  test('Workflow 1 — Public Subscriptions Page renders dynamic database comparison matrix and live pricing calculation', async ({ page }) => {
    // 1. Visit public subscriptions page without authentication
    await page.goto('/subscriptions');
    await expect(page).toHaveURL(/subscriptions/);

    // 2. Verify Header and Value Proposition
    await expect(page.locator('h1')).toContainText('Scale Your Real Estate Reach Across Ethiopia');
    await expect(page.locator('text=Membership & Monetization Engine')).toBeVisible();

    // 3. Verify Database-Driven Plans are displayed
    await expect(page.locator('text=Free Basic').first()).toBeVisible();
    await expect(page.locator('text=Agent Pro').first()).toBeVisible();
    await expect(page.locator('text=Real Estate Agency').first()).toBeVisible();

    // 4. Verify Feature Comparison Matrix Table (Module 33.1)
    await expect(page.locator('text=Feature Comparison Matrix')).toBeVisible();
    await expect(page.locator('text=Platform Capability / Feature')).toBeVisible();
    await expect(page.locator('text=Active Property Listings').first()).toBeVisible();
    await expect(page.locator('text=360° Virtual Tours').first()).toBeVisible();

    // 5. Test Selecting Paid Plan (Agent Pro) and Upgrades
    const agentProCard = page.locator('[data-plan-code="agent_pro"]');
    await agentProCard.click();

    // Verify order summary displays base plan price
    await expect(page.locator('text=Order Summary')).toBeVisible();
    await expect(page.locator('text=Agent Pro Plan')).toBeVisible();

    // Optional upgrades are visible for paid plan
    await expect(page.locator('text=Promoted Listing Upgrade (Optional)')).toBeVisible();
    await expect(page.locator('text=Multi-Placement Display Advertising (Optional)')).toBeVisible();

    // 6. Test Free Plan Exclusion Rule (Standard plan cannot include paid add-ons)
    const freePlanCard = page.locator('[data-plan-code="free"]');
    await freePlanCard.click();

    await expect(page.locator('text=Standard Free tier listings are indexed at base relevance.').first()).toBeVisible();
    await expect(page.locator('text=Display advertising slots across ERE high-traffic surfaces are exclusive to active commercial members.').first()).toBeVisible();
  });

  test('Workflow 2 — Public 360° Virtual Tour Viewer is accessible without subscription or login', async ({ page }) => {
    // 1. Visit public tour URL as unauthenticated visitor
    await page.goto('/virtual-tours/tour-e2e-demo-uuid-001');

    // 2. Verify Page Title and Public Attribution
    await expect(page.locator('h1')).toContainText('Bole Grand Residence 360° Walkthrough');
    await expect(page.locator('text=Public 360° Virtual Walkthrough · All Visitors Welcome')).toBeVisible();

    // 3. Verify 360 Stage Container
    const panoramaStage = page.locator('#panorama-container');
    await expect(panoramaStage).toBeVisible();

    // 4. Verify Scene Selector Dropdown
    const sceneSelect = page.locator('select');
    await expect(sceneSelect).toBeVisible();
    await expect(sceneSelect).toContainText('Grand Salon');
    await expect(sceneSelect).toContainText('Master Bedroom');

    // 5. Verify Navigation Helper
    await expect(page.locator('text=Drag or swipe to rotate 360°')).toBeVisible();
  });

  test('Workflow 3 — Marketplace Search Map allows toggling between Normal Pins and PostGIS Heatmap layer', async ({ page }) => {
    // 1. Visit marketplace search page
    await page.goto('/search');

    // 2. Verify Split Map Container
    const mapContainer = page.locator('#ep-split-map');
    await expect(mapContainer).toBeVisible();

    // 3. Verify Layer Mode Toggle Buttons
    const propertiesModeBtn = page.locator('button:has-text("Properties")').first();
    const heatmapModeBtn = page.locator('button:has-text("ETB/m² Heatmap")').first();

    await expect(propertiesModeBtn).toBeVisible();
    await expect(heatmapModeBtn).toBeVisible();

    // 4. Click Heatmap Mode Button (as guest)
    await heatmapModeBtn.click();

    // 5. Verify Entitlement Gate Banner appears for unauthorized visitor
    const gateNotice = page.locator('text=Advanced Spatial Heatmaps Require Subscription');
    await expect(gateNotice).toBeVisible();
    await expect(page.locator('text=View Subscription Plans')).toBeVisible();

    // 6. Click Back to Normal Map restores normal view
    await page.locator('button:has-text("Back to Normal Map")').click();
    await expect(gateNotice).not.toBeVisible();
  });

  test('Workflow 4 — Admin Console verifies Subscription Plans, Features, and Heatmap Management', async ({ page }) => {
    // 1. Authenticate as Super Admin
    await loginAsUser(page, '+251911234567');

    // 2. Visit Admin Subscriptions Features Catalog
    await page.goto('/admin/subscriptions/features');
    await expect(page.locator('h1')).toContainText('Dynamic Subscription Features');
    await expect(page.locator('table').getByText('property.max_active_listings', { exact: true })).toBeVisible();
    await expect(page.locator('table').getByText('property.virtual_tours', { exact: true })).toBeVisible();

    // 3. Visit Admin Subscription Plans
    await page.goto('/admin/subscriptions/plans');
    await expect(page.locator('h1')).toContainText('Subscription Plans Management');
    await expect(page.getByText('agent_pro', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('agency', { exact: true }).first()).toBeVisible();

    // 4. Visit Admin PostGIS Heatmaps Management
    await page.goto('/admin/gis/heatmaps');
    await expect(page.locator('h1')).toContainText('Neighborhood ETB/m² Asking-Price Heatmaps');
    await expect(page.locator('text=Mandatory Regulatory Attribution')).toBeVisible();
    await expect(page.locator('button:has-text("Recalculate Aggregations")')).toBeVisible();
  });

});

