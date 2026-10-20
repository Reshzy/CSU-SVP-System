import { expect, type Locator, type Page } from '@playwright/test';

export const DEMO_PASSWORD = 'password123';

export const users = {
    dean: 'maycmartinez03@csu.edu.ph',
    supply: 'supply@cagsu.edu.ph',
    budget: 'budget@cagsu.edu.ph',
    executive: 'executive@cagsu.edu.ph',
    canvassing: 'canvassing@cagsu.edu.ph',
    secretariat: 'bac.secretary@cagsu.edu.ph',
} as const;

export async function loginAs(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(DEMO_PASSWORD);
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/dashboard\/?$/);
}

export async function logout(page: Page): Promise<void> {
    await page.locator('[data-test="sidebar-menu-button"]').click();
    await page.locator('[data-test="logout-button"]').click();
    await expect(page.getByRole('link', { name: 'Log in' }).first()).toBeVisible();
}

export async function openRowAction(page: Page, label: string): Promise<void> {
    await page.getByRole('button', { name: 'Open row actions' }).first().click();
    await page.getByRole('menuitem', { name: label }).click();
}

export async function openRowActionInRow(
    page: Page,
    rowText: string,
    label: string,
): Promise<void> {
    await page
        .locator('tr', { hasText: rowText })
        .getByRole('button', { name: 'Open row actions' })
        .click();
    await page.getByRole('menuitem', { name: label }).click();
}

export async function fillDateInput(locator: Locator, value: string): Promise<void> {
    await locator.evaluate((element, nextValue) => {
        const input = element as HTMLInputElement;
        const prototype = Object.getOwnPropertyDescriptor(
            window.HTMLInputElement.prototype,
            'value',
        );

        prototype?.set?.call(input, nextValue);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }, value);

    await expect(locator).toHaveValue(value);
}

export async function expectStatus(page: Page, status: string): Promise<void> {
    await expect(page.getByTestId('pr-status')).toContainText(status, {
        ignoreCase: true,
    });
}
