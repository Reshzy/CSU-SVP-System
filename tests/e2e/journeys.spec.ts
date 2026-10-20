import { expect, test } from '@playwright/test';
import {
    fillDateInput,
    loginAs,
    logout,
    openRowAction,
    openRowActionInRow,
    users,
} from './helpers';

const groupedTitle = 'E2E grouped AOQ and batch PO';

function weekFromNow(): string {
    const date = new Date();
    date.setDate(date.getDate() + 7);

    return date.toISOString().slice(0, 10);
}

test.describe.serial('Slice 9 journeys', () => {
    test('landing loads CagSU SVP Portal and the dean can log in', async ({
        page,
    }) => {
        await page.goto('/');
        await expect(page.getByRole('heading', { name: 'CagSU SVP Portal' })).toBeVisible();
        await page.getByRole('link', { name: 'Log in' }).first().click();
        await loginAs(page, users.dean);
        await expect(page.getByRole('heading', { name: 'Work queues' })).toBeVisible();
    });

    test('happy path reaches bac evaluation', async ({ page }) => {
        await loginAs(page, users.dean);
        await page.goto('/purchase-requests/create');
        await page.locator('#pr_title').fill('E2E office supplies');
        await page.locator('#purpose').fill('For classes this quarter');
        await fillDateInput(page.locator('#date_needed'), weekFromNow());
        await page.getByTestId('ppmp-item-select').click();
        await page.getByRole('option', { name: /Ballpen/ }).first().click();
        await expect(page.getByTestId('ppmp-item-select')).toContainText('Ballpen');
        await page.getByRole('button', { name: 'Submit to Supply' }).click();
        await expect(page).toHaveURL(/\/purchase-requests\/\d+$/);

        const prNumber = (await page.locator('h2').first().innerText()).trim();
        expect(prNumber).toMatch(/^PR-\d{4}-\d{4}$/);

        await logout(page);
        await loginAs(page, users.supply);
        await page.goto('/supply/purchase-requests');
        await expect(page.getByRole('link', { name: prNumber })).toBeVisible();
        await openRowAction(page, 'Activate');
        await expect(page).toHaveURL(/\/supply\/purchase-requests\/\d+$/);

        await logout(page);
        await loginAs(page, users.budget);
        await page.goto('/budget/purchase-requests');
        await page.getByRole('link', { name: prNumber }).click();
        await page.locator('#earmark_programs_activities').fill('Instruction');
        await page.locator('#earmark_responsibility_center').fill('CA');
        await fillDateInput(page.locator('#earmark_date_to'), weekFromNow());
        await page.getByRole('button', { name: 'Approve earmark' }).click();
        await expect(page).toHaveURL(/\/budget\/purchase-requests\/?$/);

        await logout(page);
        await loginAs(page, users.executive);
        await page.goto('/ceo/purchase-requests');
        await page.getByRole('link', { name: prNumber }).click();
        await page.getByRole('button', { name: 'Submit decision' }).click();
        await expect(page).toHaveURL(/\/ceo\/purchase-requests\/?$/);

        await logout(page);
        await loginAs(page, users.dean);
        await page.goto('/purchase-requests');
        await page.getByRole('link', { name: prNumber }).click();
        await expect(page.getByTestId('pr-status')).toContainText('bac evaluation', {
            ignoreCase: true,
        });
    });

    test('grouped AOQ then batch purchase orders', async ({ page }) => {
        await loginAs(page, users.secretariat);
        await page.goto('/bac/quotations');
        await page.locator('tr', { hasText: groupedTitle }).getByRole('link').click();
        await page.getByRole('link', { name: 'Open AOQ' }).click();
        await page.getByTestId('generate-aoq-G1').click();
        await expect(page.getByTestId('generate-aoq-G1')).toHaveText('Regenerate AOQ');
        await expect(page.getByRole('link', { name: /Download/ }).first()).toBeVisible();
        await page.getByTestId('generate-aoq-G2').click();
        await expect(page.getByTestId('generate-aoq-G2')).toHaveText('Regenerate AOQ');

        await logout(page);
        await loginAs(page, users.supply);
        await page.goto('/supply/purchase-orders');
        await openRowActionInRow(page, groupedTitle, 'Create PO');
        await page.getByRole('link', { name: 'Create batch purchase orders' }).click();
        await expect(page.getByLabel('Delivery date required').first()).not.toHaveValue('');
        await page.getByRole('button', { name: 'Create purchase orders' }).click();
        await expect(page).toHaveURL(/\/supply\/purchase-orders\/?$/);
        await expect(page.getByText(/PO-\d{4}-/).first()).toBeVisible();
    });

    test('dean opens analytics and downloads the PR csv', async ({ page }) => {
        await loginAs(page, users.dean);
        await page.goto('/reports/analytics');
        await expect(page.getByTestId('prs-created-chart')).toBeVisible();
        await expect(page.locator('.recharts-responsive-container').first()).toBeVisible();

        await page.goto('/reports/pr');
        const downloadPromise = page.waitForEvent('download');
        await page.getByTestId('download-csv').click();
        const download = await downloadPromise;
        expect(download.suggestedFilename()).toMatch(
            /purchase_requests_report_\d{8}_\d{6}\.csv/,
        );
    });

    test('internal supplier is created pending and approved to active', async ({
        page,
    }) => {
        await loginAs(page, users.canvassing);
        await page.goto('/supply/suppliers/create');
        await page.locator('#business_name').fill('E2E North Star Trading');
        await page.locator('#contact_person').fill('Ana Cruz');
        await page.locator('#email').fill('e2e.northstar@supplier.local');
        await page.locator('#phone').fill('078-111-0001');
        await page.locator('#address').fill('Sanchez Mira, Cagayan');
        await page.getByRole('button', { name: 'Register supplier' }).click();
        await expect(page.getByRole('heading', { name: 'Register supplier' })).toBeVisible();

        await logout(page);
        await loginAs(page, users.executive);
        await page.goto('/supply/suppliers');
        await expect(page.getByText('E2E North Star Trading is pending verification')).toBeVisible();
        await page.getByRole('button', { name: 'Approve' }).first().click();
        await expect(page.getByText('E2E North Star Trading is pending verification')).toHaveCount(0);
        await expect(page.getByText('active', { exact: false }).first()).toBeVisible();
    });
});
