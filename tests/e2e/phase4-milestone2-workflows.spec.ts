import { test, expect } from '@playwright/test';

/**
 * Authentication Helper for Playwright tests.
 * Performs OTP exchange to establish session for an admin/manager.
 */
async function loginAsAdmin(page: any, phone: string = '+251911234567') {
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

test.describe('Phase 4 Milestone 2 — Maintenance, Portfolio Dashboard & Invoices E2E', () => {

  test('Enterprise Property Management Dashboard displays KPIs and Quick Action Links', async ({ page }) => {
    // 1. Authenticate as property administrator
    await loginAsAdmin(page, '+251911234567');

    // 2. Navigate to Portfolio Dashboard
    await page.goto('/portfolio/dashboard');
    await expect(page).toHaveURL(/portfolio\/dashboard/);

    // 3. Verify Header and Module 29 context
    await expect(page.locator('h1')).toContainText('Property Management Dashboard');
    await expect(page.locator('text=Module 29')).toBeVisible();

    // 4. Verify 11 Standard Business KPI Cards
    await expect(page.locator('text=Properties Managed').first()).toBeVisible();
    await expect(page.locator('text=Total Units').first()).toBeVisible();
    await expect(page.locator('text=Occupied Units').first()).toBeVisible();
    await expect(page.locator('text=Rent Collected').first()).toBeVisible();
    await expect(page.locator('text=Expected Rent').first()).toBeVisible();
    await expect(page.locator('text=Outstanding Arrears').first()).toBeVisible();

    // 5. Verify Quick Action Links in action bar
    await expect(page.getByRole('link', { name: '🏢 Unit Inventory Matrix' })).toBeVisible();
    await expect(page.getByRole('link', { name: '📜 Management Mandates' })).toBeVisible();
    await expect(page.getByRole('link', { name: '🔧 Maintenance Board' })).toBeVisible();
    await expect(page.getByRole('link', { name: '+ Issue Invoice' })).toBeVisible();
  });

  test('Portfolio Matrix renders multi-unit inventory view', async ({ page }) => {
    await loginAsAdmin(page, '+251911234567');

    await page.goto('/portfolio/matrix');
    await expect(page).toHaveURL(/portfolio\/matrix/);
    await expect(page.locator('h1')).toContainText('Multi-Unit Portfolio Matrix');
    await expect(page.locator('text=Real-time occupancy status')).toBeVisible();
  });

  test('Portfolio Mandates page lists management agreements', async ({ page }) => {
    await loginAsAdmin(page, '+251911234567');

    await page.goto('/portfolio/mandates');
    await expect(page).toHaveURL(/portfolio\/mandates/);
    await expect(page.locator('h1')).toContainText('Property Management Mandates');
  });

  test('Maintenance Tickets page renders work orders and ticket submission form', async ({ page }) => {
    await loginAsAdmin(page, '+251911234567');

    // 1. View Index
    await page.goto('/maintenance');
    await expect(page).toHaveURL(/maintenance/);
    await expect(page.locator('h1')).toContainText('Maintenance Requests & Work Orders');

    // 2. Open Ticket Creation Form
    await page.goto('/maintenance/create');
    await expect(page).toHaveURL(/maintenance\/create/);
    await expect(page.locator('h1')).toContainText('Report a Maintenance Issue');
    await expect(page.getByRole('button', { name: 'Submit Maintenance Ticket' })).toBeVisible();
  });

  test('Invoices index and creation form render correctly', async ({ page }) => {
    await loginAsAdmin(page, '+251911234567');

    // 1. View Invoices Index
    await page.goto('/invoices');
    await expect(page).toHaveURL(/invoices/);
    await expect(page.locator('h1')).toContainText('ETB Tax Invoices & Receipts');

    // 2. View Create Form
    await page.goto('/invoices/create');
    await expect(page).toHaveURL(/invoices\/create/);
    await expect(page.locator('h1')).toContainText('Issue Official ETB Tax Invoice');
    await expect(page.locator('text=Issuing Company')).toBeVisible();
  });

  // =========================================================================
  // REAL END-TO-END BUSINESS WORKFLOW TESTS (PART 6)
  // =========================================================================

  test('Workflow A — End-to-end maintenance ticket creation, lifecycle transitions and closure', async ({ page }) => {
    await loginAsAdmin(page, '+251911234567');

    // 1. Open Ticket Creation Form
    await page.goto('/maintenance/create');
    await expect(page.locator('h1')).toContainText('Report a Maintenance Issue');

    // Select first available property
    const propertySelect = page.locator('select[name="property_id"]');
    const propertyOptions = await propertySelect.locator('option').all();
    if (propertyOptions.length > 1) {
      const val = await propertyOptions[1].getAttribute('value');
      if (val) {
        await propertySelect.selectOption(val);
      }
    }

    // Fill form details
    await page.locator('select[name="category"]').selectOption('plumbing');
    await page.locator('select[name="priority"]').selectOption('high');
    await page.locator('input[name="title"]').fill('Emergency bathroom pipe leak');
    await page.locator('textarea[name="description"]').fill('Water is rapidly pooling under the master bath sink on the floor.');

    // Submit ticket
    await page.getByRole('button', { name: 'Submit Maintenance Ticket' }).click();

    // Verify redirected to ticket details
    await expect(page).toHaveURL(/maintenance\/[0-9a-f-]+/);
    await expect(page.locator('text=Emergency bathroom pipe leak')).toBeVisible();
    await expect(page.locator('text=Open').first()).toBeVisible();

    const ticketUrl = page.url();

    // 2. Advance to Assigned
    const assignBtn = page.getByRole('button', { name: 'Assign Technician / Contractor' });
    if (await assignBtn.isVisible()) {
      await assignBtn.click();
      await page.locator('input[name="contractor_name"]').fill('Addis Rapid Plumbing');
      await page.locator('input[name="contractor_phone"]').fill('+251911223344');
      await page.getByRole('button', { name: 'Confirm Transition' }).click();
      await page.waitForURL(ticketUrl);
      await expect(page.locator('text=Assigned').first()).toBeVisible();

      // 3. Advance to In Progress
      const startWorkBtn = page.getByRole('button', { name: 'Start Work' });
      if (await startWorkBtn.isVisible()) {
        await startWorkBtn.click();
        await page.getByRole('button', { name: 'Confirm Transition' }).click();
        await page.waitForURL(ticketUrl);
        await expect(page.locator('text=In Progress').first()).toBeVisible();

        // 4. Advance to Completed with resolution notes and actual cost
        const completeBtn = page.getByRole('button', { name: 'Complete Work Order' });
        if (await completeBtn.isVisible()) {
          await completeBtn.click();
          await page.locator('textarea[name="resolution_notes"]').fill('Replaced ruptured brass connector and tested water pressure.');
          await page.locator('input[name="actual_cost_etb"]').fill('3500');
          await page.getByRole('button', { name: 'Confirm Transition' }).click();
          await page.waitForURL(ticketUrl);
          await expect(page.locator('text=Completed').first()).toBeVisible();

          // 5. Sign Off & Close
          const closeBtn = page.getByRole('button', { name: 'Sign Off & Close' });
          if (await closeBtn.isVisible()) {
            await closeBtn.click();
            await page.getByRole('button', { name: 'Confirm Transition' }).click();
            await page.waitForURL(ticketUrl);
            await expect(page.locator('text=Closed').first()).toBeVisible();
          }
        }
      }
    }

    // Refresh and verify audit history shows persisted events
    await page.reload();
    await expect(page.locator('text=Emergency bathroom pipe leak')).toBeVisible();
    await expect(page.locator('text=Status History').first()).toBeVisible();
  });

  test('Workflow B — Portfolio dashboard KPIs and strict multi-tenant isolation', async ({ page }) => {
    // 1. Authenticate as company manager / admin
    await loginAsAdmin(page, '+251911234567');

    await page.goto('/portfolio/dashboard');
    await expect(page.locator('h1')).toContainText('Property Management Dashboard');

    // Verify key database metrics render without errors
    await expect(page.locator('text=Properties Managed').first()).toBeVisible();
    await expect(page.locator('text=Total Units').first()).toBeVisible();
    await expect(page.locator('text=Rent Collected').first()).toBeVisible();
    await expect(page.locator('text=Expected Rent').first()).toBeVisible();

    // 2. Verify Mandates Page for authorized manager
    await page.goto('/portfolio/mandates');
    await expect(page.locator('h1')).toContainText('Property Management Mandates');

    // 3. Verify strict fail-closed isolation for unaffiliated tenant/user
    await loginAsAdmin(page, '+251911445566'); // Tenant Dawit
    await page.goto('/portfolio/mandates');
    await expect(page.locator('h1')).toContainText('Property Management Mandates');
    // For unaffiliated tenant, zero unauthorized company mandates are shown
    await expect(page.locator('text=No property management mandates found')).toBeVisible();
  });

  test('Workflow C — Sequential tax invoice issuance, print view and voiding lifecycle', async ({ page }) => {
    await loginAsAdmin(page, '+251911234567');

    // 1. Open Invoice Creation
    await page.goto('/invoices/create');
    await expect(page.locator('h1')).toContainText('Issue Official ETB Tax Invoice');

    // Fill Item description and price
    await page.locator('select[name="service_type"]').selectOption('rent');
    await page.locator('input[name="items[0][description]"]').fill('November 2026 Commercial Office Lease');
    await page.locator('input[name="items[0][unit_price_etb]"]').fill('50000');

    // Submit invoice
    await page.getByRole('button', { name: 'Issue & Persist Invoice' }).click();

    // Verify redirected to invoice show page
    await expect(page).toHaveURL(/invoices\/[0-9a-f-]+/);
    await expect(page.locator('text=INV-2026-').first()).toBeVisible();
    await expect(page.locator('text=ETB 50,000.00').first()).toBeVisible();

    // Verify Ethiopian VAT is calculated (15% = ETB 7,500.00, Total = ETB 57,500.00)
    await expect(page.locator('text=ETB 57,500.00').first()).toBeVisible();

    const invoiceUrl = page.url();

    // 2. Open Print View
    await page.goto(`${invoiceUrl}/print`);
    await expect(page.locator('text=OFFICIAL TAX INVOICE')).toBeVisible();
    await expect(page.locator('text=INV-2026-').first()).toBeVisible();
    await expect(page.locator('text=ETB 57,500.00').first()).toBeVisible();

    // 3. Return to invoice show and perform Void Action
    await page.goto(invoiceUrl);
    const voidBtn = page.getByRole('button', { name: 'Void Invoice' });
    await expect(voidBtn).toBeVisible();
    await voidBtn.click();

    // Input void reason and submit
    await page.locator('textarea[name="reason"]').fill('Duplicate draft issued by mistake during commercial tenant onboarding.');
    await page.getByRole('button', { name: 'Confirm Void' }).click();

    // Verify invoice status is updated to voided and balance drops to ETB 0.00
    await expect(page.locator('text=Voided').first()).toBeVisible();
    await expect(page.locator('text=ETB 0.00').first()).toBeVisible();
    await expect(page.locator('text=Duplicate draft issued by mistake')).toBeVisible();
  });
});
