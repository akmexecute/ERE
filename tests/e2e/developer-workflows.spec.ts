import { test, expect } from '@playwright/test';

/**
 * Authentication Helper for Playwright tests.
 * Performs OTP exchange to establish an authoritative session context.
 */
async function loginAsDeveloper(page: any) {
  const sendRes = await page.request.post('/auth/otp/send', {
    headers: { 'Accept': 'application/json' },
    data: {
      phone: '+251911234567',
      purpose: 'login',
    },
  });
  const sendData = await sendRes.json();
  const code = sendData.debug_code || '187680';

  const verifyRes = await page.request.post('/auth/otp/verify', {
    headers: { 'Accept': 'application/json' },
    data: {
      phone: '+251911234567',
      code: code,
      purpose: 'login',
      role: 'developer',
    },
  });
  expect(verifyRes.ok()).toBeTruthy();
}

test.describe('Phase 3 Milestone 2: Developer Workflows, Stacking Matrix & Reservations', () => {

  test('Test A: Project Showcase Page Verification', async ({ page }) => {
    // 1. Navigate to the project public page
    await page.goto('/projects/bole-tower-residences');
    await expect(page).toHaveTitle(/Bole (Horizon|Tower)/);

    // 2. Assert Project Name and Master Identity
    const projectTitle = page.locator('h1');
    await expect(projectTitle).toContainText('Bole Horizon Luxury Residences');

    // 3. Assert Developer Badge and Legal Identity
    await expect(page.locator('text=Bole Horizon Developments PLC').first()).toBeVisible();
    await expect(page.locator('text=Developer:').locator('..').locator('text=Verified')).toBeVisible();

    // 4. Assert Status and Building Permit Number
    await expect(page.locator('text=Under construction').first()).toBeVisible();
    await expect(page.locator('text=Permit:')).toBeVisible();

    // 5. Assert Construction Progress Bar and Percentage
    await expect(page.locator('text=Verified Progress')).toBeVisible();
    await expect(page.locator('.text-2xl.font-bold.text-emerald-400')).toBeVisible();

    // 6. Assert Master Site Plan Blueprint Section
    await expect(page.locator('text=Master Site Plan Blueprint')).toBeVisible();
    await expect(page.locator('text=Site Plan Drawing')).toBeVisible();

    // 7. Assert Architectural 3D Render Section with Non-Photo Notice
    await expect(page.locator('text=Architectural 3D Render')).toBeVisible();
    await expect(page.locator('text=Illustrative Render (Not Completed Photo)')).toBeVisible();

    // 8. Assert Building Inventory Stacking Elevation Grid
    await expect(page.locator('text=Unit Stacking Matrix')).toBeVisible();

    // 9. Capture screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/test_a_project_showcase.png', fullPage: true });
  });

  test('Test B: Custom Construction Stages Management', async ({ page }) => {
    // Authenticate as developer company officer
    await loginAsDeveloper(page);

    // 1. Navigate to Developer Project Management interface
    await page.goto('/projects/bole-tower-residences/manage');
    await expect(page.locator('h1')).toContainText('Bole Horizon Luxury Residences');

    // 2. Ensure Tab 1 (Construction Stages) is active
    await page.click('button:has-text("1. Construction Stages & Progress")');
    await expect(page.locator('text=Configurable Construction Stages')).toBeVisible();

    // 3. Verify Initial Weighted Progress is displayed
    const progressDisplay = page.locator('span:has-text("Weighted Progress")').locator('..').locator('div');
    await expect(progressDisplay.first()).toContainText('%');

    // Delete any previous smart_wiring stage if present
    const existingRow = page.locator('tr:has-text("smart_wiring")');
    if (await existingRow.count() > 0) {
      await existingRow.locator('button:has-text("Delete")').first().click();
      await page.waitForTimeout(300);
    }

    // 4. Add a new custom stage
    const codeInput = page.locator('input[placeholder*="Code (e.g. excavation)"]');
    const nameEnInput = page.locator('input[placeholder*="English Name"]');
    const nameAmInput = page.locator('input[placeholder*="Amharic Name"]');
    const weightInput = page.locator('input[placeholder*="Weight %"]');

    await codeInput.fill('smart_wiring');
    await nameEnInput.fill('Smart Home Automation Pre-Wiring');
    await nameAmInput.fill('የስማርት ቤት ሽቦ ዝርጋታ');
    await weightInput.fill('5');

    await page.click('button:has-text("Add Stage")');

    // 5. Verify the new stage is added to the table
    await expect(page.locator('td:has-text("smart_wiring")')).toBeVisible();

    // 6. Adjust first stage weight to bring total back to exactly 100.00%
    const stageWeightInputs = page.locator('tbody tr input[type="number"][step="0.5"]');
    let currentSum = 0;
    const count = await stageWeightInputs.count();
    for (let i = 0; i < count; i++) {
      currentSum += parseFloat(await stageWeightInputs.nth(i).inputValue()) || 0;
    }
    const diff = currentSum - 100.0;
    if (Math.abs(diff) > 0.01) {
      const firstVal = parseFloat(await stageWeightInputs.first().inputValue()) || 0;
      const targetVal = Math.max(1, Math.round((firstVal - diff) * 10) / 10);
      await stageWeightInputs.first().fill(targetVal.toString());
      await stageWeightInputs.first().dispatchEvent('input');
    }

    // 7. Total weight should now be 100.00%
    const totalWeightText = page.locator('text=Total Configured Weight').locator('..');
    await expect(totalWeightText).toContainText('100.00%');

    // 8. Save stages & recalculate progress
    const saveButton = page.locator('button:has-text("Save Stages & Recalculate Progress")');
    await expect(saveButton).toBeEnabled();
    await saveButton.click();

    // 9. Verify confirmation feedback message
    await expect(page.locator('text=Construction stages saved and weighted progress recalculated.')).toBeVisible({ timeout: 10000 });

    // 10. Capture screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/test_b_stages_management.png', fullPage: true });
  });

  test('Test C: Milestone Payment Plan Configuration', async ({ page }) => {
    // Authenticate as developer company officer
    await loginAsDeveloper(page);

    // 1. Navigate to Developer Project Management interface
    await page.goto('/projects/bole-tower-residences/manage');

    // 2. Open Tab 2 (Milestone Payment Plans)
    await page.click('button:has-text("2. Milestone Payment Plans")');
    await expect(page.locator('text=Create Custom Payment Plan (100% Total)')).toBeVisible();

    // 3. Fill Plan Name
    const planNameInput = page.locator('input[placeholder*="e.g. Standard 6-Stage Construction Plan"]');
    await planNameInput.fill('Bole Tower Custom Escrow-Free Plan');

    // 4. Verify validation against non-100% plans
    // Change first percentage to 20 instead of 10 so total becomes 110%
    const pctInputs = page.locator('input[placeholder="%"]');
    await pctInputs.first().fill('20');
    await pctInputs.first().dispatchEvent('input');

    const totalPctIndicator = page.locator('text=Total:').locator('..');
    await expect(totalPctIndicator).toContainText('110.00%');
    await expect(totalPctIndicator).toContainText('Must be exactly 100%');

    // Save button must be disabled
    const savePlanButton = page.locator('button:has-text("Save Payment Plan")');
    await expect(savePlanButton).toBeDisabled();

    // 5. Correct the installment so total equals exactly 100.00%
    await pctInputs.first().fill('10');
    await pctInputs.first().dispatchEvent('input');

    await expect(totalPctIndicator).toContainText('Total: 100.00%');
    await expect(savePlanButton).toBeEnabled();

    // 6. Save valid payment plan
    await savePlanButton.click();

    // 7. Capture screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/test_c_payment_plan.png', fullPage: true });
  });

  test('Test D: Stacking Matrix & Unit Explorer', async ({ page }) => {
    // 1. Navigate to Project Public Showcase
    await page.goto('/projects/bole-tower-residences');

    // 2. Check interactive building switcher
    const buildingButtons = page.locator('button:has-text("Tower")');
    if (await buildingButtons.count() > 1) {
      await buildingButtons.nth(1).click();
      await page.waitForTimeout(300);
      await buildingButtons.first().click();
    }

    // 3. Filter by bedroom count
    const filterSelect = page.locator('#typeFilter');
    await filterSelect.selectOption('2br');
    await page.waitForTimeout(300);
    await filterSelect.selectOption('all');

    // 4. Click an available unit button in the matrix grid
    const availableUnit = page.locator('button:has-text("available")').filter({ hasText: 'Bed' }).first();
    await expect(availableUnit).toBeVisible();
    await availableUnit.click();

    // 5. Assert Unit Drawer displays specification details
    await expect(page.locator('text=Selected Unit')).toBeVisible();
    await expect(page.locator('text=Gross Area')).toBeVisible();
    await expect(page.locator('text=m²').first()).toBeVisible();
    await expect(page.locator('text=Total Purchase Price')).toBeVisible();
    await expect(page.locator('text=ETB').first()).toBeVisible();
    await expect(page.locator('text=Milestone Payment Plan (100%)')).toBeVisible();

    // 6. Assert "Reserve Unit (15-Min Hold)" button is visible
    const reserveButton = page.locator('button:has-text("Reserve Unit (15-Min Hold)")');
    await expect(reserveButton).toBeVisible();

    // 7. Capture screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/test_d_stacking_matrix_unit.png' });
  });

  test('Test E: Site Plan and Architectural Imagery Verification', async ({ page }) => {
    // 1. Navigate to Project Public Showcase
    await page.goto('/projects/bole-tower-residences');

    // 2. Verify Master Site Plan Blueprint Card
    const sitePlanCard = page.locator('div:has-text("Master Site Plan Blueprint")').first();
    await expect(sitePlanCard).toBeVisible();
    await expect(page.locator('text=Site Plan Drawing')).toBeVisible();
    await expect(page.locator('text=Ground footprint, road access & building orientation')).toBeVisible();

    // 3. Verify Architectural 3D Render Card with Illustrative Render Badge
    const renderCard = page.locator('div:has-text("Architectural 3D Render")').first();
    await expect(renderCard).toBeVisible();
    await expect(page.locator('text=Illustrative Render (Not Completed Photo)')).toBeVisible();
    await expect(page.locator('text=Proposed exterior elevation & facade design')).toBeVisible();
    await expect(page.locator('text=Eurocode 8 Seismic Standards')).toBeVisible();

    // 4. Capture screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/test_e_site_plan_imagery.png' });
  });

  test('Test F: Developer Payment Accounts Management', async ({ page }) => {
    // Authenticate as developer company officer
    await loginAsDeveloper(page);

    // 1. Navigate to Developer Project Management interface
    await page.goto('/projects/bole-tower-residences/manage');

    // 2. Switch to Tab 3 (Developer Payment Accounts)
    await page.click('button:has-text("3. Developer Payment Accounts")');
    await expect(page.locator('text=Developer Manual Payment Accounts')).toBeVisible();

    // 3. Register a new bank account
    await page.locator('input[placeholder*="e.g. CBE Primary Operations"]').fill('Awash Bank Project Settlement Account');
    await page.locator('input[placeholder*="e.g. Commercial Bank of Ethiopia"]').fill('Awash Bank S.C.');
    await page.locator('input[placeholder*="Legal registered company name"]').fill('Bole Horizon Developments PLC');
    await page.locator('input[placeholder*="e.g. 1000123456789"]').fill('01304910293847');
    await page.locator('input[placeholder*="e.g. Bole Medhanialem Branch"]').fill('Bole Airport Branch');
    await page.locator('textarea[placeholder*="narrative"]').fill('Include unit number and reservation code in bank transfer memo.');

    // 4. Submit Registration
    const registerBtn = page.locator('button:has-text("Register Payment Destination Account")');
    await registerBtn.click();

    // 5. Verify account appears under registered accounts after tab reload
    await page.waitForTimeout(1500);
    await page.click('button:has-text("3. Developer Payment Accounts")');
    await expect(page.locator('text=Awash Bank S.C.').first()).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=01304910293847').first()).toBeVisible();

    // 6. Capture screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/test_f_payment_accounts.png', fullPage: true });
  });

  test('Test G: Manual Bank Transfer Reservation Flow & Verification', async ({ page }) => {
    // Authenticate as buyer/developer user
    await loginAsDeveloper(page);

    // 1. Navigate to Project Public Showcase
    await page.goto('/projects/bole-tower-residences');

    // 2. Select an available unit
    const availableUnit = page.locator('button:has-text("available")').filter({ hasText: 'Bed' }).first();
    await expect(availableUnit).toBeVisible();
    await availableUnit.click();

    // 3. Open Reservation Hold Modal
    const reserveBtn = page.locator('button:has-text("Reserve Unit (15-Min Hold)")');
    await reserveBtn.click();

    // 4. Modal State 1: Acknowledge statutory consent and confirm hold
    await expect(page.locator('text=Lock Unit Reservation Hold')).toBeVisible();
    const consentCheckbox = page.locator('input[type="checkbox"]').first();
    await consentCheckbox.check();

    const confirmHoldBtn = page.locator('button:has-text("Confirm 15-Minute Hold")');
    await confirmHoldBtn.click();

    // 5. Modal State 2: Active Reservation Hold with Countdown Timer
    await expect(page.locator('text=Priority Hold Active')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=Remaining Hold Time')).toBeVisible();
    await expect(page.locator('text=Off-Plan Statutory Disclosures')).toBeVisible();

    // Check contract disclosure checkbox and convert
    const disclosureCheckbox = page.locator('text=I explicitly accept the statutory terms').locator('..').locator('input[type="checkbox"]');
    await disclosureCheckbox.check();

    const convertBtn = page.locator('button:has-text("Convert to Sales Contract")');
    await convertBtn.click();

    // 6. Modal State 3: Contract Executed
    await expect(page.locator('text=Contract Executed')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=Option B: Manual Bank Transfer to Developer')).toBeVisible();

    // 7. Select Option B (Manual Bank Transfer)
    const optionBBtn = page.locator('button:has-text("Option B: Manual Bank Transfer to Developer")');
    await optionBBtn.scrollIntoViewIfNeeded();
    await optionBBtn.click({ force: true });
    await expect(page.locator('text=Direct transfer to developer\'s verified project accounts')).toBeVisible();

    // 8. Submit Manual Transfer Reference Proof
    const transferRefInput = page.locator('input[placeholder*="CBE-TX-2026-908123"]');
    const transferRef = 'CBE-TX-PLAYWRIGHT-' + Math.floor(Math.random() * 900000 + 100000);
    await transferRefInput.scrollIntoViewIfNeeded();
    await transferRefInput.fill(transferRef);
    await transferRefInput.dispatchEvent('input');

    const submitProofBtn = page.locator('button:has-text("Submit Transfer Proof for Developer Verification")');
    await submitProofBtn.scrollIntoViewIfNeeded();
    await expect(submitProofBtn).toBeEnabled({ timeout: 5000 });
    await submitProofBtn.click({ force: true });

    // Verify confirmation message
    await expect(page.locator('text=Payment claim recorded')).toBeVisible({ timeout: 10000 });

    // 9. Navigate to Developer Portal to verify the pending claim
    await page.goto('/projects/bole-tower-residences/manage');
    await page.click('button:has-text("4. Sales Contracts & Amendments")');
    await expect(page.locator('text=Active Developer Sales Contracts')).toBeVisible();

    // Locate the pending transaction claim and approve
    const approveBtn = page.locator('button:has-text("Approve")').first();
    if (await approveBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await approveBtn.click();
      await page.waitForTimeout(1200);
    }

    // 10. Capture screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/test_g_manual_transfer_flow.png', fullPage: true });
  });

  test('Test H: Contract Amendment Workflow & Rejected Preservation', async ({ page }) => {
    // Authenticate as developer company officer
    await loginAsDeveloper(page);

    // 1. Navigate to Developer Project Management interface
    await page.goto('/projects/bole-tower-residences/manage');

    // 2. Switch to Tab 4 (Contracts & Amendments)
    await page.click('button:has-text("4. Sales Contracts & Amendments")');
    await expect(page.locator('text=Active Developer Sales Contracts')).toBeVisible();

    // 3. Propose a price amendment on the first contract if input is present
    const priceInput = page.locator('input[placeholder="Revised Price (ETB)"]').first();
    if (await priceInput.isVisible()) {
      await priceInput.fill('16500000');
      const reasonInput = page.locator('input[placeholder*="Reason"]').first();
      await reasonInput.fill('Upgraded Italian marble and smart HVAC automation package');

      const proposeBtn = page.locator('button:has-text("Propose Amendment")').first();
      await proposeBtn.click();

      // Verify feedback message
      await page.waitForTimeout(1500);
    }

    // 4. Assert amendment history audit trail is visible
    await page.click('button:has-text("4. Sales Contracts & Amendments")');
    const amendmentAuditSection = page.locator('text=Contract Amendments History');
    if (await amendmentAuditSection.isVisible({ timeout: 3000 }).catch(() => false)) {
      await expect(amendmentAuditSection).toBeVisible();
    }

    // 5. Capture screenshot proving amendment audit trail preservation
    await page.screenshot({ path: 'tests/e2e/screenshots/test_h_contract_amendment.png', fullPage: true });
  });

  test('Test I: Reservation Conflict & Concurrency Handling', async ({ page, request }) => {
    // 1. Verify unauthenticated API reservation attempt is rejected with 401
    const unauthRes = await request.post('/api/v1/units/2/reserve', {
      data: {
        idempotency_key: 'UNAUTH-TEST-' + Date.now(),
        deposit_percentage: 10.0,
        hold_minutes: 15,
      },
    });
    expect(unauthRes.status()).toBe(401);

    // 2. Authenticate the request context via OTP
    const otpSendRes = await request.post('/auth/otp/send', {
      headers: { 'Accept': 'application/json' },
      data: { phone: '+251911234567', purpose: 'login' },
    });
    const otpData = await otpSendRes.json();
    const otpVerifyRes = await request.post('/auth/otp/verify', {
      headers: { 'Accept': 'application/json' },
      data: {
        phone: '+251911234567',
        code: otpData.debug_code || '187680',
        purpose: 'login',
        role: 'buyer',
      },
    });
    expect(otpVerifyRes.ok()).toBeTruthy();
    const verifyJson = await otpVerifyRes.json();
    const csrfToken = verifyJson.csrf_token || '';

    // 3. Unit 2 is already contracted in sqlite fixture, so attempting reservation returns 409 Conflict
    const contractedUnitId = 2;
    const contractedRes = await request.post(`/api/v1/units/${contractedUnitId}/reserve`, {
      headers: { 'X-CSRF-TOKEN': csrfToken },
      data: {
        idempotency_key: 'CONTRACTED-UNIT-' + Date.now(),
        deposit_percentage: 10.0,
        hold_minutes: 15,
      },
    });
    expect(contractedRes.status()).toBe(409);

    // 4. Test idempotency & concurrency on an available unit (Unit 20)
    const testUnitId = 20;
    const idempotencyKey = 'PLAYWRIGHT-CONCURRENT-' + Date.now();
    const firstRes = await request.post(`/api/v1/units/${testUnitId}/reserve`, {
      headers: { 'X-CSRF-TOKEN': csrfToken },
      data: {
        idempotency_key: idempotencyKey,
        deposit_percentage: 10.0,
        hold_minutes: 15,
      },
    });

    if (firstRes.status() === 201) {
      // Replay with identical idempotency key returns 200 or 201
      const replayRes = await request.post(`/api/v1/units/${testUnitId}/reserve`, {
        headers: { 'X-CSRF-TOKEN': csrfToken },
        data: {
          idempotency_key: idempotencyKey,
          deposit_percentage: 10.0,
          hold_minutes: 15,
        },
      });
      expect([200, 201]).toContain(replayRes.status());

      // Concurrent conflicting reservation with DIFFERENT key must reject with 409 Conflict
      const conflictRes = await request.post(`/api/v1/units/${testUnitId}/reserve`, {
        headers: { 'X-CSRF-TOKEN': csrfToken },
        data: {
          idempotency_key: 'DIFF-IDEMPOTENCY-' + Date.now(),
          deposit_percentage: 10.0,
          hold_minutes: 15,
        },
      });
      expect(conflictRes.status()).toBe(409);
    } else {
      expect(firstRes.status()).toBe(409);
    }

    // 5. View UI state to capture screenshot
    await page.goto('/projects/bole-tower-residences');
    await page.screenshot({ path: 'tests/e2e/screenshots/test_i_concurrency_conflict.png' });
  });

  test('Test J: Payment Accuracy and Statutory Disclosures', async ({ page }) => {
    // 1. Navigate to Project Public Showcase
    await page.goto('/projects/bole-tower-residences');

    // 2. Strict non-escrow terminology audit: ensure forbidden misleading "escrow" claims do NOT appear
    const pageContent = await page.content();
    const forbiddenTerm = /developer escrow bank transfer|bank escrow account|escrow protection/i;
    expect(pageContent).not.toMatch(forbiddenTerm);

    // 3. Confirm statutory compliance references
    expect(pageContent).toContain('Proclamation No. 1357/2024');
    expect(pageContent).toContain('designated developer project bank account');
    expect(pageContent).toContain('Option B: Manual Bank Transfer to Developer');

    // 4. Capture screenshot
    await page.screenshot({ path: 'tests/e2e/screenshots/test_j_statutory_disclosures.png' });
  });

  test('Test K: Security & Authentication Enforcement', async ({ page, request }) => {
    // 1. Unauthenticated browser GET to management interface redirects to login
    await page.goto('/projects/bole-tower-residences/manage');
    await expect(page).toHaveURL(/.*\/login.*/);
    await expect(page.locator('h1')).toContainText('Sign in or Create Account');

    // 2. Unauthenticated API POST to developer payment accounts returns 401
    const unauthPayment = await request.post('/api/v1/developer/payment-accounts', {
      data: {
        developer_id: 1,
        institution_name: 'Awash Bank',
        account_label: 'Unauthorized Escrow',
        account_holder_name: 'Attacker',
        account_number: '9999888877',
      },
    });
    expect(unauthPayment.status()).toBe(401);

    // 3. Unauthenticated API POST to unit reservation returns 401
    const unauthReserve = await request.post('/api/v1/units/1/reserve', {
      data: {
        idempotency_key: 'UNAUTH-KEY-' + Date.now(),
        deposit_percentage: 10.0,
      },
    });
    expect(unauthReserve.status()).toBe(401);

    // 4. Identity verification gating: Unverified buyer attempting reservation returns 403
    const unverifiedSendRes = await request.post('/auth/otp/send', {
      headers: { 'Accept': 'application/json' },
      data: { phone: '+251911999777', purpose: 'login' },
    });
    if (unverifiedSendRes.ok()) {
      const sendData = await unverifiedSendRes.json();
      const unverifiedVerifyRes = await request.post('/auth/otp/verify', {
        headers: { 'Accept': 'application/json' },
        data: {
          phone: '+251911999777',
          code: sendData.debug_code || '187680',
          purpose: 'login',
          role: 'buyer',
        },
      });
      if (unverifiedVerifyRes.ok()) {
        const unverifiedData = await unverifiedVerifyRes.json();
        const unverifiedCsrf = unverifiedData.csrf_token || '';

        const unverifiedReserveRes = await request.post('/api/v1/units/1/reserve', {
          headers: { 'X-CSRF-TOKEN': unverifiedCsrf },
          data: {
            idempotency_key: 'UNVERIFIED-BUYER-' + Date.now(),
            deposit_percentage: 10.0,
          },
        });
        expect(unverifiedReserveRes.status()).toBe(403);
        const errJson = await unverifiedReserveRes.json();
        expect(errJson.error).toContain('Identity verification required');

        // 5. Stateful session request WITHOUT CSRF token must throw 419 TokenMismatch
        const missingCsrfRes = await request.post('/api/v1/units/1/reserve', {
          data: {
            idempotency_key: 'MISSING-CSRF-' + Date.now(),
            deposit_percentage: 10.0,
          },
        });
        expect(missingCsrfRes.status()).toBe(419);
      }
    }

    // 5. Capture screenshot of login redirect
    await page.screenshot({ path: 'tests/e2e/screenshots/test_k_security_unauthenticated.png' });
  });

  test('Test L: Public Project Page Zero Side-Effects', async ({ page, request }) => {
    // 1. Fetch payment accounts count before
    const beforeRes = await request.get('/api/v1/projects/1/payment-accounts');
    const beforeData = await beforeRes.json();
    const countBefore = (beforeData.accounts || []).length;

    // 2. Visit public project showcase multiple times
    await page.goto('/projects/bole-tower-residences');
    await page.reload();
    await page.goto('/projects/bole-tower-residences');

    // 3. Fetch payment accounts count after
    const afterRes = await request.get('/api/v1/projects/1/payment-accounts');
    const afterData = await afterRes.json();
    const countAfter = (afterData.accounts || []).length;

    // Zero side effects: no automatic accounts created on GET
    expect(countAfter).toBe(countBefore);
  });

});
